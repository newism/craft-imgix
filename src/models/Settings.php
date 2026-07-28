<?php

namespace Newism\Imgix\models;

use Craft;
use craft\attributes\EnvName;
use craft\config\BaseConfig;
use craft\elements\Asset;
use craft\models\ImageTransform;
use Newism\Imgix\Imgix;
use Throwable;

/**
 * newism-imgix settings
 */
class Settings extends BaseConfig
{
    /**
     * Whether the deprecated `skipTransform` setting was used, for the settings page notice.
     */
    public static bool $usedDeprecatedSkipTransform = false;

    #[EnvName('DOMAIN')]
    public string $imgixDomain = '';
    public bool $devMode = false;
    public bool $debugLogging = false;
    public bool $enabled = true;
    public bool $includeFilesystemSubfolder = true;
    /** Include the imgix SDK's `ixlib` param. Inert — it never affects the image returned. */
    public bool $includeLibraryParam = true;
    public string $subPath = '';
    public string $signingKey = '';
    public string $apiBaseUri = '';
    public string $purgeApiKey = '';
    /** @var callable|bool|null Skip non-image assets by default to avoid unnecessary imgix delivery credits */
    public mixed $skipImgix = null;
    /**
     * @var callable|bool|null
     * @deprecated in 5.1.0. Use [[skipImgix]] instead.
     */
    public mixed $skipTransform = null;
    /** @var array|callable|null */
    public mixed $imgixDefaultParams = [];
    public array $volumes = [];

    public function init(): void
    {
        parent::init();

        if ($this->skipTransform !== null) {
            self::logSkipTransformDeprecation();
            // An explicit skipImgix wins — the deprecated key only fills the gap
            $this->skipImgix ??= $this->skipTransform;
            $this->skipTransform = null;
        }

        if ($this->skipImgix === null) {
            $this->skipImgix = fn(Asset $asset, ?ImageTransform $transform = null) => $asset->kind !== 'image';
        }
    }

    /**
     * Flag and log use of the deprecated `skipTransform` setting.
     */
    public static function logSkipTransformDeprecation(): void
    {
        self::$usedDeprecatedSkipTransform = true;

        $message = 'The `skipTransform` imgix setting has been renamed to `skipImgix`. Support for the old name will be removed in 6.0.';

        // Settings can be loaded before the database is available (console
        // install/update), and the deprecator writes to a table.
        try {
            if (Craft::$app->getIsInstalled()) {
                Craft::$app->getDeprecator()->log('newism-imgix:skipTransform', $message);
                return;
            }
        } catch (Throwable) {
            // Fall through to the log
        }

        Craft::warning($message, Imgix::DEBUG_LOG_CATEGORY);
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
        self::logSkipTransformDeprecation();
        return $this->skipImgix($value);
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
            'includeLibraryParam' => 'Include Library Param',
            'subPath' => 'Sub Path',
            'enabled' => 'Enabled',
            'devMode' => 'Dev Mode',
            'debugLogging' => 'Debug Logging',
            'signingKey' => 'Signing Key',
            'apiBaseUri' => 'API Base URI',
            'purgeApiKey' => 'Purge API Key',
            'skipImgix' => 'Skip Imgix',
            'skipTransform' => 'Skip Transform (deprecated)',
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
