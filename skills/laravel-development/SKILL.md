---
name: laravel-development
description: Conventions, architecture, Eloquent performance, validation, security and testing practices for modern Laravel (11+) applications on PHP 8.2+. Use this skill whenever the user writes, refactors, debugs or reviews Laravel code — controllers, models, migrations, Form Requests, policies, jobs/queues, events, API resources, Blade/Livewire/Inertia views, artisan commands or Pest/PHPUnit tests — or mentions Eloquent, N+1 queries, artisan, Sanctum, Horizon or composer packages in a Laravel project, even if they don't say "best practices".
---

# Laravel Development

Write Laravel code the way an experienced Laravel team would: idiomatic, secure by default, testable, and boring in the good sense. The framework already solves most problems — prefer its built-in tools over custom abstractions.

## 1. Before writing code: read the project

Laravel projects differ a lot by version and stack. Spend a minute checking before generating anything, because code that ignores the project's conventions creates review friction and subtle bugs.

- `composer.json` → Laravel and PHP versions, installed packages (Sanctum, Livewire, Inertia, Filament, Spatie…).
- `bootstrap/app.php` → Laravel 11+ registers middleware, exceptions and routing here (no `Kernel.php`). Older apps use `app/Http/Kernel.php`.
- Existing code patterns: are there Actions/Services, Repositories, DTOs? Pest or PHPUnit? Follow what exists rather than introducing a new pattern.
- `.env.example` and `config/` for drivers (queue, cache, mail).

If something important is ambiguous (e.g. which auth stack), ask once rather than guessing.

## 2. Architecture defaults

- **Thin controllers.** Controllers validate (via Form Request), authorize (via Policy), delegate, and return a response. Business logic goes into a model method, an Action class (`app/Actions/CreateInvoice.php` with a single `handle()`), or a Job.
- **Form Requests** for any non-trivial validation — they keep rules, messages and `authorize()` together and are reusable.
- **Policies** for authorization; use `$this->authorize()` / `Gate` / `can` middleware, never hand-rolled `if ($user->role === 'admin')` scattered in controllers.
- **API Resources** to shape JSON output — never return raw models from APIs (leaks hidden columns, couples the API to the schema).
- **Route model binding** and resource routes (`Route::resource`, `Route::apiResource`) instead of manual `findOrFail` everywhere.
- **Config, not env.** Call `env()` only inside `config/*.php`; elsewhere use `config()`. After `config:cache`, `env()` returns null outside config files.
- **Enums** (PHP 8.1 backed enums) with model casts for statuses and types instead of magic strings.
- **Queues** for anything slow or external (mail, PDFs, API calls, imports). Make jobs idempotent and set `$tries`, `$backoff`, and `failed()`.

## 3. Eloquent and the database

Most Laravel performance problems are query problems. Think about the SQL each line generates.

- **Kill N+1 queries**: eager load with `with()` / `load()`, and enable `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider::boot()` so they surface in dev.
- Select only what you need on large tables (`select()`, `pluck()`), use `withCount()` / `withSum()` instead of loading relations to count them.
- Large datasets: `chunkById()`, `lazyById()` or `cursor()` — never `all()` on a table that can grow.
- **Mass assignment**: declare `$fillable` explicitly (or `$guarded = []` only with validated input via `$request->validated()`). Never `Model::create($request->all())`.
- **Migrations**: add indexes for foreign keys and columns used in `where`/`orderBy`; use `foreignId()->constrained()->cascadeOnDelete()` where appropriate; never edit a migration that already ran in production — add a new one.
- Wrap multi-step writes in `DB::transaction()`.
- Raw SQL only with bindings (`DB::select('... where id = ?', [$id])`). Never interpolate user input into `whereRaw`, `orderByRaw`, or column names.

For query optimization, indexes and common Eloquent traps, read [references/eloquent-performance.md](references/eloquent-performance.md).

## 4. Security checklist (apply by default)

- Validation on every input; use `$request->validated()` downstream.
- Authorization on every action that touches a resource (Policy or Gate) — including API and Livewire actions.
- Blade: `{{ }}` escapes; use `{!! !!}` only for trusted, sanitized HTML.
- CSRF is on for web routes — don't exclude routes without a reason (webhooks with signature verification are the usual exception).
- File uploads: validate `mimes`/`max`, store on a non-public disk unless meant to be public, never trust the client filename.
- Rate limit login, password reset, and public APIs (`RateLimiter::for`, `throttle` middleware).
- Secrets live in `.env`, never committed. `APP_DEBUG=false` in production.

Before shipping or when asked for a review, walk through [references/security-checklist.md](references/security-checklist.md).

## 5. Testing

- Use the project's framework (Pest if `tests/Pest.php` exists, else PHPUnit). Prefer **feature tests** that hit routes — they catch the most bugs per line.
- Use `RefreshDatabase` (or `LazilyRefreshDatabase`), factories with states, and `actingAs()`.
- Fake external effects: `Mail::fake()`, `Queue::fake()`, `Http::fake()`, `Storage::fake()`, `Notification::fake()`, `Event::fake()`.
- Every bug fix comes with a regression test.
- Test authorization failures (403) and validation failures (422 / session errors), not only the happy path.

See [references/testing.md](references/testing.md) for patterns and examples.

## 6. Code style and tooling

- PSR-12 via **Laravel Pint** (`./vendor/bin/pint`). Static analysis via **Larastan** (PHPStan) if installed.
- Type everything: parameter, return and property types; `declare(strict_types=1);` if the project uses it.
- Naming: controllers singular + `Controller`, models singular, tables plural snake_case, pivot tables alphabetical singular (`post_tag`), route names `resource.action`.
- Generate files with artisan (`php artisan make:model Post -mfsc`, `make:request`, `make:policy --model=Post`) so namespaces and stubs stay consistent.

## 7. Performance and production

- `php artisan optimize` (config/route/view/event caches) on deploy; `composer install --no-dev --optimize-autoloader`.
- Cache expensive reads with `Cache::remember()` and clear on write; use tags only with Redis/Memcached.
- Use Redis for cache/session/queue in production when available; run workers with Supervisor or Horizon; schedule with a single cron `* * * * * php artisan schedule:run`.
- Log with context (`Log::info('Invoice sent', ['invoice_id' => $id])`), and monitor failed jobs.

## 8. How to answer

1. Briefly state assumptions (Laravel version, stack) if you inferred them.
2. Give complete, runnable code with file paths as headings (`app/Http/Requests/StorePostRequest.php`).
3. Include the artisan commands to generate/migrate and the test that proves it works.
4. Call out security or performance implications in one or two lines — don't lecture.
5. When reviewing code, group findings by severity (security → correctness → performance → style) and show the fixed code.
