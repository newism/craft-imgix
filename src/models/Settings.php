<?php

namespace Newism\Imgix\models;

use craft\attributes\EnvName;
use craft\config\BaseConfig;

/**
 * newism-imgix settings
 */
class Settings extends BaseConfig
{
    #[EnvName('DOMAIN')]
    public string $imgixDomain = '';
    public bool $devMode = false;
    public bool $debugLogging = false;
    public bool $enabled = true;
    public bool $includeFilesystemSubfolder = true;
    public string $subPath = '';
    public string $signingKey = '';
    public string $apiBaseUri = '';
    public string $purgeApiKey = '';
    /** @var callable|bool|null Skip non-image assets by default to avoid unnecessary imgix delivery credits */
    public mixed $skipTransform = null;
    /** @var array|callable|null */
    public mixed $imgixDefaultParams = [];
    public array $volumes = [];

    public function init(): void
    {
        parent::init();

        if ($this->skipTransform === null) {
            $this->skipTransform = fn(\craft\elements\Asset $asset, ?\craft\models\ImageTransform $transform = null) =>
                $asset->kind !== 'image';
        }
    }

    public function imgixDomain(string $value): self
    {
        $this->imgixDomain = $value;
        return $this;
    }

    public function devMode(bool $value): self
    {
        $this->devMode = $value;
        return $this;
    }

    public function debugLogging(bool $value): self
    {
        $this->debugLogging = $value;
        return $this;
    }

    public function enabled(bool $value): self
    {
        $this->enabled = $value;
        return $this;
    }

    public function includeFilesystemSubfolder(bool $value): self
    {
        $this->includeFilesystemSubfolder = $value;
        return $this;
    }

    public function subPath(string $value): self
    {
        $this->subPath = $value;
        return $this;
    }

    public function signingKey(string $value): self
    {
        $this->signingKey = $value;
        return $this;
    }

    public function apiBaseUri(string $value): self
    {
        $this->apiBaseUri = $value;
        return $this;
    }

    public function purgeApiKey(string $value): self
    {
        $this->purgeApiKey = $value;
        return $this;
    }

    public function skipTransform(callable|bool $value): self
    {
        $this->skipTransform = $value;
        return $this;
    }

    public function imgixDefaultParams(callable|array $value): self
    {
        $this->imgixDefaultParams = $value;
        return $this;
    }

    public function volumes(array $value): self
    {
        $this->volumes = $value;
        return $this;
    }

    public function attributeLabels(): array
    {
        return [
            'imgixDomain' => 'Imgix Domain',
            'includeFilesystemSubfolder' => 'Include Filesystem Subfolder',
            'subPath' => 'Sub Path',
            'enabled' => 'Enabled',
            'devMode' => 'Dev Mode',
            'debugLogging' => 'Debug Logging',
            'signingKey' => 'Signing Key',
            'apiBaseUri' => 'API Base URI',
            'purgeApiKey' => 'Purge API Key',
            'skipTransform' => 'Skip Transform',
            'imgixDefaultParams' => 'Default Params',
            'volumes' => 'Volumes',
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['imgixDomain'], 'required'],
            [['imgixDomain'], 'match',
                'pattern' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/',
                'message' => '{attribute} must be a valid domain without protocol (e.g. your-source.imgix.net).',
            ],
        ];
    }
}
