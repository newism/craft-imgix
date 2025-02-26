<?php

namespace Newism\Imgix;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldsEvent;
use craft\events\GenerateTransformEvent;
use craft\models\ImageTransform;
use craft\services\Assets;
use Imgix\UrlBuilder;
use Newism\Imgix\behaviors\ImageTransformBehavior;
use Newism\Imgix\models\Settings;
use Newism\Imgix\services\ImgixService;
use Newism\Imgix\web\twig\TwigExtension;
use yii\base\Event;
use yii\base\InvalidArgumentException;

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
        });

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

        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_GENERATE_TRANSFORM,
            function (GenerateTransformEvent $event) {
                $event->url = self::$plugin->imgix->generateTransformUrl($event->asset, $event->transform);
            }
        );
    }

    protected function createSettingsModel(): Model
    {
        return new Settings();
    }
}
