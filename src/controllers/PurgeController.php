<?php

namespace Newism\Imgix\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;
use Newism\Imgix\Plugin;
use yii\web\Response;

class PurgeController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function actionIndex(): Response
    {
        // Find the asset by the supplied ID
        $this->requirePostRequest();
        $elementId = Craft::$app->request->getParam('elementId');
        $asset = Asset::findOne(['id' => $elementId]);

        // Throw an error if the asset is not found
        if (!$asset) {
            return $this->asFailure(
                Craft::t('app', "Asset with id: $elementId not found"),
            );
        }

        // If the asset is in a volume that can be purged, add a purge job
        if (Plugin::assetVolumeCanBePurged($asset)) {
            Plugin::addPurgeJob($asset->getUrl());
            return $this->asModelSuccess(
                $asset,
                Craft::t('app', 'Purge Job Created'),
                'asset',
            );
        }

        return $this->asModelFailure(
            $asset,
            Craft::t('app', 'Asset not eligible for purging'),
            'asset',
        );

    }
}
