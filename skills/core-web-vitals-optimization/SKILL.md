---
name: core-web-vitals-optimization
description: Diagnose and improve Core Web Vitals and page speed (LCP, INP, CLS, plus TTFB and FCP) on Laravel and WordPress sites — reading PageSpeed Insights / Lighthouse / CrUX / Search Console reports, then fixing the actual cause — slow server response, render-blocking CSS/JS, unoptimized hero images, web fonts, third-party scripts (GTM, Meta Pixel, chat widgets), layout shifts, heavy JavaScript and page builders — with Vite, Blade and WordPress-specific techniques and caching/CDN setup. Use this skill whenever the user shares a PageSpeed/Lighthouse score or report, says the site is slow on mobile, mentions LCP/INP/CLS or "Core Web Vitals assessment failed", wants a better performance score for SEO or ads landing pages, or asks how to speed up a WordPress or Laravel front-end.
---

# Core Web Vitals Optimization

Core Web Vitals are a **field** metric: Google uses real-user data (CrUX, 75th percentile, 28-day window). Lighthouse lab scores help debug but aren't what ranks. Targets at p75: **LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1**. Optimize the metric that fails, on the template that matters (home, landing pages, product/article templates), on mobile first.

## 1. Diagnose

1. PageSpeed Insights on key URLs: look at the **field data** block first (URL and origin), then the lab diagnostics.
2. Search Console → Core Web Vitals report: which URL groups fail, which metric.
3. For LCP, identify the **LCP element** (Lighthouse "Largest Contentful Paint element") and break LCP into its four parts: **TTFB → resource load delay → resource load time → render delay**. Fix the biggest part.
4. For INP, find slow interactions (Chrome DevTools Performance panel with CPU throttling, or the `web-vitals` library with attribution in production) and the long tasks behind them.
5. For CLS, use the Layout Shift regions / "Avoid large layout shifts" audit to find shifting elements.

If the user only gives a score, ask for the PSI report link or the main diagnostics; meanwhile list the most likely causes for their stack.

## 2. LCP playbook

| LCP sub-part too long | Fixes |
|---|---|
| **TTFB** (> 0.8 s) | Full-page cache (WP: server/page cache plugin; Laravel: response cache for anonymous pages), OPcache, Redis object cache, fix slow queries, CDN with HTML caching where possible, closer hosting region, HTTP/2/3 |
| **Load delay** (browser discovers the image late) | LCP image in the initial HTML as `<img>` (not CSS background, not injected by JS/slider), `fetchpriority="high"`, `<link rel="preload" as="image" imagesrcset=…>` when needed, never `loading="lazy"` on it |
| **Load time** (image too heavy) | Right dimensions via `srcset`/`sizes`, AVIF/WebP, compression (~100–200 KB max for hero on mobile), CDN/image service |
| **Render delay** (render-blocking resources) | Inline critical CSS, defer non-critical CSS, `defer` scripts, remove unused CSS (page builders!), `font-display: swap` + preload the 1–2 key fonts, avoid hiding content until JS runs (A/B testing, animation libraries) |

## 3. INP playbook

- Reduce JavaScript: remove unused libraries/plugins, avoid jQuery-heavy plugins on key pages, code-split (Vite dynamic `import()`).
- Third-party scripts (GTM tags, pixels, chat, heatmaps) are frequent offenders: audit GTM containers, load non-essential tags after consent/interaction/`requestIdleCallback`, consider server-side tagging.
- Break long tasks: yield to the main thread (`await scheduler.yield?.()` / `setTimeout` chunks), debounce input handlers, avoid synchronous layout thrashing.
- Give immediate visual feedback on click, then do heavy work.
- Livewire/Alpine: debounce `wire:model`, avoid re-rendering large components on each keystroke; WordPress: watch admin-ajax calls triggered on interaction.

## 4. CLS playbook

- `width`/`height` attributes (or CSS `aspect-ratio`) on every image, video, iframe, embed.
- Reserve space for ads, cookie banners (overlay instead of push-down), late-loading widgets, and dynamic content.
- Fonts: `font-display: swap` + metric-compatible fallback (`size-adjust`, `ascent-override`) or `optional`; preload key fonts.
- Avoid inserting content above existing content after load; animate with `transform`, not `top`/`height`.

## 5. Stack specifics

- **Laravel/Vite/Blade**: [references/laravel-frontend.md](references/laravel-frontend.md) — preload/prefetch with Vite, responsive images, critical CSS, response caching, Nginx caching headers.
- **WordPress**: [references/wordpress-frontend.md](references/wordpress-frontend.md) — page cache, asset dequeue per page, page builders, image handling, font hosting, WooCommerce, plugin choices.

## 6. Verify

- Re-run PSI/Lighthouse (mobile) 3× and compare medians; field data takes up to 28 days to reflect changes — say so.
- Add real-user monitoring with the `web-vitals` library (send to GA4 or a log endpoint) for fast feedback.

## 7. How to answer

1. Name the failing metric(s) and the probable root cause from the data given.
2. Give a prioritized fix list (impact vs effort), with exact code/config for their stack.
3. Separate quick wins (hours) from structural work (theme/page-builder replacement, hosting change).
4. State how to verify and the expected direction of each metric; don't promise a specific score.
