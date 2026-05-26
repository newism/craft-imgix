<?php

namespace Newism\Imgix\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;
use Newism\Imgix\Imgix;
use yii\web\Response;

class PurgeController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();
        $elementId = Craft::$app->request->getParam('elementId');
        $asset = Asset::findOne(['id' => $elementId]);

        if (!$asset) {
            return $this->asFailure(
                Craft::t('app', 'Asset with id: {id} not found', ['id' => $elementId]),
            );
        }

        if (Imgix::assetCanBePurged($asset)) {
            Imgix::addPurgeJob($asset->getUrl());
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
