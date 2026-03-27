---
outline: deep
---

# How it works

Whenever an asset url or transform is called in a template, the plugin intercepts the URL generation and decides whether to route the asset through Imgix or serve it directly from the filesystem.

**For image assets**, the plugin replaces the default filesystem URL with an Imgix URL:

1. Builds the Imgix path from components: filesystem subfolder + volume subpath + asset folder + filename
2. Constructs an Imgix URL using the configured `imgixDomain`
3. When a [Craft image transform](https://craftcms.com/docs/5.x/development/image-transforms.html) is applied, converts the transform parameters (width, height, mode, quality, etc.) to Imgix query parameters
4. Passes Craft's focal point coordinates as `fp-x` and `fp-y`
5. Signs the URL if a `signingKey` is configured

**For non-image assets** (PDFs, documents, videos, etc.), the plugin skips Imgix by default and the asset is served directly from the filesystem URL (e.g. S3/CloudFront). This avoids unnecessary [Imgix delivery credits](./minimize-imgix-costs.md).

No changes to your filesystem Base URL are required. The `imgixDomain` config setting controls where image URLs point.