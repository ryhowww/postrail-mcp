<?php

namespace PostRailMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auth {

	/**
	 * Validate the shared secret from the Authorization header.
	 * Used as the permission_callback for REST routes.
	 */
	public function check_secret( \WP_REST_Request $request ): bool {
		$header = $request->get_header( 'authorization' );
		if ( empty( $header ) || strpos( $header, 'Bearer ' ) !== 0 ) {
			return false;
		}

		$token  = substr( $header, 7 );
		$secret = get_option( 'postrail_mcp_secret', '' );

		if ( empty( $secret ) || ! hash_equals( $secret, $token ) ) {
			return false;
		}

		// Elevate to first admin user for tool execution.
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
		if ( ! empty( $admins ) ) {
			wp_set_current_user( $admins[0]->ID );
		}

		return true;
	}

	/**
	 * Get the stored shared secret.
	 */
	public function get_secret(): string {
		return get_option( 'postrail_mcp_secret', '' );
	}

	/**
	 * Regenerate the shared secret.
	 */
	public function regenerate_secret(): string {
		$secret = wp_generate_password( 64, false );
		update_option( 'postrail_mcp_secret', $secret );
		return $secret;
	}
}
