<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'purge_cache',
			'description' => 'Purge all server-side caches. Detects hosting environment and clears page cache, object cache, and CDN cache.',
			'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
		],
		function ( array $args ): array {
			$cleared = [];

			// WP Engine.
			if ( class_exists( 'WpeCommon' ) ) {
				if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache(); $cleared[] = 'WP Engine Varnish'; }
				if ( method_exists( 'WpeCommon', 'purge_memcached' ) )     { WpeCommon::purge_memcached();     $cleared[] = 'WP Engine Memcached'; }
				if ( method_exists( 'WpeCommon', 'clear_maxcdn_cache' ) )  { WpeCommon::clear_maxcdn_cache();  $cleared[] = 'WP Engine CDN'; }
			}

			// Cloudways Breeze.
			if ( class_exists( 'Breeze_PurgeVarnish' ) && method_exists( 'Breeze_PurgeVarnish', 'purge_all' ) ) {
				Breeze_PurgeVarnish::purge_all(); $cleared[] = 'Cloudways Breeze';
			}

			// LiteSpeed.
			if ( class_exists( 'LiteSpeed\Purge' ) ) { do_action( 'litespeed_purge_all' ); $cleared[] = 'LiteSpeed Cache'; }

			// WP Super Cache.
			if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); $cleared[] = 'WP Super Cache'; }

			// W3 Total Cache.
			if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); $cleared[] = 'W3 Total Cache'; }

			// WP Fastest Cache.
			if ( class_exists( 'WpFastestCache' ) ) { do_action( 'wpfc_clear_all_cache' ); $cleared[] = 'WP Fastest Cache'; }

			// Cache Party warm.
			if ( class_exists( 'CacheParty\\Warmer\\Warmer_Client' ) ) {
				$warmer = new \CacheParty\Warmer\Warmer_Client();
				$warmer->warm_site();
				$cleared[] = 'Cache Party warm triggered';
			}

			// WordPress core object cache.
			wp_cache_flush();
			$cleared[] = 'WordPress object cache';

			$host = 'Generic';
			if ( class_exists( 'WpeCommon' ) ) $host = 'WP Engine';
			elseif ( class_exists( 'Breeze_PurgeVarnish' ) ) $host = 'Cloudways';
			elseif ( isset( $_SERVER['HTTP_X_FW_SERVER'] ) && strpos( $_SERVER['HTTP_X_FW_SERVER'], 'Flywheel' ) !== false ) $host = 'Flywheel';

			return [ 'success' => true, 'cleared' => $cleared, 'host' => $host, 'timestamp' => current_time( 'c' ) ];
		}
	);
} );
