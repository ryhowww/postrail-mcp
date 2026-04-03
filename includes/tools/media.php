<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'postrail_mcp_register_tools', function ( PostRailMCP\Tools $tools ) {

	$tools->register(
		[
			'name'        => 'list_media',
			'description' => 'List media library items with URL, dimensions, alt text, file size, and MIME type.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search' => [ 'type' => 'string',  'description' => 'Search term.' ],
					'mime'   => [ 'type' => 'string',  'description' => 'MIME type filter (e.g., image, image/jpeg).' ],
					'limit'  => [ 'type' => 'integer', 'description' => 'Max results (default: 20).' ],
					'offset' => [ 'type' => 'integer', 'description' => 'Offset for pagination.' ],
				],
			],
		],
		function ( array $args ): array {
			$query = [
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 100 ),
				'offset'         => max( (int) ( $args['offset'] ?? 0 ), 0 ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			];

			if ( ! empty( $args['search'] ) ) $query['s']         = $args['search'];
			if ( ! empty( $args['mime'] ) )   $query['post_mime_type'] = $args['mime'];

			$items  = get_posts( $query );
			$result = [];

			foreach ( $items as $item ) {
				$meta = wp_get_attachment_metadata( $item->ID );
				$result[] = [
					'ID'       => $item->ID,
					'title'    => $item->post_title,
					'url'      => wp_get_attachment_url( $item->ID ),
					'mime'     => $item->post_mime_type,
					'width'    => $meta['width'] ?? null,
					'height'   => $meta['height'] ?? null,
					'alt'      => get_post_meta( $item->ID, '_wp_attachment_image_alt', true ),
					'filesize' => $meta['filesize'] ?? null,
					'date'     => $item->post_date,
				];
			}
			return $result;
		}
	);
} );
