<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'get_site_info',
			'description' => 'Get WordPress site overview: WP version, PHP version, active theme, plugin count, memory limit, debug mode, site URL, multisite status, permalink structure, server software.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
		],
		function ( array $args ): array {
			$theme = wp_get_theme();
			return [
				'site_url'            => site_url(),
				'home_url'            => home_url(),
				'site_name'           => get_bloginfo( 'name' ),
				'wp_version'          => get_bloginfo( 'version' ),
				'php_version'         => PHP_VERSION,
				'active_theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'active_plugins'      => count( get_option( 'active_plugins', [] ) ),
				'memory_limit'        => WP_MEMORY_LIMIT,
				'max_memory_limit'    => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'not set',
				'debug_mode'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'debug_log'           => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
				'multisite'           => is_multisite(),
				'permalink_structure' => get_option( 'permalink_structure' ) ?: 'Plain',
				'server_software'     => sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ),
				'timezone'            => wp_timezone_string(),
				'abspath'             => ABSPATH,
			];
		}
	);

	$tools->register(
		[
			'name'        => 'get_php_info',
			'description' => 'Get PHP configuration details: loaded extensions, key ini settings, memory usage.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
		],
		function ( array $args ): array {
			return [
				'version'            => PHP_VERSION,
				'sapi'               => PHP_SAPI,
				'extensions'         => get_loaded_extensions(),
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'upload_max'         => ini_get( 'upload_max_filesize' ),
				'post_max'           => ini_get( 'post_max_size' ),
				'memory_usage'       => size_format( memory_get_usage( true ) ),
				'memory_peak'        => size_format( memory_get_peak_usage( true ) ),
				'error_reporting'    => error_reporting(),
				'display_errors'     => ini_get( 'display_errors' ),
				'error_log'          => ini_get( 'error_log' ),
			];
		}
	);
} );
