# Aviary
<!-- covers: The Composer plugin that scopes dependencies into vendor-scoped/; PHP 8.2, PHPUnit 11, no lint config, symbol lists -->

Aviary is the Composer plugin that prefixes a plugin's dependencies into `vendor-scoped/` (PHP-Scoper underneath, derived from WPify Scoper). This directory is canonical; `kestrelcommerce/aviary` is a read-only mirror. Every plugin runs it on `composer install` through `composer-scoped.json`, so a change here reaches every plugin's scoped tree on its next install: run a plugin's `composer install` and test suite before merging.

- PHP floor is 8.2 (`composer.json`). This is not WordPress code, so the plugin mechanics in `plugins/AGENTS.md` do not apply. The root PHP design rules do; `src/Plugin.php` predates them and is not `final`, so match the file you are in.
- No `phpcs.xml`, `phpstan.neon`, or php-cs-fixer config: the pre-commit hook skips this directory. Keep code PHPStan-clean by hand until a config lands.
- Tests: PHPUnit 11 under `tests/`; `require-dev` pulls WordPress and WooCommerce in as fixtures.
- `symbols/` holds the extracted WordPress and WooCommerce symbol lists that scoping excludes; `scripts/extract-symbols.php` regenerates them (`composer extract`, and automatically after `composer update`). Never hand-edit them.
