# Hardening snippets

## wp-config.php

```php
define( 'DISALLOW_FILE_EDIT', true );          // no theme/plugin editor in admin
// define( 'DISALLOW_FILE_MODS', true );       // also blocks installs/updates from admin — only with CI/WP-CLI updates
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_POST_REVISIONS', 10 );
// Fresh salts: https://api.wordpress.org/secret-key/1.1/salt/  or  wp config shuffle-salts
```

## File permissions (run as root/sudo, adapt user/group)

```bash
cd /var/www/site/public_html
chown -R siteuser:www-data .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 640 wp-config.php
```

## Block PHP execution in uploads

Apache — `wp-content/uploads/.htaccess`:

```apache
<FilesMatch "\.(php|phtml|php[0-9]|phar)$">
    Require all denied
</FilesMatch>
```

Nginx:

```nginx
location ~* ^/wp-content/uploads/.*\.(php|phtml|phar)$ { deny all; }
```

## Protect sensitive files

Apache (root `.htaccess`):

```apache
<FilesMatch "^(wp-config\.php|readme\.html|license\.txt|\.user\.ini|xmlrpc\.php)$">
    Require all denied
</FilesMatch>
Options -Indexes
```

Nginx:

```nginx
location = /xmlrpc.php { deny all; }
location ~* /(wp-config\.php|readme\.html|license\.txt|\.user\.ini|\.git|\.env) { deny all; }
location ~ /\. { deny all; }
autoindex off;

# Login rate limiting
limit_req_zone $binary_remote_addr zone=wplogin:10m rate=5r/m;
location = /wp-login.php {
    limit_req zone=wplogin burst=5 nodelay;
    include fastcgi_params;   # keep your normal PHP handling here
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

## Disable XML-RPC & limit user enumeration (mu-plugin)

`wp-content/mu-plugins/security-tweaks.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'xmlrpc_enabled', '__return_false' );

// Hide users endpoint from anonymous visitors
add_filter( 'rest_endpoints', function ( $endpoints ) {
    if ( ! is_user_logged_in() ) {
        unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
} );

// Block ?author=N enumeration
add_action( 'template_redirect', function () {
    if ( ! is_admin() && isset( $_GET['author'] ) ) {
        wp_safe_redirect( home_url(), 301 );
        exit;
    }
} );

// Generic login errors
add_filter( 'login_errors', fn() => __( 'Invalid credentials.' ) );

// Remove generator version
remove_action( 'wp_head', 'wp_generator' );
```

## Security headers

Apache:

```apache
<IfModule mod_headers.c>
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
```

Nginx: same with `add_header ... always;`. Start CSP in `Content-Security-Policy-Report-Only` mode — WordPress + plugins often use inline scripts.

## fail2ban (VPS)

Use a WordPress filter on `wp-login.php` / `xmlrpc.php` POSTs in access logs, or the `wp-fail2ban` plugin which logs auth failures to syslog for fail2ban.

## WP-CLI security routine

```bash
wp core verify-checksums
wp plugin verify-checksums --all
wp core update && wp plugin update --all && wp theme update --all
wp user list --role=administrator --fields=ID,user_login,user_email,user_registered
wp config shuffle-salts
```
