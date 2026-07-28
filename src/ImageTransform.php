<?php

namespace Newism\Imgix;

class ImageTransform extends \craft\models\ImageTransform
{
    public array $imgix = [];
    public float|string|null $ratio = null;

    /**
     * Serve the original file instead of a rendered variant.
     *
     * The URL is still served from the imgix domain and CDN, but with no rendering
     * parameters — imgix delivers the source file untouched. Default params from the
     * config file, and the transform's own width/height/quality/format/mode/ratio,
     * are all ignored. `dl` is the only imgix param that survives.
     *
     * Not to be confused with `skipImgix`, which bypasses imgix entirely and falls
     * back to the filesystem URL.
     */
    public ?bool $renderOriginal = null;
}
