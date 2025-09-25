<?php

use craft\helpers\App;

return [
    // With this setting enabled, the plugin will overlay transform information on the image.
    'devMode' => App::env('CRAFT_IMGIX_DEV_MODE') ?? Craft::$app->config->general->devMode,

    'enabled' => App::env('CRAFT_IMGIX_ENABLED') ?? true,

    'imgixDomain' => App::env('CRAFT_IMGIX_DOMAIN') ?? 'your-domain.imgix.net',

    // This allows you to override the default Imgix API base URI for testing/stubbing purposes.
    // If it is null or empty, the default Imgix API URI will be used as set in the `ImgixService` class.
    'apiBaseUri' => App::env('CRAFT_IMGIX_API_BASE_URI') ?? null,

    'purgeApiKey' => App::env('CRAFT_IMGIX_PURGE_API_KEY') ?? '',

    'signingKey' => App::env('CRAFT_IMGIX_SIGNING_KEY') ?? '',

    'debugLogging' => App::env('CRAFT_IMGIX_DEBUG_LOGGING') ?? false,

    // Default imgix parameters that will be applied to all images unless overridden.
    'imgixDefaultParams' => [
    //    'auto' => 'format,compress',
    //    'cs' => 'srgb',
    ],

      // Override settings for each volume
    'volumes' => [
//        'volume handle goes here' => [
//             'devMode' => '',
//             'enabled' => '',
//             'imgixDomain' => '',
//             'imgixDefaultParams' => [],
//        ],
    ]
];
