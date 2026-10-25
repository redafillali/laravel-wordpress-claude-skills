# WordPress SEO configuration & code

## Baseline settings

- Settings → Reading: "Discourage search engines" **unchecked** in production (checked on staging).
- Settings → Permalinks: `/%postname%/` for most sites.
- One SEO plugin (Yoast SEO or Rank Math). Configure:
  - Title templates per post type/taxonomy; site name separator.
  - Noindex: search results (default), date archives, author archives on single-author sites, thin tag archives, attachment pages (redirect).
  - XML sitemap on; exclude noindexed types; submit `/sitemap_index.xml` in Search Console.
  - Organization/Person and logo in the schema settings; breadcrumbs enabled and rendered in the theme.
- Redirects: plugin redirect manager (Yoast Premium, Rank Math, or "Redirection") or server rules for bulk.

## Theme requirements

```php
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );          // WP/SEO plugin manages <title>
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'script', 'style' ) );
    add_theme_support( 'responsive-embeds' );
} );
```

- Exactly one `<h1>` per template (post title on singles; archive title on archives; site logo is **not** an h1 except maybe on the homepage).
- `wp_head()` and `wp_footer()` present; `language_attributes()` on `<html>`.
- Breadcrumbs: `if ( function_exists( 'yoast_breadcrumb' ) ) yoast_breadcrumb( '<nav class="breadcrumbs">', '</nav>' );` (Rank Math: `rank_math_the_breadcrumbs()`).

## Extending schema (Yoast)

```php
add_filter( 'wpseo_schema_webpage', function ( $data ) {
    if ( is_singular( 'course' ) ) {
        $data['about'] = array( '@type' => 'Course', 'name' => get_the_title() );
    }
    return $data;
} );
```

Rank Math: `add_filter( 'rank_math/json_ld', function ( $data, $jsonld ) { ...; return $data; }, 99, 2 );`

## Programmatic noindex

```php
add_filter( 'wp_robots', function ( $robots ) {
    if ( is_search() || ( is_tag() && is_paged() ) ) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
} );
```

(SEO plugins provide their own filters — `wpseo_robots`, `rank_math/frontend/robots` — use those when the plugin is active.)

## Performance for CWV

- Page cache (server-level or plugin), object cache (Redis), CDN.
- Images: `wp_get_attachment_image()` for srcset; WordPress adds `fetchpriority="high"` to the likely LCP image (6.3+) — don't lazy-load it with a plugin.
- Dequeue unused block library CSS / plugin assets on pages that don't use them.
- Limit page builders and sliders on key landing pages; self-host fonts with `font-display: swap`.
- Defer third-party scripts (chat, tag managers) or load on interaction.

## Common WordPress SEO issues

| Issue | Fix |
|---|---|
| `?replytocom=` / `?amp` / filter params indexed | canonical + noindex params; disable threaded comments replies links indexing |
| Duplicate `/category/x/page/2/` thin pages | Keep self-canonical on paginated pages; improve archive intros or noindex if thin |
| Staging indexed | Password-protect staging; `noindex` header; remove from index via Search Console |
| Two `<title>` tags | Theme hardcodes `<title>` while plugin outputs one — remove hardcoded tag, use `title-tag` support |
| Orphan CPT pages | Add archive pages, internal links, include CPT in sitemap |
| Mixed content after HTTPS | `wp search-replace 'http://domain' 'https://domain' --all-tables --dry-run` then run |
