# Runtime & server tuning for Laravel

## Production checklist

```bash
composer install --no-dev --prefer-dist --classmap-authoritative
php artisan optimize            # config:cache, route:cache, view:cache, event:cache
php artisan about               # verify caches = CACHED, debug = OFF
```

`.env` production: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`. Remove Telescope/Debugbar from production or restrict them to sampled recording.

## OPcache (php.ini)

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=50000
opcache.validate_timestamps=0      ; with symlink deploys + FPM reload; else 1 with revalidate_freq=2
opcache.save_comments=1            ; required by some packages (attributes/annotations)
opcache.jit_buffer_size=64M        ; JIT helps CPU-bound code, little for I/O-bound web apps
opcache.jit=tracing
realpath_cache_size=4096K
realpath_cache_ttl=600
```

Check hit rate with `opcache_get_status()` — aim for > 99 % and no "oom restarts".

## PHP-FPM sizing

```
pm = dynamic          ; or static on dedicated servers
pm.max_children = (RAM for PHP) / (avg worker RSS)   e.g. 3 GB / 60 MB ≈ 50
pm.start_servers = 25%; pm.min_spare_servers = 15%; pm.max_spare_servers = 35%
pm.max_requests = 500 ; recycles leaky workers
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm/slow.log
```

Measure worker RSS: `ps -ylC php-fpm8.3 --sort:rss`. Too few children → 502/504 under load; too many → swapping.

## Nginx

- `gzip on;` (or brotli), long cache for `/build/` hashed assets: `expires 1y; add_header Cache-Control "public, immutable";`
- `fastcgi_buffers` sized for large responses; `keepalive_timeout 65;`
- HTTP/2 or HTTP/3 enabled.

## Redis

- Separate DBs/prefixes for cache vs queue vs session; `maxmemory-policy allkeys-lru` for a cache-only instance (never for queues).
- Use `phpredis` extension rather than predis for speed.

## Queues

- Horizon with auto-balancing; separate queues for slow vs fast jobs (`high`, `default`, `imports`).
- `retry_after` > longest job `$timeout`.
- Supervisor `numprocs` based on CPU and job type (I/O-bound jobs can have more workers).

## Octane

Use when the app is already efficient and request bootstrapping dominates. Watch for: singletons holding request state, static caches growing, `config()`/`request()` captured in constructors of singletons, file uploads in memory. Run `--max-requests` to recycle workers. Test thoroughly under load (k6, wrk, `ab`).

## Load testing

```bash
# k6 quick script
k6 run -u 50 -d 60s script.js
# or
wrk -t4 -c100 -d60s https://staging.example.com/api/products
```

Compare p95 latency and error rate before/after each change; change one thing at a time.
