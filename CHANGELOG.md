# Release Notes for Imgix Asset Transformer for Craft CMS

## Unreleased

- Added: `skipTransform` config setting to skip imgix transforms for specific assets. Useful for excluding non-image assets like PDFs.

## 5.0.0-alpha.7 - 2025-09-22

- Fixed: `ratio` for CraftCMS v5.8+

## 5.0.0-alpha.6 - 2025-08-09

- Added: `signingKey` setting to the plugin config

## 5.0.0-alpha.5 - 2025-05-12

- Fix: defer to the default Craft CMS transforms when the filesystem is Temp… again
- Changed: `defaultImgixParams` to `imgixDefaultParams` in the plugin config
- Added: `enabled` setting to the plugin config
- Added: `imgixDomain` setting to the plugin config
- Added: `volumes` setting to the plugin config allowing overriding at a volume level

## 5.0.0-alpha.4 - 2025-05-12

- Fix: defer to the default Craft CMS transforms when the filesystem is Temp

## 5.0.0-alpha.3 - 2025-05-12

- Fix: return a null URL for admin svg image placeholders in card configuration previews

## 5.0.0-alpha.2 - 2025-05-03

- Fix: Added `rasterize-bypass` imgix parameter to prevent rasterization of SVGs where no transform is provided.

## 5.0.0-alpha.1 - 2025-26-02

- Initial release - starting at v5 to match current Craft CMS Version.
