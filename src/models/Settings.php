<?php

namespace Newism\Imgix\models;

use craft\base\Model;

/**
 * newism-imgix settings
 */
class Settings extends Model
{
    public bool $devMode = false;
    public array|\Closure $defaultImgixParams = [];
}
