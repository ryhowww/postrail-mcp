<?php

namespace PostRailMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tools {

	private array $tools    = [];
	private array $handlers = [];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'load_tools' ] );
	}

	public function load_tools(): void {
		$tool_files = [
			POSTRAIL_MCP_PATH . 'includes/tools/site-info.php',
			POSTRAIL_MCP_PATH . 'includes/tools/plugins.php',
			POSTRAIL_MCP_PATH . 'includes/tools/content.php',
			POSTRAIL_MCP_PATH . 'includes/tools/options.php',
			POSTRAIL_MCP_PATH . 'includes/tools/taxonomies.php',
			POSTRAIL_MCP_PATH . 'includes/tools/media.php',
			POSTRAIL_MCP_PATH . 'includes/tools/filesystem.php',
			POSTRAIL_MCP_PATH . 'includes/tools/database.php',
			POSTRAIL_MCP_PATH . 'includes/tools/error-log.php',
			POSTRAIL_MCP_PATH . 'includes/tools/wp-cli.php',
			POSTRAIL_MCP_PATH . 'includes/tools/cache.php',
		];

		foreach ( $tool_files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		do_action( 'postrail_mcp_register_tools', $this );
	}

	public function register( array $definition, callable $handler ): void {
		$name = $definition['name'] ?? '';
		if ( empty( $name ) ) {
			return;
		}
		$this->tools[ $name ]    = $definition;
		$this->handlers[ $name ] = $handler;
	}

	/**
	 * Get all tool definitions. No access mode filtering — PostRail handles that.
	 */
	public function get_list(): array {
		$result = [];
		foreach ( $this->tools as $name => $def ) {
			$result[] = [
				'name'        => $name,
				'description' => $def['description'] ?? '',
				'inputSchema' => $def['inputSchema'] ?? [ 'type' => 'object', 'properties' => new \stdClass() ],
			];
		}
		return $result;
	}

	public function execute( string $name, array $args ): array {
		if ( ! isset( $this->handlers[ $name ] ) ) {
			throw new \Exception( "Unknown tool: {$name}" );
		}

		$result = call_user_func( $this->handlers[ $name ], $args );
		return $this->format_result( $result );
	}

	private function format_result( $result ): array {
		if ( is_string( $result ) ) {
			return [ 'content' => [ [ 'type' => 'text', 'text' => $result ] ] ];
		}
		if ( is_array( $result ) && isset( $result['content'] ) ) {
			return $result;
		}
		if ( is_array( $result ) || is_object( $result ) ) {
			return [ 'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ] ] ];
		}
		return [ 'content' => [ [ 'type' => 'text', 'text' => (string) $result ] ] ];
	}
}
