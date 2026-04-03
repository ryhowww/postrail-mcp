<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	// Path validation supporting split directory layouts (Flywheel, Bedrock).
	$get_allowed_bases = function (): array {
		$bases = [ realpath( ABSPATH ) ];
		$content_real = realpath( WP_CONTENT_DIR );
		if ( $content_real !== false && strpos( $content_real, $bases[0] ) !== 0 ) {
			$bases[] = $content_real;
		}
		return $bases;
	};

	$is_inside_allowed = function ( string $real ) use ( $get_allowed_bases ): bool {
		foreach ( $get_allowed_bases() as $base ) {
			if ( strpos( $real, $base ) === 0 ) return true;
		}
		return false;
	};

	$resolve_path = function ( string $input ) use ( $is_inside_allowed ): string {
		if ( strpos( $input, '/' ) !== 0 ) $input = ABSPATH . $input;

		$real = realpath( $input );
		if ( $real === false ) {
			$parent = realpath( dirname( $input ) );
			if ( $parent === false || ! $is_inside_allowed( $parent ) ) {
				throw new Exception( 'Path is outside the WordPress installation or parent directory does not exist.' );
			}
			return $parent . '/' . basename( $input );
		}

		if ( ! $is_inside_allowed( $real ) ) {
			throw new Exception( 'Path is outside the WordPress installation.' );
		}
		return $real;
	};

	$tools->register(
		[
			'name'        => 'read_file',
			'description' => 'Read a file within the WordPress installation. Supports split directory layouts.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'path'   => [ 'type' => 'string',  'description' => 'File path (absolute or relative to ABSPATH).' ],
					'offset' => [ 'type' => 'integer', 'description' => 'Start reading from this byte offset.' ],
					'length' => [ 'type' => 'integer', 'description' => 'Max bytes to read (default: 100000). Max 1MB.' ],
				],
				'required' => [ 'path' ],
			],
		],
		function ( array $args ) use ( $resolve_path ): array {
			$path = $resolve_path( $args['path'] ?? '' );
			if ( ! is_file( $path ) ) throw new Exception( "Not a file: {$path}" );
			if ( ! is_readable( $path ) ) throw new Exception( "File not readable: {$path}" );

			$size   = filesize( $path );
			$offset = max( (int) ( $args['offset'] ?? 0 ), 0 );
			$length = min( max( (int) ( $args['length'] ?? 100000 ), 1 ), 1000000 );

			$handle = fopen( $path, 'r' );
			if ( $offset > 0 ) fseek( $handle, $offset );
			$content = fread( $handle, $length );
			fclose( $handle );

			return [
				'content' => [ [ 'type' => 'text', 'text' => $content ] ],
				'_meta'   => [ 'path' => $path, 'size' => $size, 'offset' => $offset, 'read' => strlen( $content ), 'truncated' => ( $offset + $length ) < $size ],
			];
		}
	);

	$tools->register(
		[
			'name'        => 'write_file',
			'description' => 'Write or overwrite a file within the WordPress installation. Creates parent directories if needed.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'path'    => [ 'type' => 'string',  'description' => 'File path.' ],
					'content' => [ 'type' => 'string',  'description' => 'File content to write.' ],
					'append'  => [ 'type' => 'boolean', 'description' => 'Append instead of overwrite (default: false).' ],
				],
				'required' => [ 'path', 'content' ],
			],
		],
		function ( array $args ) use ( $resolve_path ): array {
			$path    = $resolve_path( $args['path'] ?? '' );
			$content = $args['content'] ?? '';
			$append  = (bool) ( $args['append'] ?? false );

			$dir = dirname( $path );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				throw new Exception( "Cannot create directory: {$dir}" );
			}

			$bytes = file_put_contents( $path, $content, $append ? FILE_APPEND : 0 );
			if ( $bytes === false ) throw new Exception( "Failed to write file: {$path}" );

			return [ 'path' => $path, 'bytes' => $bytes, 'mode' => $append ? 'appended' : 'written' ];
		}
	);

	$tools->register(
		[
			'name'        => 'list_directory',
			'description' => 'List files and subdirectories within the WordPress installation.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'path'    => [ 'type' => 'string',  'description' => 'Directory path (default: ABSPATH).' ],
					'depth'   => [ 'type' => 'integer', 'description' => 'Recursion depth (default: 1, max: 3).' ],
					'pattern' => [ 'type' => 'string',  'description' => 'Filename filter (e.g., "*.php").' ],
				],
			],
		],
		function ( array $args ) use ( $resolve_path ): array {
			$path    = $resolve_path( $args['path'] ?? ABSPATH );
			$depth   = min( max( (int) ( $args['depth'] ?? 1 ), 1 ), 3 );
			$pattern = $args['pattern'] ?? '';

			if ( ! is_dir( $path ) ) throw new Exception( "Not a directory: {$path}" );

			$scan = function ( string $dir, int $remaining ) use ( &$scan, $pattern ): array {
				$entries = [];
				foreach ( scandir( $dir ) as $item ) {
					if ( $item === '.' || $item === '..' ) continue;
					$full   = $dir . '/' . $item;
					$is_dir = is_dir( $full );
					if ( $pattern && ! $is_dir && ! fnmatch( $pattern, $item ) ) continue;

					$entry = [ 'name' => $item, 'type' => $is_dir ? 'directory' : 'file', 'size' => $is_dir ? null : filesize( $full ), 'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $full ) ) ];
					if ( $is_dir && $remaining > 1 ) $entry['children'] = $scan( $full, $remaining - 1 );
					$entries[] = $entry;
				}
				return $entries;
			};

			return [ 'path' => $path, 'entries' => $scan( $path, $depth ) ];
		}
	);

	$tools->register(
		[
			'name'        => 'rename_file',
			'description' => 'Rename or move a file within the WordPress installation.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'from' => [ 'type' => 'string', 'description' => 'Current file path.' ],
					'to'   => [ 'type' => 'string', 'description' => 'New file path.' ],
				],
				'required' => [ 'from', 'to' ],
			],
		],
		function ( array $args ) use ( $resolve_path ): array {
			$from = $resolve_path( $args['from'] ?? '' );
			$to   = $resolve_path( $args['to'] ?? '' );
			if ( ! file_exists( $from ) ) throw new Exception( "Source does not exist: {$from}" );
			if ( file_exists( $to ) )     throw new Exception( "Destination already exists: {$to}" );
			if ( ! rename( $from, $to ) ) throw new Exception( "Failed to rename {$from} to {$to}" );
			return [ 'from' => $from, 'to' => $to ];
		}
	);
} );
