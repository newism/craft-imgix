<?php

namespace Newism\Imgix\jobs;

use craft\queue\BaseJob;
use Craft;
use Newism\Imgix\Imgix;

class PurgeAssetImgixCacheJob extends BaseJob
{
    public string $assetUrl;

    public function execute($queue): void
    {
        try {
            Imgix::getInstance()->imgix->purgeUrl($this->assetUrl);
        } catch (\Throwable $e) {
            Craft::error(
                Craft::t('newism-imgix', 'Could not purge Imgix cache url: {assetUrl} - {error}', [
                    'assetUrl' => $this->assetUrl,
                    'error' => $e->getMessage()
                ]),
                Imgix::DEBUG_LOG_CATEGORY
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
