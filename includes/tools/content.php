<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sideload a remote image and set it as the post's featured image.
 */
function postrail_mcp_sideload_featured_image( int $post_id, string $url ): void {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
	if ( ! is_wp_error( $attachment_id ) ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
}

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'list_posts',
			'description' => 'List posts/pages/CPTs. Returns ID, title, status, date, URL, excerpt.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_type'   => [ 'type' => 'string',  'description' => 'Post type slug (default: post).' ],
					'post_status' => [ 'type' => 'string',  'description' => 'Status: publish, draft, pending, trash, any (default: any).' ],
					'search'      => [ 'type' => 'string',  'description' => 'Search term.' ],
					'author'      => [ 'type' => 'integer', 'description' => 'Author user ID.' ],
					'limit'       => [ 'type' => 'integer', 'description' => 'Number of results (default: 20, max: 100).' ],
					'offset'      => [ 'type' => 'integer', 'description' => 'Offset for pagination.' ],
					'orderby'     => [ 'type' => 'string',  'description' => 'Order by: date, title, modified, ID (default: date).' ],
					'order'       => [ 'type' => 'string',  'description' => 'ASC or DESC (default: DESC).' ],
				],
			],
		],
		function ( array $args ): array {
			$query_args = [
				'post_type'      => $args['post_type'] ?? 'post',
				'post_status'    => $args['post_status'] ?? 'any',
				'posts_per_page' => min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 100 ),
				'offset'         => max( (int) ( $args['offset'] ?? 0 ), 0 ),
				'orderby'        => $args['orderby'] ?? 'date',
				'order'          => $args['order'] ?? 'DESC',
			];

			if ( ! empty( $args['search'] ) )  $query_args['s']      = $args['search'];
			if ( ! empty( $args['author'] ) )   $query_args['author'] = (int) $args['author'];

			$posts  = get_posts( $query_args );
			$result = [];

			foreach ( $posts as $post ) {
				$result[] = [
					'ID'        => $post->ID,
					'title'     => $post->post_title,
					'status'    => $post->post_status,
					'type'      => $post->post_type,
					'date'      => $post->post_date,
					'modified'  => $post->post_modified,
					'url'       => get_permalink( $post ),
					'excerpt'   => wp_trim_words( $post->post_excerpt ?: $post->post_content, 30 ),
					'author'    => (int) $post->post_author,
					'parent'    => (int) $post->post_parent,
				];
			}
			return $result;
		}
	);

	$tools->register(
		[
			'name'        => 'get_post',
			'description' => 'Get a single post/page by ID with full content, meta, featured image, and taxonomies.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [ 'ID' => [ 'type' => 'integer', 'description' => 'Post ID.' ] ],
				'required'   => [ 'ID' ],
			],
		],
		function ( array $args ): array {
			$post = get_post( (int) ( $args['ID'] ?? 0 ) );
			if ( ! $post ) throw new Exception( 'Post not found.' );

			$meta  = get_post_meta( $post->ID );
			$clean = [];
			foreach ( $meta as $key => $values ) {
				if ( strpos( $key, '_' ) === 0 && ! in_array( $key, [ '_wp_page_template', '_thumbnail_id', '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' ], true ) ) {
					continue;
				}
				$clean[ $key ] = count( $values ) === 1 ? $values[0] : $values;
			}

			$thumb_id  = get_post_thumbnail_id( $post );
			$thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : null;

			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms      = [];
			foreach ( $taxonomies as $tax ) {
				$post_terms = wp_get_post_terms( $post->ID, $tax );
				if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
					$terms[ $tax ] = array_map( function ( $t ) {
						return [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
					}, $post_terms );
				}
			}

			return [
				'ID'              => $post->ID,
				'title'           => $post->post_title,
				'content'         => $post->post_content,
				'excerpt'         => $post->post_excerpt,
				'status'          => $post->post_status,
				'type'            => $post->post_type,
				'slug'            => $post->post_name,
				'url'             => get_permalink( $post ),
				'date'            => $post->post_date,
				'modified'        => $post->post_modified,
				'author'          => (int) $post->post_author,
				'parent'          => (int) $post->post_parent,
				'template'        => get_page_template_slug( $post ) ?: null,
				'featured_image'  => $thumb_url,
				'meta'            => $clean,
				'taxonomies'      => $terms,
			];
		}
	);

	$tools->register(
		[
			'name'        => 'create_post',
			'description' => 'Create a new post, page, or custom post type. Returns the new post ID, URL, and edit URL.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_title'         => [ 'type' => 'string',  'description' => 'Post title.' ],
					'post_content'       => [ 'type' => 'string',  'description' => 'Post content (HTML).' ],
					'post_excerpt'       => [ 'type' => 'string',  'description' => 'Post excerpt.' ],
					'post_status'        => [ 'type' => 'string',  'description' => 'Status: draft, publish, pending (default: draft).' ],
					'post_type'          => [ 'type' => 'string',  'description' => 'Post type (default: post).' ],
					'post_name'          => [ 'type' => 'string',  'description' => 'URL slug.' ],
					'post_parent'        => [ 'type' => 'integer', 'description' => 'Parent post/page ID.' ],
					'meta_input'         => [ 'type' => 'object',  'description' => 'Key/value pairs for custom fields.' ],
					'featured_image_url' => [ 'type' => 'string',  'description' => 'URL of remote image to sideload as featured image.' ],
					'categories'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category term IDs.' ],
					'tags'               => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Tag term IDs.' ],
				],
				'required'   => [ 'post_title' ],
			],
		],
		function ( array $args ): array {
			$post_data = [
				'post_title'  => sanitize_text_field( $args['post_title'] ),
				'post_status' => $args['post_status'] ?? 'draft',
				'post_type'   => $args['post_type'] ?? 'post',
			];

			if ( isset( $args['post_content'] ) ) $post_data['post_content'] = $args['post_content'];
			if ( isset( $args['post_excerpt'] ) ) $post_data['post_excerpt'] = $args['post_excerpt'];
			if ( isset( $args['post_name'] ) )    $post_data['post_name']    = sanitize_title( $args['post_name'] );
			if ( isset( $args['post_parent'] ) )  $post_data['post_parent']  = (int) $args['post_parent'];
			if ( isset( $args['meta_input'] ) )   $post_data['meta_input']   = $args['meta_input'];

			$id = wp_insert_post( $post_data, true );
			if ( is_wp_error( $id ) ) throw new Exception( $id->get_error_message() );

			// Featured image sideloading
			if ( ! empty( $args['featured_image_url'] ) ) {
				postrail_mcp_sideload_featured_image( $id, $args['featured_image_url'] );
			}

			// Categories and tags
			if ( ! empty( $args['categories'] ) ) {
				wp_set_post_categories( $id, array_map( 'intval', $args['categories'] ) );
			}
			if ( ! empty( $args['tags'] ) ) {
				wp_set_post_tags( $id, array_map( 'intval', $args['tags'] ) );
			}

			return [
				'ID'       => $id,
				'url'      => get_permalink( $id ),
				'edit_url' => admin_url( "post.php?post={$id}&action=edit" ),
				'status'   => $post_data['post_status'],
			];
		}
	);

	$tools->register(
		[
			'name'        => 'update_post',
			'description' => 'Update an existing post by ID. Creates a revision before updating. Pass only the fields you want to change.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'ID'                 => [ 'type' => 'integer', 'description' => 'Post ID to update.' ],
					'post_title'         => [ 'type' => 'string' ],
					'post_content'       => [ 'type' => 'string' ],
					'post_excerpt'       => [ 'type' => 'string' ],
					'post_status'        => [ 'type' => 'string' ],
					'post_name'          => [ 'type' => 'string' ],
					'post_parent'        => [ 'type' => 'integer', 'description' => 'Parent post/page ID.' ],
					'meta_input'         => [ 'type' => 'object',  'description' => 'Custom fields to update.' ],
					'featured_image_url' => [ 'type' => 'string',  'description' => 'URL of remote image to sideload as featured image.' ],
					'categories'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category term IDs.' ],
					'tags'               => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Tag term IDs.' ],
				],
				'required'   => [ 'ID' ],
			],
		],
		function ( array $args ): array {
			$id = (int) ( $args['ID'] ?? 0 );
			if ( ! get_post( $id ) ) throw new Exception( "Post not found: {$id}" );

			// Create revision before updating for rollback safety
			wp_save_post_revision( $id );

			$post_data = [ 'ID' => $id ];
			foreach ( [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name' ] as $field ) {
				if ( isset( $args[ $field ] ) ) $post_data[ $field ] = $args[ $field ];
			}
			if ( isset( $args['post_parent'] ) ) $post_data['post_parent'] = (int) $args['post_parent'];
			if ( isset( $args['meta_input'] ) )  $post_data['meta_input']  = $args['meta_input'];

			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) throw new Exception( $result->get_error_message() );

			// Featured image sideloading
			if ( ! empty( $args['featured_image_url'] ) ) {
				postrail_mcp_sideload_featured_image( $id, $args['featured_image_url'] );
			}

			// Categories and tags
			if ( ! empty( $args['categories'] ) ) {
				wp_set_post_categories( $id, array_map( 'intval', $args['categories'] ) );
			}
			if ( ! empty( $args['tags'] ) ) {
				wp_set_post_tags( $id, array_map( 'intval', $args['tags'] ) );
			}

			return [
				'ID'       => $id,
				'url'      => get_permalink( $id ),
				'edit_url' => admin_url( "post.php?post={$id}&action=edit" ),
				'status'   => get_post_status( $id ),
			];
		}
	);

	$tools->register(
		[
			'name'        => 'delete_post',
			'description' => 'Delete a post by ID. By default moves to trash; set force=true to permanently delete.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'ID'    => [ 'type' => 'integer', 'description' => 'Post ID.' ],
					'force' => [ 'type' => 'boolean', 'description' => 'Skip trash and permanently delete (default: false).' ],
				],
				'required'   => [ 'ID' ],
			],
		],
		function ( array $args ): array {
			$id    = (int) ( $args['ID'] ?? 0 );
			$force = (bool) ( $args['force'] ?? false );

			$result = wp_delete_post( $id, $force );
			if ( ! $result ) throw new Exception( "Failed to delete post {$id}." );

			return [ 'ID' => $id, 'deleted' => true, 'force' => $force ];
		}
	);
} );
