---
name: wordpress-development
description: Secure, standards-compliant WordPress theme and plugin development (WordPress 6.x, PHP 8.x) — hooks, nonces, capability checks, sanitization and escaping, $wpdb prepared queries, custom post types, REST API endpoints, Gutenberg blocks, WP-CLI, WooCommerce customizations and performance. Use this skill whenever the user writes, debugs or audits WordPress code (functions.php, a child theme, a plugin, a shortcode, an AJAX handler, a block, a WP_Query, ACF fields) or asks to harden, speed up or clean up a WordPress site, even if they don't mention security or standards.
---

# WordPress Development

Write WordPress code that would pass a WordPress.org plugin review: secure, prefixed, hook-driven, and compatible with other plugins. WordPress runs on a shared global namespace with thousands of third-party plugins — most bugs come from collisions, missing escaping, or doing work at the wrong hook.

## 1. Read the context first

- Theme or plugin? Child theme? Block theme (`theme.json`, `templates/*.html`) or classic theme (`*.php` templates)?
- Is the code in `functions.php` of a theme the client may change? Site-wide functionality belongs in a **plugin** (or `mu-plugins/`), not in the theme — otherwise it disappears on theme switch.
- Existing prefix/namespace, text domain, coding style (WPCS), build tooling (`@wordpress/scripts`), page builder (Elementor, Divi) and key plugins (WooCommerce, ACF, Yoast/Rank Math, WPML/Polylang).
- WordPress and PHP versions available on the host.

## 2. Non-negotiable security rules

The WordPress mantra: **validate/sanitize early, escape late, always check intent and permission.**

| Concern | Do |
|---|---|
| Intent (CSRF) | `wp_nonce_field()` / `wp_create_nonce()` + `check_admin_referer()` / `check_ajax_referer()` / `wp_verify_nonce()` |
| Permission | `current_user_can( 'edit_post', $post_id )` before any write or private read — nonces are not authorization |
| Input | `sanitize_text_field()`, `sanitize_email()`, `absint()`, `sanitize_key()`, `esc_url_raw()`, `wp_kses_post()`; always `wp_unslash()` superglobals first |
| Output | `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, `wp_kses_post()`, `esc_html__()` — at the moment of echo, even for "trusted" data |
| SQL | `$wpdb->prepare()` with `%s %d %f %i` placeholders; prefer `WP_Query`/`get_posts`/meta APIs over raw SQL |
| Files | `wp_handle_upload()`, check `wp_check_filetype_and_ext()`, never `include` a user-supplied path |
| Direct access | `defined( 'ABSPATH' ) || exit;` at the top of every PHP file |
| REST | every route needs a real `permission_callback` (use `__return_true` only for truly public read endpoints) |

Full patterns and an audit checklist: [references/security.md](references/security.md).

## 3. Structure and conventions

- **Prefix everything** global (functions, options, meta keys, hooks, CPT slugs, script handles) or use a PHP namespace — e.g. `acme_` / `Acme\Plugin`.
- Use the right hook: register CPTs/taxonomies on `init`, enqueue on `wp_enqueue_scripts` / `admin_enqueue_scripts`, REST routes on `rest_api_init`, admin pages on `admin_menu`. Avoid running queries on `plugins_loaded` or at file load.
- **Enqueue** assets with `wp_enqueue_script/style` (versioned, with dependencies, `strategy => 'defer'` where suitable); never hardcode `<script>` tags. Pass data with `wp_localize_script()` or `wp_add_inline_script()`.
- **Child themes** for theme customizations; never edit parent theme or plugin files (lost on update).
- Templates: use `get_template_part()`, template hierarchy and filters (`the_content`, `body_class`) before overriding whole files. WooCommerce: prefer hooks over template overrides; if you override, track the template version.
- i18n: wrap strings in `__()`, `_e()`, `esc_html__()` with the project text domain.
- Activation/deactivation hooks for setup (flush rewrite rules once on activation after registering CPTs); `uninstall.php` cleans options/tables.
- Store settings with the Settings API (`register_setting` with a `sanitize_callback`) or a single array option, `autoload` false for large options.

A minimal, secure plugin skeleton is in [assets/plugin-boilerplate.php](assets/plugin-boilerplate.php).

## 4. Data and queries

- `WP_Query` with explicit `posts_per_page` (never `-1` on large sites), `no_found_rows => true` when not paginating, `fields => 'ids'` when you only need IDs, `update_post_meta_cache/update_post_term_cache => false` when unused.
- Avoid `meta_query` on unindexed meta for large datasets — consider a taxonomy or a custom table.
- Always `wp_reset_postdata()` after a secondary loop.
- Cache expensive results with transients (`get_transient`/`set_transient`) or the object cache (`wp_cache_get/set`), and invalidate on `save_post`.
- Custom tables: create with `dbDelta()` on activation, version the schema in an option.

## 5. Blocks, REST and AJAX

- New UI → prefer **blocks** (`block.json` + `register_block_type()`), dynamic blocks with `render_callback`/`render.php` for server data. Scaffold with `npx @wordpress/create-block`.
- Prefer **REST API** endpoints (`register_rest_route`) over `admin-ajax.php` for new code; validate `args` with `sanitize_callback` and `validate_callback`.
- For admin-ajax: `wp_ajax_{action}` (logged in) and `wp_ajax_nopriv_{action}` (public), `check_ajax_referer()`, capability check, respond with `wp_send_json_success()/error()`.

## 6. Performance

- Load assets only where needed (conditional enqueue by template/post type/shortcode presence).
- Images: `wp_get_attachment_image()` (srcset, lazy loading), proper `add_image_size()`.
- Remove heavy queries from `init`/every page load; schedule background work with WP-Cron (`wp_schedule_event`) or Action Scheduler — and on real servers, disable pseudo-cron (`DISABLE_WP_CRON`) and use a system cron.
- Clean autoloaded options; audit with `wp option list --autoload=on --format=table` (WP-CLI) or Query Monitor.
- Object cache (Redis) + page cache + CDN for traffic sites.

More in [references/performance-and-wpcli.md](references/performance-and-wpcli.md).

## 7. Tooling

- **WP-CLI** for anything repeatable: `wp search-replace` (with `--dry-run` first, handles serialized data), `wp db export`, `wp plugin update --all`, `wp cron event list`, `wp transient delete --expired`.
- **PHPCS + WordPress Coding Standards** (`WordPress-Extra`), **Query Monitor** for debugging, `WP_DEBUG` + `WP_DEBUG_LOG` in dev only.
- Local dev: wp-env, LocalWP, DDEV.

## 8. How to answer

1. Say where the code goes (plugin file, child theme `functions.php`, mu-plugin) and why.
2. Give complete code with nonce, capability, sanitize and escape in place — not "add security later".
3. Prefix names and use the project's text domain.
4. Mention any one-time action (flush permalinks, activate plugin, run a WP-CLI command).
5. When auditing, list findings by severity with the vulnerable line and the fixed version.
