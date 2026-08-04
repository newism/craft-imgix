<?php

namespace Newism\Imgix\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use Newism\Imgix\Imgix;

class PurgeImgixAsset extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('newism-imgix', 'Purge Imgix Cache');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $purgedCount = 0;

        foreach ($elements as $element) {
            if (Imgix::assetCanBePurged($element)) {
                Imgix::addPurgeJob($element->getUrl());
                $purgedCount++;
            }
        }

        if ($purgedCount > 0) {
            $this->setMessage(Craft::t('newism-imgix', '{count} purge job(s) created', ['count' => $purgedCount]));
        } else {
            $this->setMessage(Craft::t('newism-imgix', 'No assets were eligible for purging'));
        }

        return true;
    }
}
