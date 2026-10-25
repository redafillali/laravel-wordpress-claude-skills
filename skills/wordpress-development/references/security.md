# WordPress security patterns & audit checklist

## Form handler (admin-post) — reference pattern

```php
// Form
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'acme_save_settings', 'acme_nonce' ); ?>
    <input type="hidden" name="action" value="acme_save_settings">
    <input type="text" name="acme_title" value="<?php echo esc_attr( get_option( 'acme_title', '' ) ); ?>">
    <?php submit_button(); ?>
</form>

// Handler
add_action( 'admin_post_acme_save_settings', 'acme_save_settings' );
function acme_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Not allowed.', 'acme' ), 403 );
    }
    check_admin_referer( 'acme_save_settings', 'acme_nonce' );

    $title = isset( $_POST['acme_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['acme_title'] ) )
        : '';
    update_option( 'acme_title', $title, false );

    wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
    exit;
}
```

## AJAX handler

```php
add_action( 'wp_ajax_acme_like', 'acme_like' );
function acme_like() {
    check_ajax_referer( 'acme_like', 'nonce' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( 'read_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
    }
    $count = (int) get_post_meta( $post_id, '_acme_likes', true ) + 1;
    update_post_meta( $post_id, '_acme_likes', $count );
    wp_send_json_success( array( 'count' => $count ) );
}
```

## REST route

```php
add_action( 'rest_api_init', function () {
    register_rest_route( 'acme/v1', '/items/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'acme_update_item',
        'permission_callback' => fn( WP_REST_Request $r ) => current_user_can( 'edit_post', (int) $r['id'] ),
        'args'                => array(
            'id'    => array( 'validate_callback' => fn( $v ) => is_numeric( $v ) ),
            'title' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
        ),
    ) );
} );
```

REST requests from JS must send the `X-WP-Nonce` header with `wp_create_nonce( 'wp_rest' )` for cookie auth.

## $wpdb

```php
global $wpdb;
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
    $user_id, 'publish'
) );
// Identifiers (WP 6.2+): %i  →  $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id )
// LIKE: '%' . $wpdb->esc_like( $term ) . '%'  passed as a %s value
```

Never interpolate variables into the SQL string; whitelist ORDER BY columns and directions.

## Escaping cheat-sheet

| Context | Function |
|---|---|
| HTML text | `esc_html()` |
| HTML attribute | `esc_attr()` |
| URL in href/src | `esc_url()` |
| URL saved to DB / redirect | `esc_url_raw()` / `wp_safe_redirect()` |
| Inline JS value | `wp_json_encode()` (or `esc_js()` for simple strings in attributes) |
| Rich HTML | `wp_kses_post()` or `wp_kses( $html, $allowed )` |
| Textarea | `esc_textarea()` |
| Translated strings | `esc_html__()`, `esc_attr__()`, `esc_html_e()` |

## Audit checklist

- [ ] Every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- [ ] Every state-changing request (form, AJAX, REST, GET action links) checks a nonce **and** a capability.
- [ ] Every `$_GET/$_POST/$_REQUEST/$_COOKIE/$_SERVER` read is unslashed and sanitized; `isset()` guarded.
- [ ] Every echo of dynamic data is escaped for its context.
- [ ] No raw SQL without `prepare()`; no `extract()` on user input; no `eval`, `unserialize()` on user data (use JSON).
- [ ] REST routes have meaningful `permission_callback` (no missing callback warnings).
- [ ] `wp_ajax_nopriv_` handlers don't expose private data or perform privileged writes.
- [ ] File uploads restricted by type/size and capability; uploads dir has no PHP execution (server rule).
- [ ] Redirects use `wp_safe_redirect()`; external URLs whitelisted (`allowed_redirect_hosts`).
- [ ] Options/meta that store HTML are sanitized with `wp_kses_post` on save.
- [ ] Hardening: `DISALLOW_FILE_EDIT` true, strong salts, XML-RPC disabled if unused, admin username not "admin", 2FA for admins, updates applied, unused plugins/themes removed, `WP_DEBUG_DISPLAY` false in production.
- [ ] `wp-config.php` and `.env`/backups not web-accessible; directory listing off.
