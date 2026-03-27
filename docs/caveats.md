---
outline: deep
---

# Caveats

## SVG Files

Imgix does not support rasterizing `.svg` inputs by default. You will need to [contact Imgix](https://imgix.com/contact) to enable this feature for your source. The plugin still adds transform query parameters to `.svg` files regardless of this setting.

See the [Imgix blog post on SVG support](https://www.imgix.com/blog/announcing-support-for-webp-and-svg) for more details.

## PDF Files

By default, PDFs are skipped by `skipTransform` and served from your filesystem directly.

If you override `skipTransform` to include PDFs, the plugin handles them as follows:

- **No transform applied**: served with [`rasterize-bypass`](https://docs.imgix.com/en-US/apis/rendering/format/rasterize-bypass) set to `true` (original PDF unchanged via imgix CDN)
- **Transform applied**: Imgix rasterizes the PDF and applies the transform, allowing image thumbnails of PDF pages

```twig
{# Serve original PDF (default: served from filesystem, not Imgix) #}
<a href="{{ pdfAsset.url }}">Download PDF</a>

{# To generate a thumbnail, skipTransform must allow PDFs through #}
{% do pdfAsset.setTransform({ width: 300 }) %}
<img src="{{ pdfAsset.url }}" alt="PDF preview">
```
