<?php

namespace Newism\Imgix;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldsEvent;
use craft\events\ModelEvent;
use craft\log\MonologTarget;
use craft\models\ImageTransform;
use Monolog\Formatter\LineFormatter;
use Newism\Imgix\behaviors\ImageTransformBehavior;
use Newism\Imgix\jobs\PurgeAssetImgixCache;
use Newism\Imgix\models\Settings;
use Newism\Imgix\services\ImgixService;
use Newism\Imgix\web\twig\TwigExtension;
use Psr\Log\LogLevel;

/**
 * Imgix plugin
 *
 * @method static Plugin getInstance()
 * @author Newism <support@newism.com.au>
 * @copyright Newism
 * @license https://craftcms.github.io/license/ Craft License
 * @property ImgixService $imgix
 */
class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public const DEBUG_LOG_CATEGORY = 'newism-imgix';

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
        self::$plugin = $this;

        // Defer most setup tasks until Craft is fully initialized
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
        // if there is no dispatcher object on the logger then exit now otherwise tests will fail
        $dispatcher = Craft::getLogger()->dispatcher ?? null;
        if (!$dispatcher) {
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
            ImageTransform::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $imageTransform = $event->sender;
                $imageTransform->attachBehaviors([
                    'imgix' => ImageTransformBehavior::class,
                ]);
            }
        );

        Event::on(
            ImageTransform::class,
            Model::EVENT_DEFINE_FIELDS,
            function (DefineFieldsEvent $event) {
                $event->fields['imgix'] = 'imgix';
            }
        );

        /**
         * For assets that don't have a transform we want to bypass rasterize
         */
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DEFINE_URL,
            function (DefineAssetUrlEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;
                $transform = $event->transform;
                $event->url = self::$plugin->imgix->generateUrl($asset, $transform);
            }
        );

        // If no Imgix API key is provded then we don't need to add the additional event handlers to purge the cache
        if (!self::$plugin->getSettings()->purgeApiKey) {
            return;
        }

        // DRY function to add a purge job to the queue
        $addPurgeJob = function ($url) {
            $newUrl = strtok($url, '?');
            $newJob = new PurgeAssetImgixCache([
                'assetUrl' => $newUrl,
            ]);
            // Blitz runs at priority 10 so we need to run this at a higher priority via a lower integer
            \Craft::$app->queue->priority(4)->push($newJob);
        };

        // DRY function to check if the asset's volume is configured for purging
        $assetVolumeCanBePurged = function (Asset $asset) {
            $assetUrl = (string) $asset->getUrl();
            $volumeSettings = Plugin::$plugin->imgix->getSettingsForVolume($asset->getVolume());
            $isEnabled = $volumeSettings['enabled'];
            $domainMatches = $assetUrl && str_contains($assetUrl, $volumeSettings['imgixDomain']);

            return $isEnabled && $domainMatches;
        };

        // Check if the asset has changed in a relevant way and queue it to be purged
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) use ($assetVolumeCanBePurged) {
                /** @var Asset $asset */
                $asset = $event->sender;

                // If its not the live scenario, a file operation or asset move then ignore otherwise this can be
                // triggered in other scenarios during an asset save which we don't want
                $relevantScenarios = [
                    Asset::SCENARIO_CREATE,
                    Asset::SCENARIO_LIVE,
                    Asset::SCENARIO_FILEOPS,
                    Asset::SCENARIO_MOVE
                ];
                if (!in_array($asset->getScenario(), $relevantScenarios)) {
                    return;
                }

                // A new file location will want the old location purged
                if ($asset->newLocation) {
                    // There is a new location so get the asset ready for purging
                    $this->assetsToPurge[$asset->id] = [];

                    // Check that the current asset volume is configured for purging and the domain matches
                    if ($assetVolumeCanBePurged($asset)) {
                        // Queue the current asset URL to be purged later as this will be the old location if a move
                        // occurs  and we still want to purge this location if the file is replaced later
                        $this->assetsToPurge[$asset->id][] = $asset->getUrl();
                    };
                }
            }
        );

        // Purge imgix cache when an asset is saved if it was queued to be purged
        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function (ModelEvent $event) use ($addPurgeJob, $assetVolumeCanBePurged) {
                /** @var Asset $asset */
                $asset = $event->sender;

                // There is an asset queued to be purged
                if (isset($this->assetsToPurge[$asset->id])) {
                    // We know there is at least one URL to purge so lets add the current URL as well
                    $latestAssetUrl = $asset->getUrl();
                    if (!in_array($latestAssetUrl, $this->assetsToPurge[$asset->id], true)) {
                        // Check that the current asset volume is configured for purging and the domain matches
                        if ($assetVolumeCanBePurged($asset)) {
                            // Purge the asset URL as its an Imgix URL
                            $this->assetsToPurge[$asset->id][] = $latestAssetUrl;
                        }
                    }

                    // Add a purge job for each URL in the array
                    foreach ($this->assetsToPurge[$asset->id] as $assetUrl) {
                        $addPurgeJob($assetUrl);
                    }

                    // Remove the asset from the queue so it doesn't get purged again
                    unset($this->assetsToPurge[$asset->id]);
                }
            }
        );

        // Purge imgix cache when an asset is deleted
        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_DELETE,
            function (\yii\base\Event $event) use ($addPurgeJob, $assetVolumeCanBePurged) {
                /** @var Asset $asset */
                $asset = $event->sender;

                // Check that the current asset volume is configured for purging and the domain matches
                if ($assetVolumeCanBePurged($asset)) {
                    $addPurgeJob($asset->getUrl());
                }
            }
        );
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
