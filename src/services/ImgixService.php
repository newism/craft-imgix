<?php

namespace Newism\Imgix\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\models\ImageTransform;
use Imgix\UrlBuilder;
use Newism\Imgix\behaviors\ImageTransformBehavior;
use Newism\Imgix\Plugin;
use yii\di\ServiceLocator;

class ImgixService extends ServiceLocator
{
    public function getPlaceholderSVG(string $width, string $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background: transparent' />");
    }

    public function generateTransformUrl(Asset $asset, ImageTransform|ImageTransformBehavior $transform): string
    {
        $settings = Plugin::$plugin->getSettings();

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
        $params = [
            'w' => $transform->width,
            'h' => $transform->height,
            // Fall back the quality to the default image quality as per the Craft CMS docs
            // https://craftcms.com/docs/5.x/development/image-transforms.html#possible-values
            'q' => $transform->quality ?? Craft::$app->config->general->defaultImageQuality,
            'fm' => $transform->format,
            'fit' => $imgixFit,
        ];

        // If the mode is letterbox we need to set the fill color
        if ($transform->mode === 'letterbox') {
            $params['fill'] = 'solid';
            $params['fill-color'] = $transform->fill;
        }

        // If the mode is 'crop' we need to set the focal point
        if($transform->mode === 'crop') {
            if ($asset->getHasFocalPoint()) {
                $cropPosition = $asset->getFocalPoint();
            } elseif (!preg_match('/^(top|center|bottom)-(left|center|right)$/', $transform->position)) {
                $cropPosition = 'center-center';
            } else {
                $cropPosition = $transform->position;
            }

            if (is_array($cropPosition)) {
                $params['fp-x'] = $cropPosition['x'];
                $params['fp-y'] = $cropPosition['y'];
            } else {
                [$verticalPosition, $horizontalPosition] = explode('-', $cropPosition);
                $params['fp-x'] = match ($horizontalPosition) {
                    'left' => 0,
                    'center' => 0.5,
                    'right' => 1,
                };
                $params['fp-y'] = match ($verticalPosition) {
                    'top' => 0,
                    'center' => 0.5,
                    'bottom' => 1,
                };
            }
        }

        // If devMode is enabled we overlay the transform information on the image
        if ($settings->devMode) {
            $params['txt'] = "Craft: $transform->mode / Imgix: $imgixFit";
            $params['txt-size'] = 18;
            $params['txt-align'] = 'bottom,right';
            $params['fp-debug'] = true;
        }

        // Merge the default imgix params with the transform imgix params
        $imgixParams = $transform->imgix ?? [];

        // Merge the default imgix params with the transform imgix params
        $params = array_merge($settings->defaultImgixParams ?? [], $params, $imgixParams);

        $filesystem = $asset->getVolume()->getFs();
        $baseUrl = $filesystem->getRootUrl() ?? null;
        $assetUrl = implode("", [$baseUrl, $asset->folderPath, $asset->filename]);
        ['host' => $host, 'path' => $path] = parse_url($assetUrl);
        $builder = new UrlBuilder($host);
        $url = $builder->createURL($path, $params);

        if (Craft::$app->getConfig()->getGeneral()->revAssetUrls) {
            $url = Assets::revUrl($url, $asset, $asset->dateUpdated);
        }

        return $url;
    }
}
