# WordPress data model → Laravel mapping

Replace `wp_` by the actual table prefix (`$table_prefix` in `wp-config.php`).

## Core tables

| WordPress | Holds | Typical Laravel target |
|---|---|---|
| `wp_posts` | posts, pages, CPTs, attachments (`post_type='attachment'`), revisions, nav items | `posts`, `pages`, one table per CPT, `media` |
| `wp_postmeta` | custom fields, ACF values, SEO plugin data, featured image (`_thumbnail_id`) | columns on the target table, or a JSON column for long-tail fields |
| `wp_terms` + `wp_term_taxonomy` | terms and their taxonomy (`category`, `post_tag`, custom) | `categories`, `tags` (or a generic `terms` table) |
| `wp_term_relationships` | object ↔ term_taxonomy | pivot tables / polymorphic `taggables` |
| `wp_termmeta` | term custom fields, SEO for archives | columns on `categories`/`tags` |
| `wp_users` + `wp_usermeta` | users, roles (`wp_capabilities` serialized) | `users` + roles (e.g. spatie/laravel-permission) |
| `wp_comments` | comments | `comments` (or drop) |
| `wp_options` | site settings, widgets, theme mods | `config`/settings table — usually re-entered manually |

## Inventory SQL

```sql
-- Content counts by type and status
SELECT post_type, post_status, COUNT(*) FROM wp_posts GROUP BY post_type, post_status ORDER BY 3 DESC;

-- Meta keys in use (find ACF / SEO fields)
SELECT meta_key, COUNT(*) c FROM wp_postmeta GROUP BY meta_key ORDER BY c DESC LIMIT 100;

-- Taxonomies
SELECT taxonomy, COUNT(*) FROM wp_term_taxonomy GROUP BY taxonomy;

-- Shortcodes used in content
SELECT DISTINCT REGEXP_SUBSTR(post_content, '\\[[a-zA-Z0-9_-]+') sc, COUNT(*)
FROM wp_posts WHERE post_status='publish' AND post_content REGEXP '\\[[a-zA-Z0-9_-]+'
GROUP BY sc ORDER BY 2 DESC;   -- MySQL 8+

-- Old slugs (→ redirects)
SELECT p.ID, p.post_name AS current_slug, m.meta_value AS old_slug
FROM wp_posts p JOIN wp_postmeta m ON m.post_id = p.ID AND m.meta_key = '_wp_old_slug';
```

## Useful meta keys

| Meta key | Meaning |
|---|---|
| `_thumbnail_id` | featured image attachment ID |
| `_wp_attached_file` | attachment path relative to uploads |
| `_wp_attachment_image_alt` | image alt text |
| `_wp_attachment_metadata` | serialized sizes (PHP `unserialize`, trusted source only) |
| `_wp_page_template` | page template |
| `_wp_old_slug` | previous slugs → 301s |
| `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_canonical`, `_yoast_wpseo_meta-robots-noindex` | Yoast SEO |
| `rank_math_title`, `rank_math_description`, `rank_math_canonical_url`, `rank_math_robots` | Rank Math |
| `field_name` + `_field_name` (= `field_xxx` key) | ACF value + field reference |

Yoast titles may contain variables (`%%title%% %%sep%% %%sitename%%`) — resolve them during import.

## Permalinks

`wp option get permalink_structure` gives the pattern (e.g. `/%postname%/`, `/%year%/%monthnum%/%postname%/`, `/%category%/%postname%/`). Rebuild each published URL from it, or simply crawl the live site / export the sitemap and store `old_url` per record.

Hierarchical pages: full path = parent slugs + slug (`/services/seo/`). Compute recursively from `post_parent`.

## Passwords

- Legacy WP hashes start with `$P$` (phpass). WordPress 6.8+ stores new hashes as `$wp$2y$...` (bcrypt over a pre-hashed password).
- Option A (simplest, safest): import users without passwords, send a password-reset email at launch.
- Option B (seamless): keep the WP hash in a `legacy_password` column; on login, if Laravel's check fails and a legacy hash exists, verify it with a phpass implementation (e.g. a maintained `hautelook/phpass`-style package) or WP's algorithm, then `Hash::make()` the plain password, store it, and null the legacy hash. Implement it in a custom login action, test both hash formats.

## Content transformation

- Run `wpautop`-equivalent logic if content relies on WP's automatic `<p>` insertion (classic editor content often has no `<p>` tags).
- Gutenberg content contains `<!-- wp:paragraph -->` comments — strip them or parse blocks if you want structure.
- Replace absolute links `https://old-domain/wp-content/uploads/...` with the new media URL; replace internal post links with new routes.
- Sanitize with an allowlist (e.g. `mews/purifier` / HTML Purifier) to drop builder-specific markup and inline scripts.
