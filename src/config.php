<?php

use craft\helpers\App;

return [
    // With this setting enabled, the plugin will overlay transform information on the image.
    'devMode' => App::env('CRAFT_IMGIX_DEV_MODE') ?? Craft::$app->config->general->devMode,

    'enabled' => App::env('CRAFT_IMGIX_ENABLED') ?? true,

    'imgixDomain' => App::env('CRAFT_IMGIX_DOMAIN') ?? 'your-domain.imgix.net',

    // Default imgix parameters that will be applied to all images unless overridden.
    //'imgixDefaultParams' => [
    //    'auto' => 'format,compress',
    //    'cs' => 'srgb',
    //],

      // Override settings for each volume
//    'volumes' => [
//        'volume handle goes here' => [
//             'devMode' => '',
//             'enabled' => '',
//             'imgixDomain' => '',
//             'imgixDefaultParams' => [],
//        ],
//    ]
];
