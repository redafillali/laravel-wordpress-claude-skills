# WordPress database optimization

Always back up first: `wp db export before-cleanup.sql`. Replace `wp_` with the real prefix.

## 1. Autoloaded options

Every page load reads all autoloaded options.

```sql
SELECT ROUND(SUM(LENGTH(option_value))/1024) AS autoload_kb
FROM wp_options WHERE autoload IN ('yes','on','auto-on','auto');

SELECT option_name, ROUND(LENGTH(option_value)/1024) kb
FROM wp_options WHERE autoload IN ('yes','on','auto-on','auto')
ORDER BY LENGTH(option_value) DESC LIMIT 25;
```

- Target: under ~800 KB. Leftovers from uninstalled plugins → set `autoload` off or delete (after checking the plugin is really gone).
- WP 6.6+: `wp option set-autoload <name> off` (or `UPDATE wp_options SET autoload='off' WHERE option_name='...'`).
- Index (often missing on old installs): `ALTER TABLE wp_options ADD INDEX autoload (autoload);` (WP 6.x core already has it on new installs).

## 2. Transients

```bash
wp transient delete --expired
wp transient delete --all     # safe but caches will rebuild
```

With a persistent object cache (Redis), transients live in Redis, not the DB.

## 3. Revisions, drafts, trash, orphans

```sql
-- Counts
SELECT post_type, COUNT(*) FROM wp_posts GROUP BY post_type;

-- Orphaned postmeta
DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts p ON p.ID = pm.post_id WHERE p.ID IS NULL;

-- Orphaned term relationships
DELETE tr FROM wp_term_relationships tr LEFT JOIN wp_posts p ON p.ID = tr.object_id WHERE p.ID IS NULL;
```

```bash
wp post delete $(wp post list --post_type=revision --format=ids) --force   # in batches on big sites
```

Limit future revisions in `wp-config.php`: `define( 'WP_POST_REVISIONS', 10 );`

WooCommerce: also clean `wp_woocommerce_sessions`, expired `wc_` transients, Action Scheduler logs (`wp_actionscheduler_logs`, completed actions older than 30 days).

## 4. wp_postmeta performance

Core indexes: `post_id`, `meta_key(191)`. `meta_query` filtering on `meta_value` scans rows.

```sql
-- Helps meta_query on key + value (common with ACF / WooCommerce filters)
ALTER TABLE wp_postmeta ADD INDEX meta_key_value (meta_key(191), meta_value(32));
```

(The "Index WP MySQL For Speed" plugin automates better keys for options/postmeta/usermeta/termmeta.)

Better design for heavy filtering: taxonomy terms instead of meta, or a custom table with typed, indexed columns.

## 5. Query hygiene in themes/plugins

- `WP_Query`: `no_found_rows => true` (no SQL_CALC_FOUND_ROWS) when not paginating, `fields => 'ids'`, explicit `posts_per_page`, avoid `orderby => 'rand'` and `meta_value` ordering on big sets.
- Avoid `posts_per_page => -1` on large post types.
- Use Query Monitor to find duplicate queries and the component responsible.

## 6. Table engine & charset

```sql
SELECT table_name, engine, table_collation FROM information_schema.TABLES WHERE table_schema = DATABASE();
```

Convert MyISAM leftovers to InnoDB (`ALTER TABLE t ENGINE=InnoDB;`) and use `utf8mb4_unicode_ci` / `utf8mb4_unicode_520_ci` consistently (mismatched collations break index use in joins).
