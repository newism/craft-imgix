# Release Notes for Imgix Asset Transformer for Craft CMS

## 5.0.0 - 2026-04-08

### Breaking Changes

- Dropped Craft CMS 4 support. Now requires Craft CMS ^5.6.0 and PHP 8.2+
- Renamed plugin class from `Plugin` to `Imgix` (requires `"class": "Newism\\Imgix\\Imgix"` in composer.json `extra`)
- Config file must return an array (not a `BaseConfig` object). Fluent syntax is still supported for `VolumeSettings` within the array
- Replaced `pathPrefix` (strip-based) path config with `includeFilesystemSubfolder` (build-from-components) approach
- Non-image assets are now skipped by default via `skipTransform` to avoid unnecessary imgix delivery credits

### Added

- Read-only CP settings page showing resolved config values, env var mappings, validation status, and per-volume path debug with sample assets
- `VolumeSettings` model for type-safe per-volume configuration with fluent setters
- `includeFilesystemSubfolder` setting (default `true`) to include the filesystem subfolder (e.g. S3 bucket subfolder) in imgix paths
- Per-volume `subPath` setting for web folder sources serving multiple volumes
- `#[EnvName('DOMAIN')]` attribute for `imgixDomain` so env var maps to `CRAFT_IMGIX_DOMAIN` (not `CRAFT_IMGIX_IMGIX_DOMAIN`)
- `App::envConfig()` integration for automatic env var overrides with `CRAFT_IMGIX_` prefix
- Settings model validation with `defineRules()` and `attributeLabels()`
- Config file auto-copied to `config/newism-imgix.php` on plugin install
- CpScreenSlideout links to edit filesystem and volume from the settings page
- Asset action menu purge uses Craft's built-in `formsubmit` (no inline JS)

### Changed

- Path construction now builds from components (`filesystem subfolder + volume subpath + asset path`) instead of parsing filesystem URLs
- Volume subpath is now always included in imgix paths (fixes missing subpath bug)
- `getSettingsForVolume()` caches results per volume handle for performance
- `assetVolumeCanBePurged()` no longer triggers `generateUrl()` — uses settings check only
- Purge URL query-string stripping consolidated to `purgeUrl()` only
- `performAction()` in `PurgeImgixAsset` always returns `true` with count-based messages
- Translation placeholder used for error messages in `PurgeController`
- Consistent use of `self::getInstance()` instead of static `$plugin` property

### Removed

- `ImageTransformBehavior` and `EVENT_DEFINE_BEHAVIORS`/`EVENT_DEFINE_FIELDS` listeners (redundant since Craft 5.6.0 DI override preserves subclass properties)
- Redundant temp filesystem version check (the `hasUrls` check already handles it)
- Inline JS for asset edit page purge action (replaced with `formsubmit`)

## 5.0.0-alpha.8 - 2025-10-14

- Added: `skipTransform` config setting to skip imgix transforms for specific assets. Useful for excluding non-image assets like PDFs.
- Added: Imgix purge functionality triggered on Asset changes and manual element actions.

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
