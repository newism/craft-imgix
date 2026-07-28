---
outline: deep
---

# Image Transforms

This plugin is a drop-in replacement for Craft CMS native [image transforms](https://craftcms.com/docs/5.x/development/image-transforms.html). You shouldn't need to update your templates unless you want to use additional Imgix parameters.

Here's some best practices for using the plugin in your templates.

## Standard Transforms

All standard Craft CMS transform options are supported:

* `mode` — `crop`, `fit`, `letterbox`, or `stretch`
* `width`
* `height`
* `quality`
* `format`
* `position`
* `fill`

```twig
{% do asset.setTransform({ width: 800, height: 600, mode: 'crop' }) %}

{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  alt: asset.title,
}) }}
```

### Transform Mode Mapping

Craft CMS transform modes are mapped to [Imgix fit parameters](https://docs.imgix.com/en-US/apis/rendering/size/fit):

| Craft Mode  | Imgix Fit          | Description                                                                                                                                                                                 |
|-------------|--------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `crop`      | `crop`             | Crops to exact dimensions, respects focal point                                                                                                                                             |
| `fit`       | `clip`             | Resizes to fit within dimensions, maintains aspect ratio                                                                                                                                    |
| `letterbox` | `fill` / `fillmax` | Fills to exact dimensions with background. Uses `fill` when upscaling is allowed, `fillmax` otherwise. Respects the per-transform `upscale` property and the `upscaleImages` general config |
| `stretch`   | `scale`            | Stretches to exact dimensions                                                                                                                                                               |

## Ratio-Based Transforms

This plugin adds a `ratio` option which sets the aspect ratio of the image. When only a ratio is provided, the asset's width is used as the base dimension.

```twig
{# Crop to 16:9 using the asset's full width #}
{% do asset.setTransform({ ratio: 16/9 }) %}

{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['1.5x', '2x', '3x']),
  alt: asset.title,
}) }}
```

You can combine `ratio` with `width` or `height` to control the output size:

```twig
{# 400px wide at 16:9 #}
{% do asset.setTransform({ width: 400, ratio: 16/9 }) %}

{# 300px tall at 4:3 #}
{% do asset.setTransform({ height: 300, ratio: 4/3 }) %}
```

## Additional Imgix Parameters

Apply any [Imgix rendering parameter](https://docs.imgix.com/en-US/apis/rendering) via the `imgix` object key:

```twig
{# Blur effect #}
{% do asset.setTransform({
    width: 300,
    height: 300,
    imgix: {
        blur: 20,
    },
}) %}
```
```twig
{# Monochrome filter #}
{% do asset.setTransform({
    width: 800,
    imgix: {
        mono: '44768B',
    },
}) %}
```
```twig
{# Text overlay #}
{% do asset.setTransform({
    width: 800,
    imgix: {
        'txt': 'Hello World',
        'txt-size': 48,
        'txt-color': 'ffffff',
        'txt-align': 'center,middle',
    },
}) %}
```

## Serving the Original File

Imgix optimises images by default — the `imgixDefaultParams` from your config file (typically `auto=format,compress`) are applied to every URL. When you need the untouched original, set `renderOriginal`:

```twig
{% do asset.setTransform({ renderOriginal: true }) %}

{{ asset.url }}
{# https://your-source.imgix.net/path/to/image.jpg?ixlib=php-4.1.0 #}
```

The URL is still served from your Imgix domain and CDN, but with no rendering parameters at all. Config defaults are skipped, and `width`, `height`, `quality`, `format`, `mode`, `position` and `ratio` are all ignored.

`dl` is the one parameter that still applies, so you can force a download of the original:

```twig
{% do asset.setTransform({
    renderOriginal: true,
    imgix: { dl: asset.filename },
}) %}

<a href="{{ asset.url }}" download>Download original</a>
{# https://your-source.imgix.net/path/to/image.jpg?dl=image.jpg&ixlib=php-4.1.0 #}
```

The `ixlib` parameter comes from the Imgix SDK and identifies the client library. It is not a rendering parameter and has no effect on the file returned. Set [`includeLibraryParam`](./configuration.md) to `false` if you want a completely bare URL.

Every other key in the `imgix` object is dropped, and this is deliberate. **Any** rendering parameter sends the image through Imgix's processing pipeline and re-encodes it — even one that asks for the size the image already is. Requesting a 2177px-wide PNG at its native 2177px still returns a different file, about 5% smaller. `w`, `q=100` and `fit=clip` on their own all produce byte-identical re-encoded output.

Only a URL with no rendering parameters returns the source file unchanged. `dl` is safe because it sets a response header rather than entering the pipeline, which is why it's the one parameter allowed through — as is `ixlib`, which Imgix reads for analytics and ignores when serving.

::: tip
Don't set `width` or `height` alongside `renderOriginal`. The URL ignores them, but `{{ asset.width }}` and `{{ asset.height }}` will still report them, so your markup won't match the file being served.
:::

::: warning
`revAssetUrls` cache-busting params are not added to `renderOriginal` URLs, since the point is a URL free of rendering parameters. Replaced assets are still purged from the Imgix cache if you have [cache purging](./cache-purging.md) configured.
:::

### renderOriginal vs skipImgix

These look similar but do different things:

| | URL host | Query params | Use when |
|---|---|---|---|
| `renderOriginal: true` | Imgix domain | None, except `dl` and `ixlib` | You want the original file, delivered over the Imgix CDN |
| [`skipImgix`](./configuration.md) | Your filesystem | None | You want Imgix out of the picture entirely, e.g. to save delivery credits |

For a one-off filesystem URL without configuring `skipImgix`, use the `filesystemUrl()` method:

```twig
{{ imgix.filesystemUrl(asset) }}
{# https://your-bucket.s3.amazonaws.com/path/to/image.jpg #}
```

## srcset Generation

The plugin works with Craft's built-in [srcset generation](https://craftcms.com/docs/5.x/development/image-transforms.html#generating-srcset-sizes). Each srcset variant generates a separate Imgix URL with the appropriate dimensions.

### Pixel density descriptors (`1.5x`, `2x`, `3x`)

Best for fixed-size images (e.g. thumbnails, logos). The browser picks the right density for the device.

```twig
{% do asset.setTransform({ width: 400, height: 300 }) %}

{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['1.5x', '2x', '3x']),
  alt: asset.title,
}) }}
```

### Width descriptors (`300w`, `600w`, `900w`)

Best for responsive images that scale with the viewport. Pair with `sizes` so the browser knows how wide the image will be rendered.

```twig
{% do asset.setTransform({ width: 900, mode: 'crop' }) %}

{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['300w', '600w', '900w']),
  sizes: '(max-width: 600px) 100vw, 900px',
  alt: asset.title,
}) }}
```

### With ratio and srcset

Combine `ratio` with srcset for responsive aspect-ratio-locked images.

```twig
{% do asset.setTransform({ width: 800, ratio: 16/9 }) %}

{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['400w', '800w', '1200w', '1600w']),
  sizes: '(max-width: 800px) 100vw, 800px',
  alt: asset.title,
}) }}
```