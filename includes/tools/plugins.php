<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'list_plugins',
			'description' => 'List all installed plugins with name, version, active/inactive status, and update availability.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search' => [ 'type' => 'string', 'description' => 'Optional search term to filter plugins by name.' ],
				],
			],
		],
		function ( array $args ): array {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all     = get_plugins();
			$active  = get_option( 'active_plugins', [] );
			$updates = get_site_transient( 'update_plugins' );
			$search  = $args['search'] ?? '';

			$result = [];
			foreach ( $all as $file => $data ) {
				if ( $search && stripos( $data['Name'], $search ) === false ) continue;

				$has_update = isset( $updates->response[ $file ] );
				$result[] = [
					'file'             => $file,
					'name'             => $data['Name'],
					'version'          => $data['Version'],
					'active'           => in_array( $file, $active, true ),
					'update_available' => $has_update,
					'new_version'      => $has_update ? $updates->response[ $file ]->new_version : null,
				];
			}
			return $result;
		}
	);

	$tools->register(
		[
			'name'        => 'activate_plugin',
			'description' => 'Activate a plugin by its file path (e.g., "akismet/akismet.php").',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'plugin' => [ 'type' => 'string', 'description' => 'Plugin file path relative to plugins directory.' ] ],
				'required'   => [ 'plugin' ],
			],
		],
		function ( array $args ): array {
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin = sanitize_text_field( $args['plugin'] ?? '' );
			if ( empty( $plugin ) ) throw new Exception( 'Plugin file path is required.' );

			$result = activate_plugin( $plugin );
			if ( is_wp_error( $result ) ) throw new Exception( $result->get_error_message() );

			return [ 'success' => true, 'plugin' => $plugin, 'status' => 'activated' ];
		}
	);

	$tools->register(
		[
			'name'        => 'deactivate_plugin',
			'description' => 'Deactivate a plugin by its file path.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'plugin' => [ 'type' => 'string', 'description' => 'Plugin file path relative to plugins directory.' ] ],
				'required'   => [ 'plugin' ],
			],
		],
		function ( array $args ): array {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin = sanitize_text_field( $args['plugin'] ?? '' );
			if ( empty( $plugin ) ) throw new Exception( 'Plugin file path is required.' );

			deactivate_plugins( $plugin );
			return [ 'success' => true, 'plugin' => $plugin, 'status' => 'deactivated' ];
		}
	);
} );
