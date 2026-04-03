<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'get_option',
			'description' => 'Read a WordPress option by key. Returns the value (scalar or array).',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'key' => [ 'type' => 'string', 'description' => 'Option key.' ] ],
				'required'   => [ 'key' ],
			],
		],
		function ( array $args ): array {
			$key   = sanitize_text_field( $args['key'] ?? '' );
			$value = get_option( $key, '__NOT_FOUND__' );
			if ( $value === '__NOT_FOUND__' ) {
				return [ 'key' => $key, 'exists' => false, 'value' => null ];
			}
			return [ 'key' => $key, 'exists' => true, 'value' => $value ];
		}
	);

	$tools->register(
		[
			'name'        => 'set_option',
			'description' => 'Create or update a WordPress option. Supports any serializable value.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'key'   => [ 'type' => 'string', 'description' => 'Option key.' ],
					'value' => [ 'description' => 'Option value (any type).' ],
				],
				'required'   => [ 'key', 'value' ],
			],
		],
		function ( array $args ): array {
			$key = sanitize_text_field( $args['key'] ?? '' );
			if ( empty( $key ) ) throw new Exception( 'Option key is required.' );
			$result = update_option( $key, $args['value'] );
			return [ 'key' => $key, 'updated' => $result, 'value' => $args['value'] ];
		}
	);

	$tools->register(
		[
			'name'        => 'search_options',
			'description' => 'Search wp_options by key pattern (SQL LIKE). Returns key, value, and autoload status.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'pattern' => [ 'type' => 'string',  'description' => 'LIKE pattern (e.g., "wpseo%" or "%cache%").' ],
					'limit'   => [ 'type' => 'integer', 'description' => 'Max results (default: 50).' ],
				],
				'required'   => [ 'pattern' ],
			],
		],
		function ( array $args ): array {
			global $wpdb;
			$pattern = $args['pattern'] ?? '';
			if ( empty( $pattern ) ) throw new Exception( 'Search pattern is required.' );

			$limit = min( max( (int) ( $args['limit'] ?? 50 ), 1 ), 200 );
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d", $pattern, $limit ),
				ARRAY_A
			);

			$results = [];
			foreach ( $rows as $row ) {
				$value   = maybe_unserialize( $row['option_value'] );
				$display = is_string( $value ) && strlen( $value ) > 500 ? substr( $value, 0, 500 ) . '... (truncated)' : $value;
				$results[] = [ 'key' => $row['option_name'], 'value' => $display, 'autoload' => $row['autoload'] ];
			}
			return [ 'results' => $results, 'count' => count( $results ) ];
		}
	);
} );
