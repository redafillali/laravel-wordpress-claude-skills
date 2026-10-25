# Laravel front-end performance

## LCP hero image in Blade

```blade
<img
  src="{{ Vite::asset('resources/images/hero-1200.webp') }}"
  srcset="{{ Vite::asset('resources/images/hero-640.webp') }} 640w,
          {{ Vite::asset('resources/images/hero-1200.webp') }} 1200w,
          {{ Vite::asset('resources/images/hero-1920.webp') }} 1920w"
  sizes="100vw"
  width="1920" height="1080"
  alt="{{ __('Hero description') }}"
  fetchpriority="high"
  decoding="async">
```

Below-the-fold images: `loading="lazy"` + `width`/`height`. For user-uploaded images, generate sizes on upload (spatie/laravel-medialibrary conversions or intervention/image) and output `srcset`.

## Vite

- Preload strategy (Laravel 11+): `Vite::useAggressivePrefetching()` or `Vite::prefetch(concurrency: 3)` in `AppServiceProvider` for SPA-ish navigation; keep the entry small.
- Split heavy, rarely-used code with dynamic imports:
  ```js
  document.querySelector('#map') && import('./map.js').then(m => m.init());
  ```
- Separate entries per area (`resources/js/admin.js` vs `app.js`) so public pages don't load admin code.
- Check bundle size: `npx vite-bundle-visualizer`.

## Critical CSS

For content pages, inline above-the-fold CSS and load the rest async:

```blade
<style>{!! file_get_contents(public_path('build/critical-home.css')) !!}</style>
<link rel="preload" href="{{ Vite::asset('resources/css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript>@vite('resources/css/app.css')</noscript>
```

(Generate critical CSS at build time with `critical`/`beasties`.) With Tailwind, the full CSS is often small enough (< 20 KB gzip) that inlining isn't necessary — measure first.

## Fonts

```blade
<link rel="preload" href="{{ Vite::asset('resources/fonts/inter-var.woff2') }}" as="font" type="font/woff2" crossorigin>
```

```css
@font-face { font-family: Inter; src: url('../fonts/inter-var.woff2') format('woff2'); font-display: swap; font-weight: 100 900; }
```

Self-host instead of Google Fonts CSS (saves a connection + render-blocking stylesheet).

## Server response (TTFB)

- Anonymous pages: `spatie/laravel-responsecache` or a CDN caching HTML with proper `Cache-Control: public, s-maxage=…`.
- `php artisan optimize`, OPcache, Redis, eliminate N+1 (see `laravel-performance-tuning`).

## Nginx static assets

```nginx
location /build/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
gzip on; gzip_types text/css application/javascript application/json image/svg+xml;
```

## Third-party scripts

```blade
{{-- Load GTM after first interaction or idle --}}
<script>
  const loadGtm = () => { if (window.gtmLoaded) return; window.gtmLoaded = true;
    const s = document.createElement('script'); s.src = 'https://www.googletagmanager.com/gtm.js?id={{ config('services.gtm.id') }}'; s.async = true; document.head.appendChild(s); };
  ['scroll','pointerdown','keydown'].forEach(e => addEventListener(e, loadGtm, {once: true, passive: true}));
  setTimeout(loadGtm, 4000);
</script>
```

Trade-off: delayed tags may miss very short sessions — validate with the marketing team; consent mode still applies.
