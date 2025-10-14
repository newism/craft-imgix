<?php

namespace Newism\Imgix\models;

use craft\base\Model;

/**
 * newism-imgix settings
 */
class Settings extends Model
{
    public array $imgixDefaultParams = [];
    public array $volumes = [];
    public bool $debugLogging = true;
    public bool $devMode = false;
    public bool $enabled = true;
    public mixed $skipTransform = false;
    public string $apiBaseUri = '';
    public string $imgixDomain = '';
    public string $purgeApiKey = '';
    public string $signingKey = '';
}
