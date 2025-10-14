<?php

namespace Newism\Imgix\jobs;

use craft\queue\BaseJob;
use \Craft;
use Newism\Imgix\Plugin;

class PurgeAssetImgixCacheJob extends BaseJob
{
    public string $assetUrl;

    public function execute($queue): void
    {
        try {
            Plugin::$plugin->imgix->purgeUrl($this->assetUrl);
        } catch (\Throwable $e) {
            // Log the error in the Plugin specific log and then re-throw it so the error appears on the job
            Craft::error(
                Craft::t('newism-imgix', 'Could not purge Imgix cache url: {assetUrl} - {error}', [
                    'assetUrl' => $this->assetUrl,
                    'error' => $e->getMessage()
                ]),
                Plugin::DEBUG_LOG_CATEGORY
            );

            throw $e;
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('newism-imgix', 'Purging Imgix cache url: {assetUrl}', [
            'assetUrl' => $this->assetUrl,
        ]);
    }
}
