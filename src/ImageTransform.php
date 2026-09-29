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

    /**
     * Resolve `ratio` into the missing dimension as soon as the transform is created.
     *
     * Craft reads the transform's width/height for `{{ asset.width }}` / `{{ asset.height }}`
     * without generating a URL, so the ratio has to be applied here rather than only in
     * ImgixService::getTransformUrl(). A ratio with neither dimension needs the source
     * image's size, so that case is still resolved when the URL is generated.
     */
    public function init(): void
    {
        parent::init();

        if (!is_numeric($this->ratio) || $this->ratio <= 0) {
            return;
        }

        if ($this->width && !$this->height) {
            $this->height = (int)round($this->width / $this->ratio);
        } elseif ($this->height && !$this->width) {
            $this->width = (int)round($this->height * $this->ratio);
        }
    }
}
