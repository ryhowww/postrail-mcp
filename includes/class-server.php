<?php

namespace PostRailMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Server {

	private Auth  $auth;
	private Tools $tools;

	private array $supported_versions = [ '2024-11-05', '2025-03-26', '2025-06-18' ];

	public function __construct( Auth $auth, Tools $tools ) {
		$this->auth  = $auth;
		$this->tools = $tools;

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// MCP endpoint — shared secret auth.
		register_rest_route( 'postrail-mcp/v1', '/mcp', [
			'methods'             => [ 'POST', 'GET', 'DELETE' ],
			'callback'            => [ $this, 'handle_request' ],
			'permission_callback' => [ $this->auth, 'check_secret' ],
		] );

		// Health check — no auth required.
		register_rest_route( 'postrail-mcp/v1', '/health', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_health' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function handle_health(): \WP_REST_Response {
		return new \WP_REST_Response( [
			'status'            => 'ok',
			'plugin'            => 'postrail-mcp',
			'version'           => POSTRAIL_MCP_VERSION,
			'wp'                => get_bloginfo( 'version' ),
			'php'               => PHP_VERSION,
			'site'              => home_url(),
			'secret_configured' => ! empty( get_option( 'postrail_mcp_secret', '' ) ),
		], 200 );
	}

	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		$method = $request->get_method();

		if ( $method === 'DELETE' ) {
			return new \WP_REST_Response( null, 204 );
		}

		if ( $method === 'GET' ) {
			return new \WP_REST_Response( [
				'name'    => 'PostRail MCP',
				'version' => POSTRAIL_MCP_VERSION,
				'status'  => 'ok',
			], 200 );
		}

		// POST — JSON-RPC.
		$raw = $request->get_body();
		if ( empty( $raw ) ) {
			return $this->rpc_error( null, -32700, 'Parse error: empty body' );
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $this->rpc_error( null, -32700, 'Parse error: invalid JSON' );
		}

		$id         = $data['id'] ?? null;
		$rpc_method = $data['method'] ?? null;
		$params     = $data['params'] ?? [];

		if ( ! $rpc_method ) {
			return $this->rpc_error( $id, -32600, 'Invalid Request: method missing' );
		}

		try {
			switch ( $rpc_method ) {
				case 'initialize':
					return $this->handle_initialize( $id, $params );

				case 'notifications/initialized':
				case 'notifications/cancelled':
					return new \WP_REST_Response( null, 202 );

				case 'tools/list':
					return $this->handle_tools_list( $id );

				case 'tools/call':
					return $this->handle_tools_call( $id, $params );

				case 'resources/list':
					return $this->rpc_success( $id, [ 'resources' => [] ] );

				case 'prompts/list':
					return $this->rpc_success( $id, [ 'prompts' => [] ] );

				default:
					return $this->rpc_error( $id, -32601, "Method not found: {$rpc_method}" );
			}
		} catch ( \Exception $e ) {
			return $this->rpc_error( $id, -32603, $e->getMessage() );
		}
	}

	private function handle_initialize( $id, array $params ): \WP_REST_Response {
		$client_version = $params['protocolVersion'] ?? '';
		$negotiated = in_array( $client_version, $this->supported_versions, true )
			? $client_version
			: end( $this->supported_versions );

		$response = $this->rpc_success( $id, [
			'protocolVersion' => $negotiated,
			'serverInfo'      => [
				'name'    => 'PostRail MCP - ' . get_bloginfo( 'name' ),
				'version' => POSTRAIL_MCP_VERSION,
			],
			'capabilities'    => [
				'tools' => new \stdClass(),
			],
		] );

		$response->header( 'Mcp-Session-Id', wp_generate_uuid4() );
		return $response;
	}

	private function handle_tools_list( $id ): \WP_REST_Response {
		// No access mode filtering — PostRail handles permissions centrally.
		$tools = $this->tools->get_list();
		return $this->rpc_success( $id, [ 'tools' => $tools ] );
	}

	private function handle_tools_call( $id, array $params ): \WP_REST_Response {
		$tool_name = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? [];

		if ( empty( $tool_name ) ) {
			return $this->rpc_error( $id, -32602, 'Missing parameter: name' );
		}

		try {
			$result = $this->tools->execute( $tool_name, $arguments );
			return $this->rpc_success( $id, $result );
		} catch ( \Exception $e ) {
			return $this->rpc_error( $id, -32603, $e->getMessage() );
		}
	}

	private function rpc_success( $id, array $result ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		], 200 );
	}

	private function rpc_error( $id, int $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [ 'code' => $code, 'message' => $message ],
		], 200 );
	}
}
