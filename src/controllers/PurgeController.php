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
        $element = Asset::findOne(['id' => $elementId]);

        // Throw an error if the asset is not found
        if (!$element) {
            Craft::error("Asset with id {$elementId} not found");
        }

        // If the asset is in a volume that can be purged, add a purge job
        if (Plugin::assetVolumeCanBePurged($element)) {
            Plugin::addPurgeJob($element->getUrl());
            Craft::$app->getSession()->setNotice('Imgix purge scheduled for: ' . $element->title);
        }

        // Redirect to the asset edit page
        $url = $element->getCpEditUrl();

        return $this->redirect($url);
    }
}
