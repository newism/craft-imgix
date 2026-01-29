<?php

use craft\helpers\App;

return [
    // Your imgix source domain.
    'imgixDomain' => App::env('CRAFT_IMGIX_DOMAIN') ?? 'your-domain.imgix.net',

    // With this setting enabled, the plugin will overlay transform information on the image.
    'devMode' => App::env('CRAFT_IMGIX_DEV_MODE') ?? Craft::$app->config->general->devMode,

    // When enabled, debug information will be logged to Craft's log files.
    'debugLogging' => App::env('CRAFT_IMGIX_DEBUG_LOGGING') ?? false,

    // Globally enable or disable the plugin.
    'enabled' => App::env('CRAFT_IMGIX_ENABLED') ?? true,

    // The signing key for secure URLs. Leave blank to disable URL signing.
    'signingKey' => App::env('CRAFT_IMGIX_SIGNING_KEY') ?? '',

    // This allows you to override the default Imgix API base URI for testing/stubbing purposes.
    // If it is null or empty, the default Imgix API URI will be used as set in the `ImgixService` class.
    'apiBaseUri' => App::env('CRAFT_IMGIX_API_BASE_URI') ?? null,

    // The API key used to authenticate purge requests.
    // Leave blank to disable the purge functionality.
    'purgeApiKey' => App::env('CRAFT_IMGIX_PURGE_API_KEY') ?? '',

    // An optional callback that defines whether to skip the transform logic.
    // The callback will be passed the Asset and the ImageTransform (or null if no transform is being applied).
    // Return true to skip the transform logic and return the URL defined in the asset Filesystem. When this returns
    // false, the plugin automatically rewrites the asset's base URL to use the configured Imgix domain.
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
