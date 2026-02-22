<?php

namespace Newism\Imgix\services;

use Craft;
use craft\elements\Asset;
use craft\fs\Temp;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\ImageTransforms;
use craft\models\Volume;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Imgix\UrlBuilder;
use Newism\Imgix\models\Settings;
use Newism\Imgix\Plugin;
use Psr\Http\Message\ResponseInterface;
use yii\di\ServiceLocator;

class ImgixService extends ServiceLocator
{
    private ?Client $client = null;

    public function getPlaceholderSVG(string $width, string $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background: transparent' />");
    }

    public function generateUrl(Asset $asset, mixed $transform = null): ?string
    {
        /**
         * Check for temp fs and return null if it is allowing Craft CMS to handle the transform
         */
        $volume = $asset->getVolume();
        $fs = $volume->getFs();

        // If the volume doesn't have a URL we return null
        if(!$fs->hasUrls) {
            return null;
        }

        // Version check for Craft 5
        if(version_compare(Craft::$app->getVersion(), '5', '>') && Assets::isTempUploadFs($fs)) {
            return null;
        }
        // Version check for Craft 4 where Assets::isTempUploadFs doesn't exit
        elseif(version_compare(Craft::$app->getVersion(), '4', '>') && ($fs instanceof Temp)) {
            return null;
        }

        $volumeSettings = $this->getSettingsForVolume($volume);
        if(!$volumeSettings->enabled) {
            return null;
        }

        $transform = ImageTransforms::normalizeTransform($transform);

        $skipTransform = false;
        if (isset($volumeSettings->skipTransform)) {
            $skip = $volumeSettings->skipTransform;
            $skipTransform = is_callable($skip)
                ? $skip($asset, $transform)
                : (bool)$skip;
        }
        if($skipTransform) {
            return null;
        }

        // Return null when no transform is applied to use the original asset URL
        if (!$transform) {
            return null;
        }

        $defaultImgixParams = [];
        if (isset($volumeSettings->imgixDefaultParams)) {
            $params = $volumeSettings->imgixDefaultParams;
            $defaultImgixParams = is_callable($params)
                ? $params($asset, $transform)
                : (array)$params;
        }

        $httpQueryParams = $defaultImgixParams;

        $cropPosition = $asset->getFocalPoint();
        $httpQueryParams['fp-x'] = $cropPosition['x'] ?? null;
        $httpQueryParams['fp-y'] = $cropPosition['y'] ?? null;
        $httpQueryParams['fp-debug'] = $volumeSettings->devMode ?: null;


        // If we have a transform we add the imgix params
        if ($transform) {
            /*
             * If ratio is defined we need to figure out the missing dimension and set it
             * We then set the transform back on the asset so craft can recalculate the width and height
             */
            if (isset($transform->ratio) && \is_numeric($transform->ratio)) {

                // If the transform has no width or height default to width of the asset
                // to calculate the transform
                if (!$transform->width && !$transform->height) {
                    $transform->width = $asset->getWidth();
                }

                if (isset($transform->width) && !isset($transform->height)) {
                    $transform->height = round($transform->width / $transform->ratio);
                    unset($transform->ratio);
                } elseif (isset($transform->height) && !isset($transform->width)) {
                    $transform->width = round($transform->height * $transform->ratio);
                    unset($transform->ratio);
                }
            }

            // Set a default fill so Craft CMS calculates the correct dimensions
            if ($transform->mode === 'letterbox') {
                $transform->fill = $transform->fill ?: 'transparent';
            }

            $asset->setTransform($transform);

            // Convert the craft transform to a imgix fit
            $imgixFit = match ($transform->mode) {
                'crop' => 'crop',
                'fit' => 'clip',
                'letterbox' => Craft::$app->config->general->upscaleImages ? 'fill' : 'fillmax',
                'stretch' => 'scale',
                default => $transform->mode
            };

            // Set the params
            $httpQueryParams = array_merge($httpQueryParams, [
                'w' => $transform->width,
                'h' => $transform->height,
                // Fall back the quality to the default image quality as per the Craft CMS docs
                // https://craftcms.com/docs/5.x/development/image-transforms.html#possible-values
                'q' => $transform->quality ?: Craft::$app->config->general->defaultImageQuality,
                'fm' => $transform->format ?: $httpQueryParams['fm'] ?? null,
                'fit' => $imgixFit,
            ]);

            // If the mode is letterbox we need to set the fill color
            if ($transform->mode === 'letterbox') {
                $httpQueryParams['fill'] = 'blur';
                $httpQueryParams['fill-color'] = $transform->fill;
            }

            // If the mode is 'crop' we need to set the focal point
            if ($transform->mode === 'crop') {
                if ($asset->getHasFocalPoint()) {
                    $cropPosition = $asset->getFocalPoint();
                } elseif (!preg_match('/^(top|center|bottom)-(left|center|right)$/', $transform->position)) {
                    $cropPosition = 'center-center';
                } else {
                    $cropPosition = $transform->position;
                }

                if (is_array($cropPosition)) {
                    $httpQueryParams['fp-x'] = $cropPosition['x'];
                    $httpQueryParams['fp-y'] = $cropPosition['y'];
                } else {
                    [$verticalPosition, $horizontalPosition] = explode('-', $cropPosition);
                    $httpQueryParams['fp-x'] = match ($horizontalPosition) {
                        'left' => 0,
                        'center' => 0.5,
                        'right' => 1,
                    };
                    $httpQueryParams['fp-y'] = match ($verticalPosition) {
                        'top' => 0,
                        'center' => 0.5,
                        'bottom' => 1,
                    };
                }
            }

            // Merge the default imgix params with the transform imgix params
            $httpQueryParams = array_merge($httpQueryParams, $transform->imgix ?? []);

            // If devMode is enabled we overlay the transform information on the image
            if ($volumeSettings->devMode) {
                $httpQueryParams['text-size'] = 18;
                $httpQueryParams['txt-align'] = 'bottom,right';
                $httpQueryParams['txt'] = "Craft: $transform->mode / Imgix: $imgixFit";
            }
        }

        // If we don't have a transform we check if the asset is a PDF
        // If it is we bypass rasterization
        // If it's not we apply the focal point if it exists
        if (!$transform) {
            if (in_array($asset->mimeType, ['application/pdf', 'image/svg+xml'])) {
                $httpQueryParams = [
                    'rasterize-bypass' => true
                ];
            }
        }

        // Create a new builder with the imgixDomain
        $builder = new UrlBuilder($volumeSettings->imgixDomain, true, $volumeSettings->signingKey);

        $filesystem = $asset->getVolume()->getFs();
        $baseUrl = $filesystem->getRootUrl() ?? null;
        $assetUrl = implode("", [$baseUrl, $asset->folderPath, $asset->filename]);
        ['path' => $path] = parse_url($assetUrl);

        // Filter out null $httpQueryParams values
        $httpQueryParams = array_filter($httpQueryParams, fn($value) => $value !== null);
        $url = $builder->createURL($path, $httpQueryParams);

        if (Craft::$app->getConfig()->getGeneral()->revAssetUrls) {
            $url = Assets::revUrl($url, $asset, $asset->dateUpdated);
        }

        return $url;
    }

    public function getSettingsForVolume(Volume $volume): Settings
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        $volumeSettings = array_merge([
            'devMode' => $settings->devMode,
            'enabled' => $settings->enabled,
            'imgixDomain' => $settings->imgixDomain,
            'apiBaseUri' => $settings->apiBaseUri,
            'purgeApiKey' => $settings->purgeApiKey,
            'signingKey' => $settings->signingKey,
            'debugLogging' => $settings->debugLogging,
            'skipTransform' => $settings->skipTransform,
            'imgixDefaultParams' => $settings->imgixDefaultParams,
        ], $settings->volumes[$volume->handle] ?? []);

        return new Settings($volumeSettings);
    }

    public function purgeUrl(string $url): ?array
    {
        // If there is no client we return null
        $client = $this->getApiClient();
        if (empty($client)) {
            return null;
        }

        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $debugLogging = (bool) $settings->debugLogging;
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['path'])) {
            return null;
        }

        // Remove the query params and build the full URL
        $sanitisedUrl = rtrim(str_replace($parsedUrl['query'] ?? '', '', $url), '?');

        $method = 'POST';
        $uri = 'api/v1/purge';
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

        // Send the request to the imgix API
        try {
            $response = $client->request('POST', 'api/v1/purge', $payload);
        } catch (ClientException $e) {
            throw new \RuntimeException(sprintf(
                'Error: %s %s returned %s: %s',
                $method,
                $uri,
                $e->getResponse()?->getStatusCode(),
                (string) $e->getResponse()?->getBody()
            ));
        }

        // If logging is enabled we log the request and response for later reference
        if ($debugLogging) {
            // Use the `info` level as `debug` usually won't appear on a production site
            Craft::info(sprintf(
                "Purge: %s %s\nPayload: %s\nResponse (Code %s): %s",
                $method,
                $uri,
                json_encode($payload),
                $response->getStatusCode(),
                (string) $response->getBody()
            ), Plugin::DEBUG_LOG_CATEGORY);
        }

        // Note: according to the Guzzle documentation there should be a `$response->json()` method but I suspect that
        // because the API is returning a `Content-Type: application/vnd.api+json` header it's not working as expected
        $responseBody = (string) $response->getBody();

        return json_decode($responseBody, true);
    }

    private function getApiClient(): ?Client
    {
        if ($this->client === null) {
            /** @var Settings $settings */
            $settings = Plugin::getInstance()->getSettings();
            // Only create the client if we have a purgeApiKey
            if ($settings->purgeApiKey) {
                $clientOptions = [
                    'base_uri' => $settings->apiBaseUri ?: 'https://api.imgix.com/',
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer " . $settings->purgeApiKey,
                    ],
                ];

                $this->client = Craft::createGuzzleClient($clientOptions);
            }
        }

        return $this->client;
    }
}
