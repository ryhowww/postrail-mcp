<?php
/**
 * Plugin Name: PostRail MCP
 * Plugin URI: https://postrail.com
 * Description: MCP server endpoint for PostRail — enables remote WordPress management via the Model Context Protocol.
 * Version: 1.1.0
 * Author: Ryan Howard
 * License: GPL-2.0-or-later
 * Text Domain: postrail-mcp
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POSTRAIL_MCP_VERSION', '1.1.0' );
define( 'POSTRAIL_MCP_FILE', __FILE__ );
define( 'POSTRAIL_MCP_PATH', plugin_dir_path( __FILE__ ) );

// Plugin Update Checker — auto-updates from GitHub releases.
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

$postrailMcpUpdater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/ryhowww/postrail-mcp/',
	__FILE__,
	'postrail-mcp'
);
$postrailMcpUpdater->getVcsApi()->enableReleaseAssets();
$postrailMcpUpdater->setBranch( 'main' );

// Force auto-updates.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( isset( $item->slug ) && $item->slug === 'postrail-mcp' ) {
		return true;
	}
	return $update;
}, 10, 2 );

// Autoload classes.
spl_autoload_register( function ( $class ) {
	$prefix = 'PostRailMCP\\';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$map = [
		'Server' => 'includes/class-server.php',
		'Auth'   => 'includes/class-auth.php',
		'Tools'  => 'includes/class-tools.php',
		'Admin'  => 'includes/class-admin.php',
	];

	if ( isset( $map[ $relative ] ) ) {
		require_once POSTRAIL_MCP_PATH . $map[ $relative ];
	}
} );

// Boot.
add_action( 'plugins_loaded', function () {
	$auth   = new PostRailMCP\Auth();
	$tools  = new PostRailMCP\Tools();
	$server = new PostRailMCP\Server( $auth, $tools );

	if ( is_admin() ) {
		new PostRailMCP\Admin();
	}
} );

// Redirect to settings page after activation.
register_activation_hook( __FILE__, function () {
	set_transient( 'postrail_mcp_activated', true, 30 );
} );

// Redirect to settings page after activation.
add_action( 'admin_init', function () {
	if ( get_transient( 'postrail_mcp_activated' ) ) {
		delete_transient( 'postrail_mcp_activated' );
		if ( ! isset( $_GET['activate-multi'] ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=postrail-mcp' ) );
			exit;
		}
	}
} );
