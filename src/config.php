<?php

/**
 * Imgix plugin config.
 *
 * Scalar values can be overridden by environment variables using the
 * CRAFT_IMGIX_ prefix (e.g. CRAFT_IMGIX_DOMAIN, CRAFT_IMGIX_SIGNING_KEY).
 *
 * Non-image assets are skipped by default to avoid unnecessary imgix
 * delivery credits. Set skipTransform to false to route all assets through imgix.
 *
 * @see \Newism\Imgix\models\Settings
 */

return [
    // 'imgixDomain' => 'your-domain.imgix.net',
    // 'enabled' => true,
    // 'devMode' => false,
    // 'debugLogging' => false,
    // 'includeFilesystemSubfolder' => true,
    // 'signingKey' => '',
    // 'purgeApiKey' => '',
    // 'skipTransform' => false,
    // 'imgixDefaultParams' => [
    //     'auto' => 'format,compress',
    //     'cs' => 'srgb',
    // ],
    // 'volumes' => [
    //     'images' => Newism\Imgix\models\VolumeSettings::create()
    //         ->imgixDomain('images.imgix.net')
    //         ->subPath('images')
    //         ->imgixDefaultParams(['auto' => 'format,compress']),
    //     'documents' => Newism\Imgix\models\VolumeSettings::create()
    //         ->enabled(false),
    // ],
];
