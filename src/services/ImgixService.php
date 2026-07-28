<?php

namespace Newism\Imgix\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\helpers\Image;
use craft\helpers\ImageTransforms;
use craft\models\Volume;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Imgix\UrlBuilder;
use Newism\Imgix\ImageTransform;
use Newism\Imgix\Imgix;
use Newism\Imgix\models\Settings;
use Newism\Imgix\models\VolumeSettings;
use yii\di\ServiceLocator;

class ImgixService extends ServiceLocator
{
    private ?Client $client = null;
    private array $volumeSettingsCache = [];

    /**
     * Render a placeholder .svg of width and height
     */
    public function getPlaceholderSVG(string $width, string $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background: transparent' />");
    }

    /**
     * Render the asset filesystem url, no transforms using the filesystem root url and path
     */
    public function filesystemUrl(Asset $asset, ?string $uri = null, ?DateTime $dateUpdated = null): string
    {
        return Assets::generateUrl($asset, $uri, $dateUpdated);
    }

    /**
     * Generate the imgix url for an asset, with or without a transform.
     *
     * Returns null when imgix shouldn't handle this asset, so the caller can
     * fall back to Craft's default URL generation.
     */
    public function getTransformUrl(Asset $asset, mixed $transform = null): ?string
    {
        $volume = $asset->getVolume();
        $fs = $volume->getFs();

        if (!$fs->hasUrls) {
            return null;
        }

        $volumeSettings = $this->getSettingsForVolume($volume);
        if (!$volumeSettings->enabled) {
            return null;
        }

        // Normalise and set the transform back on the asset.
        $transform = ImageTransforms::normalizeTransform($transform);

        if (isset($volumeSettings->skipImgix)) {
            $skip = $volumeSettings->skipImgix;
            $skipImgix = is_callable($skip)
                ? $skip($asset, $transform)
                : (bool)$skip;

            if ($skipImgix) {
                return null;
            }
        }

        // A transform with `renderOriginal` asks imgix to deliver the source file,
        // so no default or calculated rendering params are applied.
        $renderOriginal = $transform?->renderOriginal ?? false;

        $defaultImgixParams = [];
        if (!$renderOriginal && isset($volumeSettings->imgixDefaultParams)) {
            $params = $volumeSettings->imgixDefaultParams;
            $defaultImgixParams = is_callable($params)
                ? $params($asset, $transform)
                : (array)$params;
        }

        $httpQueryParams = $defaultImgixParams;

        if ($renderOriginal) {
            // Only `dl` survives — any other imgix param produces a rendered
            // variant, which defeats the point of the flag.
            $httpQueryParams = array_intersect_key($transform->imgix ?? [], ['dl' => true]);
        } elseif ($transform) {
            // Get the orginal width and height using a temp transform
            $tempTransform = new ImageTransform();
            $sourceWidth = $asset->getWidth($tempTransform) ?? 0;
            $sourceHeight = $asset->getHeight($tempTransform) ?? 0;

            // Resolve ratio to concrete width/height
            // We set these on the transform so Craft's _dimensions() returns correct values
            // for {{ asset.width }} / {{ asset.height }}
            if (isset($transform->ratio) && \is_numeric($transform->ratio)) {
                if (!$transform->width && !$transform->height) {
                    $transform->width = $sourceWidth;
                }

                if ($transform->width && !$transform->height) {
                    $transform->height = (int)round($transform->width / $transform->ratio);
                } elseif ($transform->height && !$transform->width) {
                    $transform->width = (int)round($transform->height * $transform->ratio);
                }
            }

            // Use Craft's dimension calculation to respect upscale settings
            [$targetWidth, $targetHeight] = ($sourceWidth && $sourceHeight)
                ? Image::targetDimensions(
                    $sourceWidth,
                    $sourceHeight,
                    $transform->width,
                    $transform->height,
                    $transform->mode,
                    $transform->upscale
                )
                : [$transform->width, $transform->height];

            if ($transform->mode === 'letterbox') {
                $transform->fill = $transform->fill ?: 'transparent';
            }

            $imgixFit = match ($transform->mode) {
                'crop' => 'crop',
                'fit' => 'clip',
                'letterbox' => $transform->upscale ? 'fill' : 'fillmax',
                'stretch' => 'scale',
                // Capture any non-standard transform modes
                default => $transform->mode,
            };

            $httpQueryParams = array_merge($httpQueryParams, [
                'w' => $targetWidth,
                'h' => $targetHeight,
                'q' => $transform->quality ?: ($httpQueryParams['q'] ?? Craft::$app->config->general->defaultImageQuality),
                'fm' => $transform->format ?: ($httpQueryParams['fm'] ?? null),
                'fit' => $imgixFit,
            ]);

            if ($transform->mode === 'letterbox') {
                $httpQueryParams['fill'] = 'blur';
                $httpQueryParams['fill-color'] = $transform->fill;
            }

            // Focal points only apply to crop mode
            if ($transform->mode === 'crop') {
                if ($asset->getHasFocalPoint()) {
                    $focalPoint = $asset->getFocalPoint();
                    $httpQueryParams['fp-x'] = $focalPoint['x'];
                    $httpQueryParams['fp-y'] = $focalPoint['y'];
                } else {
                    $position = preg_match('/^(top|center|bottom)-(left|center|right)$/', $transform->position)
                        ? $transform->position
                        : 'center-center';

                    [$verticalPosition, $horizontalPosition] = explode('-', $position);
                    $httpQueryParams['fp-x'] = match ($horizontalPosition) {
                        'left' => 0,
                        'center' => 0.5,
                        'right' => 1,
                        default => 0.5,
                    };
                    $httpQueryParams['fp-y'] = match ($verticalPosition) {
                        'top' => 0,
                        'center' => 0.5,
                        'bottom' => 1,
                        default => 0.5,
                    };
                }

                if ($volumeSettings->devMode) {
                    $httpQueryParams['fp-debug'] = true;
                }
            }

            $httpQueryParams = array_merge($httpQueryParams, $transform->imgix ?? []);

            if ($volumeSettings->devMode) {
                $httpQueryParams['txt-size'] = 18;
                $httpQueryParams['txt-align'] = 'bottom,right';
                $httpQueryParams['txt'] = "Craft: $transform->mode / Imgix: $imgixFit";
            }
        }

        // Bypass rasterization for PDFs and SVGs when nothing is being rendered
            if ((!$transform || $renderOriginal) && in_array($asset->mimeType, ['application/pdf', 'image/svg+xml'])) {
            // Without a transform the default params are dropped too, so the
            // original file is served rather than an optimised rasterization.
            if (!$transform) {
                $httpQueryParams = [];
            }
            $httpQueryParams['rasterize-bypass'] = 'true';
        }

        // `ixlib` identifies the SDK to imgix and is inert — it never triggers a
        // re-encode, so it's left to the setting even under renderOriginal
        $builder = new UrlBuilder(
            $volumeSettings->imgixDomain,
            true,
            $volumeSettings->signingKey,
            $volumeSettings->includeLibraryParam,
        );

        $pathParts = [];

        if (!empty($volumeSettings->subPath)) {
            $pathParts[] = trim($volumeSettings->subPath, '/');
        }

        if ($volumeSettings->includeFilesystemSubfolder && property_exists($fs, 'subfolder')) {
            $subfolder = App::parseEnv($fs->subfolder);
            if ($subfolder) {
                $pathParts[] = trim($subfolder, '/');
            }
        }

        $subpath = $volume->getSubpath(ensureTrailing: false, parse: true);
        if ($subpath) {
            $pathParts[] = $subpath;
        }

        $pathParts[] = $asset->getPath();

        $path = '/' . implode('/', array_filter($pathParts));
        $path = FileHelper::normalizePath($path);
        $path = str_replace('\\', '/', $path);

        $httpQueryParams = array_filter($httpQueryParams, fn($value) => $value !== null);

        if (!$renderOriginal && Craft::$app->getConfig()->getGeneral()->revAssetUrls) {
            $httpQueryParams = array_merge($httpQueryParams, Assets::revParams($asset, $asset->dateUpdated));
        }

        $url = $builder->createURL($path, $httpQueryParams);

        return $url;
    }

    public function getSettingsForVolume(Volume $volume): Settings
    {
        if (isset($this->volumeSettingsCache[$volume->handle])) {
            return $this->volumeSettingsCache[$volume->handle];
        }

        /** @var Settings $settings */
        $settings = Imgix::getInstance()->getSettings();
        $volumeOverrides = $settings->volumes[$volume->handle] ?? [];

        if ($volumeOverrides instanceof VolumeSettings) {
            $volumeSettingsModel = $volumeOverrides;
            $volumeOverrides = array_filter($volumeSettingsModel->toArray(), fn($v) => $v !== null);

            // Callables don't survive toArray() — carry the raw value across
            if ($volumeSettingsModel->skipImgix !== null) {
                $volumeOverrides['skipImgix'] = $volumeSettingsModel->skipImgix;
            }
        }

        // Normalise the deprecated key before merging, so a volume-level override
        // beats the inherited global value rather than colliding with it in init()
        if (isset($volumeOverrides['skipTransform'])) {
            $volumeOverrides['skipImgix'] ??= $volumeOverrides['skipTransform'];
            unset($volumeOverrides['skipTransform']);
        }

        // Preserve callable properties that don't survive toArray()
        $baseArray = $settings->toArray();
        $baseArray['skipImgix'] = $settings->skipImgix;
        $baseArray['imgixDefaultParams'] = $settings->imgixDefaultParams;

        return $this->volumeSettingsCache[$volume->handle] = new Settings(array_merge($baseArray, $volumeOverrides));
    }

    public function purgeUrl(string $url): ?array
    {
        $client = $this->getApiClient();
        if (empty($client)) {
            return null;
        }

        /** @var Settings $settings */
        $settings = Imgix::getInstance()->getSettings();
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['path'])) {
            return null;
        }

        $sanitisedUrl = strtok($url, '?') ?: $url;

        $payload = [
            'json' => [
                'data' => [
                    'attributes' => [
                        'url' => $sanitisedUrl,
                    ],
                    'type' => 'purges',
                ],
            ],
        ];

        try {
            $response = $client->request('POST', 'api/v1/purge', $payload);
        } catch (ClientException $e) {
            throw new \RuntimeException(sprintf(
                'Error: POST api/v1/purge returned %s: %s',
                $e->getResponse()->getStatusCode(),
                (string)$e->getResponse()->getBody()
            ));
        }

        if ($settings->debugLogging) {
            Craft::info(sprintf(
                "Purge: POST api/v1/purge\nPayload: %s\nResponse (Code %s): %s",
                json_encode($payload),
                $response->getStatusCode(),
                (string)$response->getBody()
            ), Imgix::DEBUG_LOG_CATEGORY);
        }

        return json_decode((string)$response->getBody(), true);
    }

    private function getApiClient(): ?Client
    {
        if ($this->client === null) {
            /** @var Settings $settings */
            $settings = Imgix::getInstance()->getSettings();
            if ($settings->purgeApiKey) {
                $this->client = Craft::createGuzzleClient([
                    'base_uri' => $settings->apiBaseUri ?: 'https://api.imgix.com/',
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer " . $settings->purgeApiKey,
                    ],
                ]);
            }
        }

        return $this->client;
    }
}
