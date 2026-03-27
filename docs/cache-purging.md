---
outline: deep
---

# Cache Purging

When a `purgeApiKey` is configured, the plugin automatically purges the [Imgix cache](https://docs.imgix.com/en-US/apis/management/purge) when assets are saved, moved, replaced, or deleted.

Purge jobs are added to the Craft queue at priority 9 (higher than [Blitz](https://putyourlightson.com/plugins/blitz) at priority 10).

## Getting an API Key

To enable purge functionality, create an API key in the [Imgix dashboard](https://dashboard.imgix.com/api-keys) and set it as the `purgeApiKey` config value or the `CRAFT_IMGIX_PURGE_API_KEY` environment variable.

## Manual Purging

### From the Assets index page

Select one or more assets and choose "Purge Imgix Cache" from the actions menu.

### From the asset edit page

Click the action menu and choose "Purge Imgix Cache".

