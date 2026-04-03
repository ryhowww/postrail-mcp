<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$find_log = function (): string {
		if ( defined( 'WP_DEBUG_LOG' ) ) {
			if ( is_string( WP_DEBUG_LOG ) && file_exists( WP_DEBUG_LOG ) ) return WP_DEBUG_LOG;
			if ( WP_DEBUG_LOG === true && file_exists( WP_CONTENT_DIR . '/debug.log' ) ) return WP_CONTENT_DIR . '/debug.log';
		}
		$php_log = ini_get( 'error_log' );
		if ( $php_log && file_exists( $php_log ) ) return $php_log;

		foreach ( [ WP_CONTENT_DIR . '/debug.log', ABSPATH . 'error_log', ABSPATH . 'php_error.log', '/tmp/php-errors.log' ] as $path ) {
			if ( file_exists( $path ) ) return $path;
		}
		throw new Exception( 'No error log file found. Enable WP_DEBUG_LOG in wp-config.php.' );
	};

	$tools->register(
		[
			'name'        => 'read_error_log',
			'description' => 'Read the PHP/WordPress error log. Returns the last N lines.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'lines' => [ 'type' => 'integer', 'description' => 'Lines from end (default: 100, max: 1000).' ] ],
			],
		],
		function ( array $args ) use ( $find_log ): array {
			$path  = $find_log();
			$lines = min( max( (int) ( $args['lines'] ?? 100 ), 1 ), 1000 );
			$size  = filesize( $path );

			$handle = fopen( $path, 'r' );
			if ( ! $handle ) throw new Exception( "Cannot open log file: {$path}" );

			$seek = $lines * 200;
			if ( $size > $seek ) { fseek( $handle, -$seek, SEEK_END ); fgets( $handle ); }

			$all = [];
			while ( ( $line = fgets( $handle ) ) !== false ) $all[] = rtrim( $line );
			fclose( $handle );

			$output = array_slice( $all, -$lines );
			return [ 'path' => $path, 'size' => size_format( $size ), 'lines' => count( $output ), 'content' => implode( "\n", $output ) ];
		}
	);

	$tools->register(
		[
			'name'        => 'clear_error_log',
			'description' => 'Truncate the error log file to zero bytes.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
		],
		function ( array $args ) use ( $find_log ): array {
			$path = $find_log();
			$size = filesize( $path );
			$handle = fopen( $path, 'w' );
			if ( ! $handle ) throw new Exception( "Cannot open log for writing: {$path}" );
			fclose( $handle );
			return [ 'path' => $path, 'cleared' => true, 'size_before' => size_format( $size ) ];
		}
	);
} );
