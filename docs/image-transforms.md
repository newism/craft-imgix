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