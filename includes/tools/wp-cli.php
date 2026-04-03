<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'run_wp_cli',
			'description' => 'Execute a WP-CLI command on the server. Input is the command string without the leading "wp".',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'command' => [ 'type' => 'string', 'description' => 'WP-CLI command (without leading "wp").' ] ],
				'required'   => [ 'command' ],
			],
		],
		function ( array $args ): array {
			$command = $args['command'] ?? '';
			if ( empty( $command ) ) throw new Exception( 'Command is required.' );

			if ( ! function_exists( 'shell_exec' ) || ! function_exists( 'exec' ) ) {
				throw new Exception( 'Shell execution is disabled on this server. Use WP-CLI via SSH instead.' );
			}

			$wp_cli = null;
			foreach ( [ 'wp', '/usr/local/bin/wp', '/usr/bin/wp' ] as $bin ) {
				$check = @shell_exec( "which {$bin} 2>/dev/null" );
				if ( ! empty( trim( $check ?? '' ) ) ) { $wp_cli = trim( $check ); break; }
			}
			if ( ! $wp_cli ) throw new Exception( 'WP-CLI is not installed on this server.' );

			$full = sprintf( '%s %s --path=%s --allow-root 2>&1', escapeshellcmd( $wp_cli ), $command, escapeshellarg( ABSPATH ) );
			$output = [];
			$code   = 0;
			exec( $full, $output, $code );

			return [ 'command' => "wp {$command}", 'exit_code' => $code, 'output' => implode( "\n", $output ) ];
		}
	);
} );
