<?php

namespace Newism\Imgix\behaviors;

use yii\base\Behavior;

class ImageTransformBehavior extends Behavior
{
    public array $imgix = [];
    public float|string|null $ratio = null;
}
