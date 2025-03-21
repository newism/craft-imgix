# Imgix Asset Transformer for Craft CMS

Adds imgix powered asset transforms to Craft CMS:

1. Drop-in replacement for Craft CMS native [image transforms](https://craftcms.com/docs/5.x/development/image-transforms.html) and [.srcset()](https://craftcms.com/docs/5.x/development/image-transforms.html#generating-srcset-sizes) method
2. Add additional imgix parameters to image transforms
3. Use imgix for CP thumbnails
4. Allows `.pdf` files to be rasterized and transformed (unlike Craft CMS transforms)

The only thing you'll need to update is your [filesystem Base URL](https://craftcms.com/docs/5.x/reference/element-types/assets.html#filesystems)
to use your imgix domain.

## Requirements

This plugin requires Craft CMS 4.0.0 or later, and PHP 8.0.2 or later.

See Caveats for additional requirements.

## Installation

You can install this plugin with Composer.

```shell
composer require newism/craft-imgix -w && php craft plugin/install newism-imgix
```

Then update your [filesystem Base URL](https://craftcms.com/docs/5.x/reference/element-types/assets.html#filesystems)
to use your imgix domain.

## Configuration

Copy config.php into Crafts config folder and rename it to newism-imgix.php.

```shell
cp vendor/newism/craft-imgix/src/config.php config/newism-imgix.php
```

Update the `imgixDomain` settings key with your imgix domain.

```php

## Usage

This plugin is a drop-in replacement for Craft CMS native [image transforms](https://craftcms.com/docs/5.x/development/image-transforms.html) and [.srcset()](https://craftcms.com/docs/5.x/development/image-transforms.html#generating-srcset-sizes) method.

You shouldn't have to update any of your templates unless you want to add additional imgix parameters.

### Adding additional transform parameters

In addition to the standard Craft CMS transform options:

* `mode`
* `width`
* `height`
* `quality`
* `format`
* `position`
* `fill`

This plugin adds a `ratio` option which allows you to set the aspect ratio of the image. This will automatically
set the `mode` to `crop` and use the image's width and focal point.

```twig
{# Set the transform #}
{% do asset.setTransform({ 
    ratio: 16/9,
}) %}

{# Render the tag #}
{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['1.5x', '2x', '3x']),
  alt: asset.title,
}) }}
```

You can also apply additional imgix parameters to your image transforms by adding them to the transform options under the `imgix` object key.

```twig
{# Set the transform #}
{% do asset.setTransform({ 
    width: 300, 
    height: 300,
    imgix: {
        blur: 20,
    },
}) %}

{# Render the tag #}
{{ tag('img', {
  src: asset.url,
  width: asset.width,
  height: asset.height,
  srcset: asset.getSrcset(['1.5x', '2x', '3x']),
  alt: asset.title,
}) }}
```

### Caveats

#### .svg

imgix does not support rasterizing .svg inputs by default ([blog post](https://www.imgix.com/blog/announcing-support-for-webp-and-svg)).
You will need to contact imgix to enable this feature. This plugin does add transform query parameters to .svg files regardless of this setting.

#### .pdf
.pdf file are served with `rasterize-bypass` set to true when no transform is provided. This will serve the original .pdf file.

> The `rasterize-bypass` parameter allows users to bypass all rendering parameters, including default parameters, and serve the original image file. This is particularly useful for file formats that support vector graphics and do not require rasterization. [Source](https://docs.imgix.com/en-US/apis/rendering/format/rasterize-bypass)

#### `imgix` object key values

In Craft CMS < v5.6.0 the additional `imgix` object key values are lost
when calling [.srcset()](https://craftcms.com/docs/5.x/development/image-transforms.html#generating-srcset-sizes).
