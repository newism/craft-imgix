<?php

use craft\helpers\App;

return [
    // Your imgix source domain.
    'imgixDomain' => App::env('CRAFT_IMGIX_DOMAIN') ?? 'your-domain.imgix.net',

    // With this setting enabled, the plugin will overlay transform information on the image.
    'devMode' => App::env('CRAFT_IMGIX_DEV_MODE') ?? Craft::$app->config->general->devMode,

    // Globally enable or disable the plugin.
    'enabled' => App::env('CRAFT_IMGIX_ENABLED') ?? true,

    // The signing key for secure URLs. Leave blank to disable URL signing.
    'signingKey' => App::env('CRAFT_IMGIX_SIGNING_KEY') ?? '',

    // An optional callback that defines whether to skip the transform logic.
    // The callback will be passed the Asset and the ImageTransform (or null if no transform is being applied).
    // Return true to skip the transform logic and return the URL defined in the asset Filesystem.
    // Example: skip non-image assets when no transform is applied
//    'skipTransform' => function(\craft\elements\Asset $asset, ?\craft\models\ImageTransform $transform = null) {
//        return $asset->kind !== 'image' && $transform === null;
//    },

    // Default imgix parameters that will be applied to all images unless overridden.
//    'imgixDefaultParams' => [
//        'auto' => 'format,compress',
//        'cs' => 'srgb',
//    ],

      // Override settings for each volume
// 'volumes' => [
//     'volume handle goes here' => [
//          'devMode' => '',
//          'enabled' => '',
//          'imgixDomain' => '',
//          'imgixDefaultParams' => [],
//     ],
// ]


];
