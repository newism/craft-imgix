<?php

namespace Newism\Imgix\models;

use craft\base\Model;

/**
 * newism-imgix settings
 */
class Settings extends Model
{
    public bool $devMode = false;
    public bool $enabled = true;
    public string $imgixDomain = '';
    public array $imgixDefaultParams = [];
    public string $signingKey = '';
    public mixed $skipTransform = false;
    public array $volumes = [];
}
