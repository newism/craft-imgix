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
    public ?string $imgixDomain = null;
    public array $imgixDefaultParams = [];
    public array $volumes = [];
}
