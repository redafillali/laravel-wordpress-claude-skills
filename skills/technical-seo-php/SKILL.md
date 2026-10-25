---
name: technical-seo-php
description: Technical SEO audit and implementation for Laravel and WordPress sites — title and meta tags, canonicals, robots directives, XML sitemaps, robots.txt, hreflang, schema.org JSON-LD, Open Graph, 301 redirect plans, pagination, internal linking, image SEO and Core Web Vitals (LCP, INP, CLS). Use this skill whenever the user asks for an SEO audit, an SEO-friendly redesign or migration, meta tags or structured data in Blade or a WordPress theme, a sitemap, fixing indexing/coverage issues from Google Search Console, duplicate content, or page speed — even if they only say "make it rank better" or "Google doesn't index my pages".
---

# Technical SEO for Laravel & WordPress

Technical SEO is about removing obstacles: making sure search engines can **crawl**, **render**, **index** and **understand** the right pages, quickly. Content and links matter too, but they can't help a page Google can't reach or picks the wrong version of.

## 1. Audit workflow

When asked for an audit, follow this order (each layer depends on the previous) and output a prioritized table: *Issue | Impact (High/Med/Low) | Affected URLs | Fix | Effort*.

1. **Crawlability** — robots.txt, HTTP status codes, redirect chains, internal links reaching important pages, JS-only navigation, orphan pages.
2. **Indexability** — `noindex` (meta or `X-Robots-Tag`), canonicals, duplicate versions (http/https, www/non-www, trailing slash, query params, uppercase), staging sites leaking.
3. **Sitemaps** — exist, only 200/indexable canonical URLs, `lastmod` truthful, declared in robots.txt and Search Console.
4. **On-page signals** — one unique `<title>` (~50–60 chars) and meta description (~140–160 chars) per page, single descriptive `<h1>`, logical heading hierarchy, descriptive URLs, image `alt`.
5. **Structured data** — JSON-LD matching visible content: Organization/LocalBusiness, WebSite, BreadcrumbList, Article/BlogPosting, Product, FAQPage (only if appropriate), Course/EducationalOrganization for schools. Validate with Google's Rich Results Test.
6. **International** — `hreflang` reciprocal pairs + `x-default`, `lang` attribute, one language per URL.
7. **Performance / Core Web Vitals** — LCP < 2.5 s, INP < 200 ms, CLS < 0.1 (field data from CrUX/Search Console first, lab data second).
8. **Mobile & rendering** — responsive, content parity mobile/desktop, no blocked CSS/JS.

Use the data the user can provide: Search Console exports (Pages/Coverage, Performance), a crawl (Screaming Frog), PageSpeed Insights. If you have none, say which findings are hypotheses to verify.

## 2. Implementation — Laravel

- Centralize SEO data: a `Seo` value object or a package (`artesaos/seotools`, `ralphjsmit/laravel-seo`), filled per controller/model, rendered in the layout `<head>`.
- Canonical always absolute, normalized (scheme, host, no tracking params). Enforce one host and trailing-slash policy with a middleware or web-server redirect (301).
- Sitemaps: `spatie/laravel-sitemap` (generate via scheduled command into `public/sitemap.xml`, split in a sitemap index above 50,000 URLs).
- `noindex` for search results, filters, cart/account, thin tag pages, staging (`X-Robots-Tag: noindex` header on staging envs).
- Real 404 status for missing content (`abort(404)`), 410 for intentionally removed content, 301 for moved content (see redirect table pattern in the migration skill).
- Server-render critical content (Blade/Livewire/Inertia SSR) — don't rely on client-side JS for main content and links.

Blade snippets and JSON-LD examples: [references/laravel-seo.md](references/laravel-seo.md).

## 3. Implementation — WordPress

- Use one SEO plugin (Yoast or Rank Math) — never two — and configure: titles templates, noindex for thin archives (date, author on single-author sites, tags if thin), XML sitemap, breadcrumbs, schema base.
- Permalinks `/%postname%/` (or with category if needed); changing permalinks later requires 301s.
- Attachment pages → redirect to the file/parent (Yoast setting).
- Theme: `add_theme_support( 'title-tag' )`, no hardcoded `<title>`, single `<h1>`, semantic HTML, `wp_get_attachment_image()` for responsive images.
- Custom schema: extend the plugin's graph via its filters (`wpseo_schema_graph_pieces`, `rank_math/json_ld`) rather than printing a second disconnected JSON-LD block.

Details: [references/wordpress-seo.md](references/wordpress-seo.md).

## 4. Redesigns and migrations

SEO is most often lost during redesigns. Before launch: export all indexed URLs + top pages from Search Console, map each old URL to a new one (1:1, 301), keep titles/H1/content of top pages, crawl staging (with auth, but `noindex`), then remove `noindex` and staging `Disallow` at launch. After launch: crawl production, submit sitemap, monitor 404s and Coverage for 4–8 weeks.

## 5. Core Web Vitals quick wins

- **LCP**: serve the hero image in WebP/AVIF at the right size, `fetchpriority="high"`, no `loading="lazy"` on it, preload the critical font, inline critical CSS, fast TTFB (page cache, OPcache, Redis).
- **CLS**: `width`/`height` (or `aspect-ratio`) on images/iframes/embeds, reserve space for ads/banners, `font-display: swap` with size-adjusted fallbacks.
- **INP**: less JavaScript (remove unused plugins/libraries, defer third-party tags, split long tasks), avoid heavy handlers on input.

## 6. How to answer

- Lead with the 3–5 highest-impact issues; don't drown the user in low-value items.
- Give exact code (Blade, PHP, `.htaccess`/Nginx, plugin settings path) for each fix.
- Separate **verified** findings (from data provided) from **to verify** items.
- Never recommend manipulative tactics (cloaking, hidden text, fake reviews schema, doorway pages) — they risk manual penalties.
