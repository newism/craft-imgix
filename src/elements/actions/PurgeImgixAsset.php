<?php

namespace Newism\Imgix\elements\actions;

use Craft;
use craft\base\ElementAction;
use Newism\Imgix\Plugin;

class PurgeImgixAsset extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('newism-imgix', 'Purge Imgix Cache');
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
            (() => {
                new Craft.ElementActionTrigger({
                    type: $type,

                    // Whether this action should be available when multiple elements are selected
                    bulk: true,

                    // Return whether the action should be available depending on which elements are selected
                    validateSelection: (selectedItems, elementIndex) => {
                      return true;
                    },
                });
            })();
        JS, [static::class]);

        return null;
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $success = false;
        // Iterate through elements and add purge jobs for assets that can be purged
        foreach ($elements as $element) {
            if (Plugin::assetVolumeCanBePurged($element)) {
                Plugin::addPurgeJob($element->getUrl());
                $success = true;
            }
        }

        if($success) {
            $this->setMessage(Craft::t('newism-imgix', 'Purge Job Created'));
        }

        if(!$success) {
            $this->setMessage(Craft::t('newism-imgix', 'No assets could be purged'));
        }

        return $success;
    }
}
