<?php

namespace Newism\Imgix;

class ImageTransform extends \craft\models\ImageTransform
{
    public array $imgix = [];
    public float|string|null $ratio = null;
}
