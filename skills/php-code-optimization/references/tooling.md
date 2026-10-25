# Code quality tooling

## Install

```bash
composer require --dev phpstan/phpstan rector/rector laravel/pint
# Laravel: Larastan instead of plain PHPStan
composer require --dev larastan/larastan
```

## PHPStan / Larastan — `phpstan.neon`

```neon
includes:
    - vendor/larastan/larastan/extension.neon   # Laravel only
parameters:
    paths:
        - app
    level: 5          # raise progressively; generate a baseline for legacy code
    checkMissingIterableValueType: false
```

```bash
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/phpstan analyse --generate-baseline   # freeze existing errors, fix new ones first
```

(`checkMissingIterableValueType` was removed in PHPStan 2 — drop it if you're on v2.)

## Rector — `rector.php`

```php
<?php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/tests'])
    ->withPhpSets(php83: true)                 // upgrade syntax to PHP 8.3
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true, earlyReturn: true)
    ->withImportNames(removeUnusedImports: true);
```

```bash
./vendor/bin/rector process --dry-run   # review the diff first
./vendor/bin/rector process
```

Laravel-specific rules: `driftingly/rector-laravel` (`LaravelSetList::LARAVEL_110` etc.).
WordPress code: Rector works too; exclude vendor and third-party plugins.

## Pint — `pint.json`

```json
{
  "preset": "laravel",
  "rules": { "declare_strict_types": false, "final_class": false }
}
```

```bash
./vendor/bin/pint            # fix
./vendor/bin/pint --test     # CI check
```

WordPress projects: `phpcs` with `wp-coding-standards/wpcs` (`--standard=WordPress`) and `phpcbf` to auto-fix.

## Composer scripts & CI

```json
"scripts": {
  "lint": "pint --test",
  "analyse": "phpstan analyse",
  "refactor": "rector process --dry-run",
  "test": "php artisan test --parallel",
  "qa": ["@lint", "@analyse", "@test"]
}
```

Run `composer qa` in GitHub Actions on every PR.

## Profiling

- Xdebug profiler (`xdebug.mode=profile`) + KCachegrind/QCachegrind, or SPX (`php-spx`, web UI), or Blackfire.
- Profile realistic requests (production-like data volume); look at inclusive time and call counts.
