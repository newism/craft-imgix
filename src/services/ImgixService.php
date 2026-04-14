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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Imgix\UrlBuilder;
use Newism\Imgix\Imgix;
use Newism\Imgix\models\Settings;
use Newism\Imgix\models\VolumeSettings;
use yii\di\ServiceLocator;

class ImgixService extends ServiceLocator
{
    private ?Client $client = null;
    private array $volumeSettingsCache = [];

    public function getPlaceholderSVG(string $width, string $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background: transparent' />");
    }

    public function generateUrl(Asset $asset, mixed $transform = null): ?string
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

        // Temporarily reset the transform to get the original source width ahd height
        $asset->setTransform(null);
        $sourceWidth = $asset->getWidth() ?? 0;
        $sourceHeight = $asset->getHeight() ?? 0;

        // Normalise and set the transform back on the asset.
        $transform = ImageTransforms::normalizeTransform($transform);
        $asset->setTransform($transform);

        if (isset($volumeSettings->skipTransform)) {
            $skip = $volumeSettings->skipTransform;
            $skipTransform = is_callable($skip)
                ? $skip($asset, $transform)
                : (bool) $skip;
            if ($skipTransform) {
                return null;
            }
        }

        $defaultImgixParams = [];
        if (isset($volumeSettings->imgixDefaultParams)) {
            $params = $volumeSettings->imgixDefaultParams;
            $defaultImgixParams = is_callable($params)
                ? $params($asset, $transform)
                : (array) $params;
        }

        $httpQueryParams = $defaultImgixParams;

        if ($transform) {
            // Resolve ratio to concrete width/height
            // We set these on the transform so Craft's _dimensions() returns correct values
            // for {{ asset.width }} / {{ asset.height }}
            if (isset($transform->ratio) && \is_numeric($transform->ratio)) {
                if (!$transform->width && !$transform->height) {
                    $transform->width = $sourceWidth;
                }

                if ($transform->width && !$transform->height) {
                    $transform->height = (int) round($transform->width / $transform->ratio);
                } elseif ($transform->height && !$transform->width) {
                    $transform->width = (int) round($transform->height * $transform->ratio);
                }
            }

            $transformWidth = $transform->width;
            $transformHeight = $transform->height;

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

            // Use Craft's dimension calculation to respect upscale settings
            [$targetWidth, $targetHeight] = ($sourceWidth && $sourceHeight)
                ? Image::targetDimensions($sourceWidth, $sourceHeight, $transformWidth, $transformHeight, $transform->mode, $transform->upscale)
                : [$transformWidth, $transformHeight];

            $httpQueryParams = array_merge($httpQueryParams, [
                'w' => $targetWidth,
                'h' => $targetHeight,
                'q' => $transform->quality ?: Craft::$app->config->general->defaultImageQuality,
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

        // Bypass rasterization for PDFs and SVGs when no transform is applied
        if (!$transform) {
            if (in_array($asset->mimeType, ['application/pdf', 'image/svg+xml'])) {
                $httpQueryParams = [
                    'rasterize-bypass' => true
                ];
            }
        }

        $builder = new UrlBuilder($volumeSettings->imgixDomain, true, $volumeSettings->signingKey);

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
        $url = $builder->createURL($path, $httpQueryParams);

        if (Craft::$app->getConfig()->getGeneral()->revAssetUrls) {
            $url = Assets::revUrl($url, $asset, $asset->dateUpdated);
        }

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
            $volumeOverrides = array_filter($volumeOverrides->toArray(), fn($v) => $v !== null);
        }

        // Preserve callable properties that don't survive toArray()
        $baseArray = $settings->toArray();
        $baseArray['skipTransform'] = $settings->skipTransform;
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
                (string) $e->getResponse()->getBody()
            ));
        }

        if ($settings->debugLogging) {
            Craft::info(sprintf(
                "Purge: POST api/v1/purge\nPayload: %s\nResponse (Code %s): %s",
                json_encode($payload),
                $response->getStatusCode(),
                (string) $response->getBody()
            ), Imgix::DEBUG_LOG_CATEGORY);
        }

        return json_decode((string) $response->getBody(), true);
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
