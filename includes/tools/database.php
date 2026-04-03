<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'query_db',
			'description' => 'Execute SQL against the WordPress database. Default read-only. Use {prefix} for table prefix.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'sql'       => [ 'type' => 'string',  'description' => 'SQL query. Use {prefix} for the WP table prefix.' ],
					'read_only' => [ 'type' => 'boolean', 'description' => 'If true (default), only SELECT/SHOW/DESCRIBE/EXPLAIN allowed.' ],
				],
				'required' => [ 'sql' ],
			],
		],
		function ( array $args ): array {
			global $wpdb;

			$sql       = $args['sql'] ?? '';
			$read_only = $args['read_only'] ?? true;
			if ( empty( $sql ) ) throw new Exception( 'SQL query is required.' );

			$sql       = str_replace( '{prefix}', $wpdb->prefix, $sql );
			$is_select = preg_match( '/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', ltrim( $sql ) );

			if ( $read_only && ! $is_select ) {
				throw new Exception( 'Write queries are not allowed in read-only mode. Pass read_only=false to enable.' );
			}

			if ( $is_select ) {
				$results = $wpdb->get_results( $sql, ARRAY_A );
				if ( $wpdb->last_error ) throw new Exception( "SQL error: {$wpdb->last_error}" );
				return [ 'rows' => $results, 'row_count' => count( $results ), 'query' => $sql ];
			}

			$affected = $wpdb->query( $sql );
			if ( $wpdb->last_error ) throw new Exception( "SQL error: {$wpdb->last_error}" );
			return [ 'affected_rows' => $affected, 'insert_id' => $wpdb->insert_id, 'query' => $sql ];
		}
	);
} );
