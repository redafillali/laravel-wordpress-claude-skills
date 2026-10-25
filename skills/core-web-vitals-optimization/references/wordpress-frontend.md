# WordPress front-end performance

## Foundation

1. **Page cache**: server-level (Nginx FastCGI cache, LiteSpeed Cache on LiteSpeed servers, host cache) or a plugin (WP Rocket, W3TC, WP Super Cache). One page-cache solution only.
2. **Object cache**: Redis + Redis Object Cache drop-in on VPS.
3. **CDN**: Cloudflare (APO for WordPress caches HTML at the edge) or Bunny.
4. **PHP 8.3+**, OPcache, enough PHP workers.

## LCP image

- Use the theme/block image with `wp_get_attachment_image()` (srcset/sizes automatic). WordPress 6.3+ adds `fetchpriority="high"` to the likely LCP image and skips lazy-loading for the first content images — make sure optimization plugins don't override it.
- Sliders/hero carousels built in JS delay LCP → replace with a static hero image.
- Background-image heroes (page builders) → switch to an `<img>` or preload it:
  ```php
  add_action( 'wp_head', function () {
      if ( is_front_page() ) {
          echo '<link rel="preload" as="image" href="' . esc_url( get_theme_file_uri( 'assets/hero.webp' ) ) . '" fetchpriority="high">';
      }
  }, 1 );
  ```
- Convert to WebP/AVIF (WordPress supports WebP/AVIF uploads; or an image plugin/CDN).

## Dequeue unused assets per page

```php
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_page( array( 'contact' ) ) ) {
        wp_dequeue_script( 'contact-form-7' );
        wp_dequeue_style( 'contact-form-7' );
    }
    if ( ! is_singular() || ! has_blocks() ) {
        wp_dequeue_style( 'wp-block-library' );
    }
    if ( function_exists( 'is_woocommerce' ) && ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
        wp_dequeue_style( 'woocommerce-general' );
        wp_dequeue_script( 'wc-cart-fragments' );
    }
}, 100 );
```

Find handles with Query Monitor → Scripts/Styles. Test each page after dequeuing.

## Scripts

- `defer` theme scripts: `wp_enqueue_script( 'theme', $src, array(), $ver, array( 'strategy' => 'defer', 'in_footer' => true ) );`
- Delay third-party scripts (chat, pixels) until interaction — WP Rocket / Perfmatters / Flying Scripts offer this; validate tracking afterwards.
- Remove emoji script if unused: `remove_action( 'wp_head', 'print_emoji_detection_script', 7 ); remove_action( 'wp_print_styles', 'print_emoji_styles' );`
- jQuery Migrate / jQuery: only remove if no plugin depends on it.

## Page builders

Elementor/Divi/WPBakery generate large DOM and CSS. Mitigations: enable their performance features (Elementor: optimized DOM output, improved asset loading, inline font icons, CSS print method external), limit widgets/animations on landing pages, avoid nested sections. Structural fix: block theme or lightweight theme (GeneratePress, Kadence, Blocksy) + blocks for key templates.

## Fonts

- Self-host (Local Google Fonts / theme.json `fontFace`), WOFF2, 1–2 families, preload the main one, `font-display: swap`.

## CLS

- Images inserted via builders without dimensions → set width/height; embeds (YouTube) with aspect-ratio wrappers or lite-youtube facades.
- Cookie banner as overlay; ad slots with reserved min-height.

## WooCommerce

- Disable cart fragments on non-shop pages, limit related/upsell queries, paginate product listings, cache category pages (exclude cart/checkout/my-account from page cache).
