# WordPress performance & WP-CLI recipes

## Diagnose first

1. **Query Monitor** plugin: slow queries, duplicate queries, hooks, HTTP calls per page.
2. Server timing: TTFB uncached vs cached (`curl -o /dev/null -s -w '%{time_starttransfer}\n' URL`).
3. PageSpeed Insights / Lighthouse for front-end (LCP image, render-blocking CSS/JS, CLS from images without dimensions).
4. Autoloaded options size:
   ```sql
   SELECT SUM(LENGTH(option_value)) FROM wp_options WHERE autoload IN ('yes','on','auto-on','auto');
   ```
   Over ~1 MB is a smell; find the biggest:
   ```bash
   wp db query "SELECT option_name, LENGTH(option_value) len FROM $(wp db prefix)options WHERE autoload IN ('yes','on') ORDER BY len DESC LIMIT 20"
   ```

## Common fixes

| Symptom | Fix |
|---|---|
| Slow uncached pages | Object cache (Redis + drop-in), remove queries from `init`, reduce `meta_query` |
| Many plugins enqueuing everywhere | Conditional dequeue (`wp_dequeue_script` on pages that don't need them) |
| Huge `wp_postmeta` / revisions | `define( 'WP_POST_REVISIONS', 10 );`, clean orphans, `wp post delete $(wp post list --post_type=revision --format=ids) --force` (backup first) |
| Expired transients piling up | `wp transient delete --expired` |
| WP-Cron hammering on traffic | `define( 'DISABLE_WP_CRON', true );` + system cron `*/5 * * * * wp cron event run --due-now --path=/var/www/site` |
| Heavy LCP image | Correct size, `fetchpriority="high"` on hero, no lazy-load above the fold, WebP/AVIF |
| Admin-ajax heartbeat load | Throttle Heartbeat via `heartbeat_settings` filter |

## WP-CLI recipes

```bash
# Backup & restore
wp db export backup-$(date +%F).sql
wp db import backup.sql

# Change domain (serialized-safe). ALWAYS dry run first.
wp search-replace 'https://old.example.com' 'https://new.example.com' --all-tables --dry-run
wp search-replace 'https://old.example.com' 'https://new.example.com' --all-tables --precise
wp cache flush && wp rewrite flush

# Inventory
wp core version; wp plugin list --fields=name,status,version,update; wp theme list
wp user list --role=administrator

# Updates (staging first)
wp core update && wp core update-db
wp plugin update --all --dry-run

# Content export for migrations
wp post list --post_type=post --post_status=publish --fields=ID,post_name,post_date --format=csv > posts.csv
wp export --dir=./export --post_type=post

# Media
wp media regenerate --only-missing --yes

# Maintenance
wp maintenance-mode activate | deactivate
wp transient delete --expired
wp cron event list
```

Run WP-CLI as the web user (`sudo -u www-data wp ...`) to avoid permission issues; on a multisite add `--url=` or `--network`.
