<?php

use craft\helpers\App;

return [
    // With this setting enabled, the plugin will overlay transform information on the image.
    'devMode' => App::env('CRAFT_IMGIX_DEV_MODE') ?? Craft::$app->config->general->devMode,

    // Default imgix parameters that will be applied to all images unless overridden.
    //'defaultImgixParams' => [
    //    'auto' => 'format,compress',
    //    'cs' => 'srgb',
    //],
];
