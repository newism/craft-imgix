<p align="center"><img src="src/icon.svg" width="100" height="100" alt="Imgix Asset Transformer icon"></p>
<h1 align="center">Imgix Asset Transformer for Craft CMS</h1>

Imgix-powered asset transforms for Craft CMS. A drop-in replacement for native image transforms with per-volume configuration, cache purging, and ratio-based transforms.

## Features

- **Drop-in replacement** — replaces native image transforms, `.srcset()` methods, and Control Panel thumbnails
- **Imgix rendering parameters** — add any Imgix parameter via the `imgix` transform key
- **Ratio-based transforms** — aspect-ratio-locked images with the `ratio` option
- **Automatic cache purging** — purges Imgix on asset save, move, replace, and delete
- **Per-volume configuration** — different Imgix sources per volume for multi-domain setups
- **URL signing** — secure image delivery via `signingKey`
- **Non-image assets skipped** — PDFs, documents, etc. are skipped by default to reduce Imgix costs
- **PDF rasterization** — supported when enabled via `skipTransform`
- **Placeholder SVG** — transparent SVG data URIs for CLS prevention
- **Configuration debugging** — built-in settings page to verify your setup

## Requirements

- Craft CMS 5.6+
- PHP 8.2+

## Installation

```bash
composer require newism/craft-imgix
php craft plugin/install newism-imgix
```

## Documentation

Full documentation is available at https://plugins.newism.com.au/imgix-asset-transformer.

## Support

For support, please [open an issue on GitHub](https://github.com/newism/craft-imgix/issues).
