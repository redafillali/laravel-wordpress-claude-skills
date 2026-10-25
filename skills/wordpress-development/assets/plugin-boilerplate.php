<?php
/**
 * Plugin Name:       Acme Example
 * Description:       Minimal, secure plugin skeleton. Rename "acme" / "Acme" everywhere.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       acme
 */

namespace Acme\Example;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';
const FILE    = __FILE__;

/**
 * Register custom post type on init.
 */
function register_post_types(): void {
	register_post_type(
		'acme_project',
		array(
			'label'        => __( 'Projects', 'acme' ),
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'projects' ),
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_post_types' );

/**
 * Enqueue front-end assets only where needed.
 */
function enqueue_assets(): void {
	if ( ! is_singular( 'acme_project' ) && ! is_post_type_archive( 'acme_project' ) ) {
		return;
	}
	$url = plugin_dir_url( FILE );
	wp_enqueue_style( 'acme-front', $url . 'assets/front.css', array(), VERSION );
	wp_enqueue_script( 'acme-front', $url . 'assets/front.js', array(), VERSION, array( 'strategy' => 'defer' ) );
	wp_localize_script(
		'acme-front',
		'acmeData',
		array(
			'restUrl' => esc_url_raw( rest_url( 'acme/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Settings page using the Settings API (sanitized on save).
 */
function register_settings(): void {
	register_setting(
		'acme_settings',
		'acme_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_options',
			'default'           => array( 'api_key' => '' ),
		)
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );

function sanitize_options( $input ): array {
	return array(
		'api_key' => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
	);
}

function add_settings_page(): void {
	add_options_page( __( 'Acme', 'acme' ), __( 'Acme', 'acme' ), 'manage_options', 'acme', __NAMESPACE__ . '\\render_settings_page' );
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page' );

function render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$options = get_option( 'acme_options', array( 'api_key' => '' ) );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'acme_settings' ); ?>
			<label for="acme_api_key"><?php esc_html_e( 'API key', 'acme' ); ?></label>
			<input id="acme_api_key" type="text" name="acme_options[api_key]" value="<?php echo esc_attr( $options['api_key'] ); ?>" class="regular-text">
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Activation: register CPT then flush rewrite rules once.
 */
function activate(): void {
	register_post_types();
	flush_rewrite_rules();
}
register_activation_hook( FILE, __NAMESPACE__ . '\\activate' );

function deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( FILE, __NAMESPACE__ . '\\deactivate' );

// Put cleanup (delete_option( 'acme_options' ), custom tables) in uninstall.php,
// guarded by: defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
