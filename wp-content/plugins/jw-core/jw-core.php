<?php
/**
 * Plugin Name: JW Core
 * Description: Conteúdo editorial, metaboxes, GraphQL, atualização pública e contato da JW US Invest.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: wp-graphql
 * License: GPL-2.0-or-later
 * Text Domain: jw-core
 *
 * @package JWCore
 */

defined( 'ABSPATH' ) || exit;
define( 'JW_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'JW_CORE_URL', plugin_dir_url( __FILE__ ) );
foreach ( array( 'schema', 'sanitize', 'admin', 'content', 'graphql', 'runtime', 'contact/validation', 'contact/storage', 'contact/rest' ) as $jw_module ) {
	require_once JW_CORE_PATH . 'includes/' . $jw_module . '.php';
}
register_activation_hook( __FILE__, 'jw_core_activate' );

/** Install only technical storage; editorial content is created separately. */
function jw_core_activate() {
	jw_core_register_types();
	jw_core_contact_install();
	add_option( 'jw_content_revision', wp_generate_uuid4(), '', false );
	flush_rewrite_rules();
}
