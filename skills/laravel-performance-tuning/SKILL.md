---
name: laravel-performance-tuning
description: Diagnose and fix slow Laravel applications end to end — measure first (Telescope, Pulse, Debugbar, slow query log, APM), then fix the real bottleneck — N+1 and heavy queries, missing indexes, caching strategy, queues for slow work, config/route/view caching, OPcache and JIT, PHP-FPM sizing, Redis, Octane, HTTP response caching and asset delivery. Use this skill whenever the user says a Laravel page, API endpoint, job, import, admin panel or dashboard is slow, times out, uses too much memory or CPU, hits 500/504 under load, or asks how to make a Laravel app faster or scale it — even if they just paste a slow controller.
---

# Laravel Performance Tuning

Performance work fails when it starts with guesses. The rule here: **measure → find the single biggest cost → fix it → measure again.** A page doing 400 queries won't be saved by Octane; a 3 s external API call won't be saved by indexes.

## 1. Establish the baseline (always first)

Ask for or collect, per slow endpoint:
- Response time (p50/p95), number of DB queries and their total time, memory peak, external HTTP calls.
- Where it runs: local vs production, traffic level, server specs, PHP version, cache/queue/session drivers.

Tools, cheapest first:
| Tool | Gives |
|---|---|
| `DB::enableQueryLog()` / `DB::listen()` / `Model::preventLazyLoading()` | query count, N+1 detection |
| Laravel Debugbar (dev) | queries, timeline, memory per request |
| Telescope (dev/staging) | requests, queries, jobs, cache hits |
| Pulse (production) | slow requests, slow queries, slow jobs, usage |
| MySQL slow query log + `EXPLAIN` | the actual heavy SQL |
| `php artisan about`, `opcache_get_status()` | config/caches/OPcache state |
| APM (Sentry performance, New Relic, Blackfire, Xdebug profiler) | function-level hot spots |

Quick in-code timer when nothing is installed:

```php
$t = microtime(true); DB::enableQueryLog();
// ... code under test
logger()->info('perf', ['ms' => round((microtime(true) - $t) * 1000), 'queries' => count(DB::getQueryLog()), 'mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1)]);
```

## 2. Fix in order of typical impact

1. **Database** (most common): N+1 → `with()`; aggregates → `withCount/withSum`; unindexed filters/sorts → composite indexes; `SELECT *` on wide tables → select columns; huge offsets → `cursorPaginate()`; loops of single updates → bulk `update()`/`upsert()`. Deep dive: the `mysql-optimization` skill if available, and [references/database-and-eloquent.md](references/database-and-eloquent.md).
2. **Work that doesn't belong in the request**: emails, PDFs, image processing, webhooks, imports, third-party APIs → queued jobs (Redis queue + Horizon). Return 202/redirect immediately and notify on completion.
3. **Repeated computation** → cache with clear invalidation (see §3).
4. **Framework bootstrapping** in production: `php artisan optimize` (config, routes, views, events), `composer install --no-dev --classmap-authoritative`, no debug tools enabled, `APP_DEBUG=false`, log level ≥ `warning`/`error` on hot paths.
5. **Runtime**: PHP 8.3+, OPcache on and sized, PHP-FPM workers matched to RAM, Redis for cache/session/queue instead of `file`/`database`.
6. **Concurrency model**: Octane (Swoole/FrankenPHP/RoadRunner) only after 1–5 — it multiplies throughput of a healthy app but brings state-leak risks.
7. **Delivery**: HTTP caching headers, CDN for assets, Vite build with hashed filenames, compressed responses (gzip/brotli at Nginx), image optimization.

Server/runtime settings: [references/runtime-and-server.md](references/runtime-and-server.md).

## 3. Caching strategy

- Cache **results of expensive reads**, keyed by everything that changes the result: `Cache::remember("dashboard:{$tenantId}:{$date}", now()->addMinutes(10), fn () => ...)`.
- **Invalidate on write** (model observers or events) rather than relying only on TTL when staleness is visible to users.
- Prevent stampedes on hot keys: `Cache::flexible()` (stale-while-revalidate, Laravel 11.23+) or `Cache::lock()` around regeneration.
- `Cache::memo()` / request-level memoization (`once()` helper) for values computed several times per request.
- Don't cache per-user pages in shared caches; never cache responses with CSRF tokens or personal data in a public CDN.
- Full-page caching for anonymous traffic (e.g. `spatie/laravel-responsecache`) is the biggest win on content sites.

## 4. Jobs, imports and memory

- Process big datasets with `lazyById()` / `chunkById()`; never load everything into a collection.
- Disable query log and model events during imports when safe; batch inserts (`insert()`/`upsert()` 500–1000 rows).
- Long jobs: set `$timeout` below the queue's `retry_after`, use `Bus::batch()` to parallelize chunks, make jobs idempotent.
- Memory leak in workers → `--max-jobs` / `--max-time` on `queue:work`, avoid static accumulations.

## 5. APIs and front-end

- Paginate everything; use API Resources with `whenLoaded` to avoid lazy loads; return only needed fields.
- ETag/`Cache-Control` for cacheable GET endpoints.
- Livewire: `wire:model.blur`/`.live.debounce`, lazy components (`#[Lazy]`), computed properties (`#[Computed]` with `persist`), avoid large public properties.
- Inertia: partial reloads (`only`), lazy/deferred props.

## 6. How to answer

1. If no measurement was given, ask for it (or give the 3-line snippet above) — but still list the likely causes visible in the code.
2. Rank fixes by expected gain vs effort; show before/after code.
3. Quantify when you can ("1 + N → 2 queries", "~400 ms → ~40 ms if the index is used").
4. Give the verification step (what number should change, how to measure it).
5. Mention trade-offs (cache staleness, Octane state, memory).
