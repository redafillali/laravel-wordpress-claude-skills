---
name: wp-to-laravel-migration
description: Step-by-step method to migrate a WordPress site to a custom Laravel application without losing content, media, URLs or search rankings — inventory, data mapping from wp_posts/wp_postmeta/terms/users, import commands, URL preservation and 301 redirects, SEO parity, admin replacement and cutover. Use this skill whenever the user wants to move, rebuild, replatform or "convert" a WordPress (or WooCommerce) site to Laravel, import WordPress content or users into a Laravel database, or plan the redirects and launch of such a migration — even if they only ask about one piece (e.g. "how do I import my WP posts into Laravel").
---

# WordPress → Laravel Migration

A migration succeeds when visitors, editors and Google barely notice the switch: same content, same (or properly redirected) URLs, same or better SEO signals, and an admin the editors can actually use. Most failed migrations lose traffic because URLs, metadata or media were treated as an afterthought — so this skill puts them first.

Work through the phases in order. At each phase, produce a concrete artifact (inventory, mapping table, import command, redirect map, checklist) rather than generic advice.

## Phase 0 — Decide whether to migrate

Ask (or infer) why Laravel: custom business logic, performance, security, integrating with an existing Laravel app/CRM, maintainability. If the site is mainly editorial with no custom logic, mention the alternatives honestly (headless WP + Laravel front, or staying on WP with a cleanup). Then proceed with what the user decides.

## Phase 1 — Inventory the WordPress site

Produce an inventory table before writing any code. Use WP-CLI or SQL on a copy of the DB — never on production.

- Content types: `wp post-type list`, counts per type & status (`wp post list --post_type=X --format=count`).
- Taxonomies and terms (categories, tags, custom), menus, widgets.
- Custom fields: ACF groups / meta keys actually used (`SELECT meta_key, COUNT(*) FROM wp_postmeta GROUP BY meta_key ORDER BY 2 DESC`). Ignore `_edit_lock`, `_wp_old_slug` (but keep `_wp_old_slug` for redirects!), etc.
- Users and roles; whether passwords must be preserved.
- Media: count and size of `wp-content/uploads`, image sizes in use.
- Plugins providing features to rebuild: forms, SEO (Yoast/Rank Math meta), multilingual (WPML/Polylang), e-commerce, memberships, search, redirects plugin.
- Every public URL: crawl the live site (Screaming Frog or sitemap) + export from DB; pull top pages from Google Search Console / Analytics (these are the URLs you must not break).
- Shortcodes and page-builder markup inside `post_content` (`SELECT ... WHERE post_content LIKE '%[%'`).

Details and SQL: [references/data-mapping.md](references/data-mapping.md).

## Phase 2 — Target model & mapping

- Design Laravel models/tables for what the site **needs**, not a clone of `wp_posts`. Typical: `posts`, `pages`, `categories`, `tags`, `taggables`, `media`, `users`, `redirects`, plus one table per real CPT.
- Keep a `wp_id` (nullable, indexed) column on each imported table — it makes re-running imports idempotent and lets you trace issues.
- Store SEO fields explicitly: `meta_title`, `meta_description`, `canonical_url`, `og_image`, `noindex`.
- Write the mapping table (WP source → Laravel column, transformation) and get the user to validate it.

## Phase 3 — Import pipeline

Build the import as **re-runnable Artisan commands** reading from a second DB connection (`wordpress` in `config/database.php`) pointing to a copy of the WP database.

- One command per entity, run in dependency order: users → terms → media → posts/pages/CPTs → relations → redirects.
- Upsert on `wp_id` so the import can run many times (dev, staging, final delta at cutover).
- Content cleanup: convert or strip shortcodes and builder markup, rewrite internal links and `wp-content/uploads` URLs to the new media paths, keep `<h2>` structure, sanitize HTML (allowlist).
- Media: copy files (rsync) preserving paths when possible (`/wp-content/uploads/2024/05/x.jpg` → keep the same public path or redirect it), record alt text from `_wp_attachment_image_alt`.
- Users: WordPress hashes (phpass `$P$` or, since WP 6.8, bcrypt-based `$wp$`) are not Laravel hashes. Either force a password reset, or verify the old hash on first login and rehash — see the reference.
- Log counts per entity and compare with the inventory; a mismatch blocks cutover.

A command skeleton is in [references/import-command.md](references/import-command.md).

## Phase 4 — URLs, redirects and SEO parity

This is where traffic is won or lost.

- Prefer **keeping the same URLs** (same slugs, same permalink structure, same trailing slash policy). Laravel routes can mirror `/{year}/{month}/{slug}/`, `/category/{slug}/`, etc.
- Every URL that changes gets a **301** to its closest equivalent — never a blanket redirect to the homepage. Store redirects in a `redirects` table served by middleware (or web-server rules for volume), import Yoast/Redirection plugin rules and `_wp_old_slug` values.
- Also handle: `/feed/`, `/wp-admin` & `/wp-login.php` (redirect to the new admin login or 404), `/?p=123` legacy IDs, attachment pages, pagination `/page/2/`, author and date archives (redirect or noindex), uploads paths.
- SEO parity per template: title, meta description, canonical, robots, hreflang, Open Graph, schema.org, XML sitemap, robots.txt, breadcrumbs, internal linking, image alts. Use the `technical-seo-php` skill if available.
- Build a **URL test list** (top pages from Search Console + full crawl) and an automated check that each returns 200 or a single 301 to a 200.

## Phase 5 — Editor experience

Editors lose WordPress's admin — plan its replacement explicitly: Filament (fastest for Laravel), Nova, or a custom admin. Cover: rich text editor (TipTap/Trix/CKEditor), media library, drafts/scheduling, previews, roles, SEO fields per page, redirects manager. Train editors before launch.

## Phase 6 — Cutover

Use the checklist in [references/cutover-checklist.md](references/cutover-checklist.md). Key points: content freeze on WP, final delta import, DNS TTL lowered beforehand, redirects deployed with the app, crawl right after launch, submit the new sitemap in Search Console, keep the old WP read-only backup for a few months, monitor 404s and rankings for 4–8 weeks.

## How to answer

- Identify which phase the user is in and give the artifact for that phase.
- For code: give Laravel migrations, models, Artisan commands and middleware with file paths, idempotent and runnable.
- Always mention the SEO/URL impact of any structural choice.
- Flag risks explicitly (lost URLs, password hashes, shortcodes, multilingual) with a mitigation.
