<?php

namespace Newism\Imgix;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineMenuItemsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterElementActionsEvent;
use craft\enums\Color;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\Cp;
use craft\helpers\Typecast;
use craft\log\MonologTarget;
use craft\models\ImageTransform;
use Monolog\Formatter\LineFormatter;
use Newism\Imgix\elements\actions\PurgeImgixAsset;
use Newism\Imgix\jobs\PurgeAssetImgixCacheJob;
use Newism\Imgix\models\Settings;
use Newism\Imgix\services\ImgixService;
use Newism\Imgix\web\twig\TwigExtension;
use Psr\Log\LogLevel;

/**
 * Imgix plugin
 *
 * @method static Imgix getInstance()
 * @author Newism <support@newism.com.au>
 * @copyright Newism
 * @license https://craftcms.github.io/license/ Craft License
 * @property ImgixService $imgix
 */
class Imgix extends BasePlugin
{
    public bool $hasCpSettings = true;

    public const DEBUG_LOG_CATEGORY = 'newism-imgix';

    private ?Settings $_settings = null;
    private array $assetsToPurge = [];

    public static function config(): array
    {
        return [
            'components' => [
                'imgix' => ['class' => ImgixService::class],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function () {
            Craft::$container->set(ImageTransform::class, [
                'class' => \Newism\Imgix\ImageTransform::class,
            ]);
            Craft::$app->view->registerTwigExtension(new TwigExtension());
            $this->attachEventHandlers();
            $this->registerLogTarget();
        });

    }

    private function registerLogTarget(): void
    {
        $dispatcher = Craft::getLogger()->dispatcher ?? null; // @phpstan-ignore nullCoalesce.property
        if (!$dispatcher) { // @phpstan-ignore booleanNot.alwaysFalse
            return;
        }

        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'newism-imgix',
            'categories' => [static::DEBUG_LOG_CATEGORY . '*'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'maxFiles' => 30,
            'formatter' => new LineFormatter(
                format: "[%datetime%] %level_name%\n%message%\n\n",
                dateFormat: 'Y-m-d H:i:s',
                allowInlineLineBreaks: true,
            ),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DEFINE_URL,
            function (DefineAssetUrlEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                $transform = $event->transform;
                $event->url = self::getInstance()->imgix->generateUrl($asset, $transform);
            }
        );

        if (self::getInstance()->getSettings()->purgeApiKey) {
            Event::on(
                Asset::class,
                Asset::EVENT_BEFORE_SAVE,
                function (ModelEvent $event) {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    $relevantScenarios = [
                        Asset::SCENARIO_CREATE,
                        Asset::SCENARIO_LIVE,
                        Asset::SCENARIO_FILEOPS,
                        Asset::SCENARIO_MOVE,
                        Asset::SCENARIO_REPLACE,
                    ];
                    if (!in_array($asset->getScenario(), $relevantScenarios)) {
                        return;
                    }

                    if ($asset->newLocation) {
                        $this->assetsToPurge[$asset->id] = [];

                        if (self::assetCanBePurged($asset)) {
                            $this->assetsToPurge[$asset->id][] = $asset->getUrl();
                        }
                    }
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_SAVE,
                function (ModelEvent $event) {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    if (isset($this->assetsToPurge[$asset->id])) {
                        $latestAssetUrl = $asset->getUrl();
                        if (!in_array($latestAssetUrl, $this->assetsToPurge[$asset->id], true)) {
                            if (self::assetCanBePurged($asset)) {
                                $this->assetsToPurge[$asset->id][] = $latestAssetUrl;
                            }
                        }

                        foreach ($this->assetsToPurge[$asset->id] as $assetUrl) {
                            self::addPurgeJob($assetUrl);
                        }

                        unset($this->assetsToPurge[$asset->id]);
                    }
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_DELETE,
                function (\yii\base\Event $event) {
                    /** @var Asset $asset */
                    $asset = $event->sender;

                    if (self::assetVolumeCanBePurged($asset)) {
                        self::addPurgeJob($asset->getUrl());
                    }
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_REGISTER_ACTIONS,
                function (RegisterElementActionsEvent $event) {
                    $event->actions[] = PurgeImgixAsset::class;
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_DEFINE_ACTION_MENU_ITEMS,
                function (DefineMenuItemsEvent $event) {
                    $event->items[] = [
                        'icon' => 'cloud-minus',
                        'label' => Craft::t('newism-imgix', 'Purge Imgix Cache'),
                        'action' => 'newism-imgix/purge',
                        'params' => ['elementId' => $event->sender->id],
                    ];
                }
            );
        }
    }

    protected function settingsHtml(): ?string
    {
        $settings = $this->getSettings();
        $settings->validate();

        $configFilePath = Craft::$app->getConfig()->getConfigFilePath($this->handle);
        $configFileExists = file_exists($configFilePath);

        $volumeData = [];
        foreach ($settings->volumes as $handle => $volumeSettings) {
            $volumeData[$handle] = is_object($volumeSettings)
                ? array_filter($volumeSettings->toArray(), fn($v) => $v !== null)
                : $volumeSettings;
        }

        // Build path debug info for each volume
        $volumePathDebug = [];
        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $volSettings = self::getInstance()->imgix->getSettingsForVolume($volume);
            $fs = $volume->getFs();

            $fsSubfolder = null;
            $fsAddSubfolderToRootUrl = null;
            if (property_exists($fs, 'subfolder')) {
                $fsSubfolder = App::parseEnv($fs->subfolder);
            }
            if (property_exists($fs, 'addSubfolderToRootUrl')) {
                $fsAddSubfolderToRootUrl = $fs->addSubfolderToRootUrl;
            }

            $sampleAssets = [];
            foreach (array_keys(Assets::getFileKinds()) as $kind) {
                $asset = Asset::find()->volume($volume)->kind($kind)->limit(1)->orderBy(['elements.id' => SORT_DESC])->one();
                if (!$asset) {
                    continue;
                }
                $skipTransform = false;
                if (isset($volSettings->skipTransform)) {
                    $skip = $volSettings->skipTransform;
                    $skipTransform = is_callable($skip) ? $skip($asset, null) : (bool) $skip;
                }

                $sampleAssets[] = [
                    'filename' => $asset->filename,
                    'kind' => $asset->kind,
                    'path' => $asset->getPath(),
                    'skipped' => $skipTransform,
                    'skippedStatusHtml' => Cp::statusLabelHtml([
                        'color' => $skipTransform ? Color::Teal : Color::Red,
                        'icon' => $skipTransform ? 'check' : 'xmark',
                        'label' => $skipTransform ? Craft::t('app', 'Yes') : Craft::t('app', 'No'),
                    ]),
                    'transformedUrl' => $asset->getUrl(),
                ];
            }

            $volumePathDebug[$volume->handle] = [
                'volume' => [
                    'name' => $volume->name,
                    'id' => $volume->id,
                    'subpath' => $volume->getSubpath(ensureTrailing: false, parse: true) ?: null,
                    'rootUrl' => $volume->getRootUrl(),
                ],
                'filesystem' => [
                    'type' => $fs::displayName(),
                    'handle' => $fs->handle,
                    'baseUrl' => $fs->hasUrls ? App::parseEnv($fs->url) : null,
                    'rootUrl' => $fs->getRootUrl(),
                    'subfolder' => $fsSubfolder,
                    'addSubfolderToRootUrl' => $fsAddSubfolderToRootUrl,
                ],
                'imgix' => [
                    'domain' => $volSettings->imgixDomain,
                    'enabled' => $volSettings->enabled,
                    'includeFilesystemSubfolder' => $volSettings->includeFilesystemSubfolder,
                    'subPath' => $volSettings->subPath ?: null,
                    'signingKey' => $volSettings->signingKey ? true : false,
                    'labels' => $volSettings->attributeLabels(),
                ],
                'sampleAssets' => $sampleAssets,
            ];
        }

        return Craft::$app->view->renderTemplate('newism-imgix/settings/_index', [
            'configFilePath' => $configFilePath,
            'configFileExists' => $configFileExists,
            'errors' => $settings->getErrors(),
            'config' => [
                ['imgixDomain', 'CRAFT_IMGIX_DOMAIN', $settings->imgixDomain],
                ['includeFilesystemSubfolder', 'CRAFT_IMGIX_INCLUDE_FILESYSTEM_SUBFOLDER', $settings->includeFilesystemSubfolder ? 'true' : 'false'],
                ['enabled', 'CRAFT_IMGIX_ENABLED', $settings->enabled ? 'true' : 'false'],
                ['devMode', 'CRAFT_IMGIX_DEV_MODE', $settings->devMode ? 'true' : 'false'],
                ['debugLogging', 'CRAFT_IMGIX_DEBUG_LOGGING', $settings->debugLogging ? 'true' : 'false'],
                ['signingKey', 'CRAFT_IMGIX_SIGNING_KEY', $settings->signingKey ? '••••••••' : null],
                ['purgeApiKey', 'CRAFT_IMGIX_PURGE_API_KEY', $settings->purgeApiKey ? '••••••••' : null],
                ['apiBaseUri', 'CRAFT_IMGIX_API_BASE_URI', $settings->apiBaseUri ?: null],
                ['skipTransform', '—', is_callable($settings->skipTransform) ? 'callable' : ($settings->skipTransform ? 'true' : 'false')],
                ['imgixDefaultParams', '—', $settings->imgixDefaultParams ? json_encode($settings->imgixDefaultParams) : null],
            ],
            'volumeData' => $volumeData,
            'volumePathDebug' => $volumePathDebug,
        ]);
    }

    /**
     * @return Settings
     */
    public function getSettings(): ?Model
    {
        if ($this->_settings !== null) {
            return $this->_settings;
        }

        /** @var Settings $settings */
        $settings = parent::getSettings();

        $this->applyEnvConfig($settings, Settings::class, 'CRAFT_IMGIX_');

        return $this->_settings = $settings;
    }

    protected function afterInstall(): void
    {
        parent::afterInstall();

        $source = __DIR__ . '/config.php';
        $destination = Craft::getAlias("@root/config/{$this->handle}.php");

        if (!file_exists($destination)) {
            copy($source, $destination);
            Craft::info("Copied default config to config/{$this->handle}.php", self::DEBUG_LOG_CATEGORY);
        }
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    private function applyEnvConfig(object $model, string $class, string $prefix): void
    {
        $envConfig = App::envConfig($class, $prefix);
        Typecast::properties($class, $envConfig);

        foreach ($envConfig as $name => $value) {
            if (method_exists($model, $name)) {
                try {
                    $model->$name($value);
                    continue;
                } catch (\Throwable) {
                }
            }
            $model->$name = $value;
        }
    }

    public static function addPurgeJob(string $url): void
    {
        $job = new PurgeAssetImgixCacheJob(['assetUrl' => $url]);
        // Higher priority than Blitz (10)
        Craft::$app->queue->priority(9)->push($job);
    }

    /**
     * Check if the asset's volume is configured for imgix purging.
     * Uses settings check only — does not trigger URL generation.
     */
    public static function assetVolumeCanBePurged(Asset $asset): bool
    {
        $volumeSettings = self::getInstance()->imgix->getSettingsForVolume($asset->getVolume());

        return $volumeSettings->enabled && !empty($volumeSettings->imgixDomain);
    }

    public static function assetCanBePurged(Asset $asset): bool
    {
        return self::assetVolumeCanBePurged($asset)
            && !self::getInstance()->imgix->shouldSkipTransform($asset);
    }
}
