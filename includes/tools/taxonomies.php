<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'list_terms',
			'description' => 'List terms for a taxonomy (e.g., category, post_tag). Returns ID, name, slug, count, parent.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'taxonomy' => [ 'type' => 'string',  'description' => 'Taxonomy slug.' ],
					'search'   => [ 'type' => 'string',  'description' => 'Search term.' ],
					'parent'   => [ 'type' => 'integer', 'description' => 'Parent term ID.' ],
					'limit'    => [ 'type' => 'integer', 'description' => 'Max results (default: 100).' ],
				],
				'required' => [ 'taxonomy' ],
			],
		],
		function ( array $args ): array {
			$query = [
				'taxonomy'   => sanitize_text_field( $args['taxonomy'] ),
				'number'     => min( max( (int) ( $args['limit'] ?? 100 ), 1 ), 500 ),
				'hide_empty' => false,
			];
			if ( ! empty( $args['search'] ) ) $query['search'] = $args['search'];
			if ( isset( $args['parent'] ) )   $query['parent'] = (int) $args['parent'];

			$terms = get_terms( $query );
			if ( is_wp_error( $terms ) ) throw new Exception( $terms->get_error_message() );

			return array_map( function ( $t ) {
				return [ 'term_id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count, 'parent' => $t->parent ];
			}, $terms );
		}
	);

	$tools->register(
		[
			'name'        => 'get_post_terms',
			'description' => 'Get all terms attached to a post, grouped by taxonomy.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'ID'       => [ 'type' => 'integer', 'description' => 'Post ID.' ],
					'taxonomy' => [ 'type' => 'string',  'description' => 'Optional: specific taxonomy.' ],
				],
				'required' => [ 'ID' ],
			],
		],
		function ( array $args ): array {
			$id   = (int) ( $args['ID'] ?? 0 );
			$post = get_post( $id );
			if ( ! $post ) throw new Exception( 'Post not found.' );

			$taxonomies = ! empty( $args['taxonomy'] ) ? [ $args['taxonomy'] ] : get_object_taxonomies( $post->post_type );
			$result     = [];

			foreach ( $taxonomies as $tax ) {
				$terms = wp_get_post_terms( $id, $tax );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$result[ $tax ] = array_map( function ( $t ) {
						return [ 'term_id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
					}, $terms );
				}
			}
			return $result;
		}
	);
} );
