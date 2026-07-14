# Emogrifier Scoped Dependency Build

This directory is the source of truth for rebuilding the scoped `symfony/css-selector` bundle used by:

- `app/Services/Libs/Emogrifier/scoped-vendor/symfony/css-selector`

## Why this exists

A direct vendor snapshot can drift to PHP-8-only packages. This build workspace pins a PHP-7.4-compatible dependency set and regenerates the scoped bundle deterministically.

## Rebuild command

Run from repository root:

```bash
bash app/Services/Libs/Emogrifier/build/rebuild_scoped_css_selector.sh
```

The rebuild script will:

1. Install locked dependencies from `build/composer.lock`.
2. Copy `symfony/css-selector` into `scoped-vendor/symfony/css-selector`.
3. Prefix namespaces to `FluentEmogrifier\\Vendor\\Symfony\\Component\\CssSelector`.
4. Replace `str_contains()` usage with a local compatibility helper.
5. Update scoped composer metadata (`installed.json`, `installed.php`, `platform_check.php`).

## Updating css-selector

1. Edit `build/composer.json` version constraints.
2. Run:

```bash
composer update symfony/css-selector --working-dir app/Services/Libs/Emogrifier/build
bash app/Services/Libs/Emogrifier/build/rebuild_scoped_css_selector.sh
```

3. Verify with PHP 7.4 and 8.x before release.
