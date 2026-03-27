---
outline: deep
---

# Minimize Imgix Costs

Every asset served through Imgix consumes [delivery credits](https://docs.imgix.com/en-US/references/billing-overview) (bandwidth). imgix transitioned to a [credit-based pricing model](https://www.imgix.com/blog/credit-pricing) in late 2025:

- **Management credits**: cache storage (2 credits/GB/month)
- **Delivery credits**: bandwidth (1 credit/GB)
- **Transformation credits**: image processing (varies by feature)

Non-image assets (PDFs, ZIPs, etc.) served through Imgix consume delivery credits even though no transformation is applied.

**This plugin skips non-image assets by default** (`$asset->kind !== 'image'`). To route all assets through Imgix, set `'skipTransform' => false`. You can also disable Imgix for specific volumes:

```php
use Newism\Imgix\models\VolumeSettings;

'volumes' => [
    'documents' => VolumeSettings::create()->enabled(false),
],
```

For more details, see the [imgix pricing page](https://www.imgix.com/pricing) and the [credit consumption guide](https://www.imgix.com/credit-consumption).

