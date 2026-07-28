<?php

namespace Newism\Imgix\models;

use craft\config\BaseConfig;

/**
 * Per-volume imgix settings.
 *
 * All properties are nullable — null means "inherit from global settings".
 * Only explicitly set values will override the global configuration.
 */
class VolumeSettings extends BaseConfig
{
    public ?string $imgixDomain = null;
    public ?bool $includeFilesystemSubfolder = null;
    public ?bool $includeLibraryParam = null;
    public ?string $subPath = null;
    public ?bool $devMode = null;
    public ?bool $enabled = null;
    public ?bool $debugLogging = null;
    public ?string $signingKey = null;
    /** @var callable|bool|null */
    public mixed $skipImgix = null;
    /**
     * @var callable|bool|null
     * @deprecated in 5.1.0. Use [[skipImgix]] instead.
     */
    public mixed $skipTransform = null;
    public ?array $imgixDefaultParams = null;

    public function imgixDomain(string $value): self
    {
        $this->imgixDomain = $value;
        return $this;
    }

    public function includeFilesystemSubfolder(bool $value): self
    {
        $this->includeFilesystemSubfolder = $value;
        return $this;
    }

    public function includeLibraryParam(bool $value): self
    {
        $this->includeLibraryParam = $value;
        return $this;
    }

    public function subPath(string $value): self
    {
        $this->subPath = $value;
        return $this;
    }

    public function devMode(bool $value): self
    {
        $this->devMode = $value;
        return $this;
    }

    public function enabled(bool $value): self
    {
        $this->enabled = $value;
        return $this;
    }

    public function debugLogging(bool $value): self
    {
        $this->debugLogging = $value;
        return $this;
    }

    public function signingKey(string $value): self
    {
        $this->signingKey = $value;
        return $this;
    }

    public function skipImgix(callable|bool $value): self
    {
        $this->skipImgix = $value;
        return $this;
    }

    /**
     * @deprecated in 5.1.0. Use [[skipImgix()]] instead.
     */
    public function skipTransform(callable|bool $value): self
    {
        Settings::logSkipTransformDeprecation();
        return $this->skipImgix($value);
    }

    public function imgixDefaultParams(array $value): self
    {
        $this->imgixDefaultParams = $value;
        return $this;
    }

    protected function defineRules(): array
    {
        return [
            [['imgixDomain'], 'match',
                'pattern' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/',
                'message' => '{attribute} must be a valid domain without protocol (e.g. your-source.imgix.net).',
                'when' => fn() => $this->imgixDomain !== null,
            ],
        ];
    }
}
