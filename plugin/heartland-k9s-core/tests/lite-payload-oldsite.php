<?php
/**
 * LOCAL-ONLY fixture for the content-only ("lite") payload simulation: the old
 * heartlandk9s.org site with EVERY live attachment present — all 244 live:media:<id>
 * records of the payload, at their live upload paths (2023/05/name.png, derived
 * from the media index source_url) and with their live ids — on top of the pages,
 * registry pages and menu that tests/adopt-existing-oldsite.php builds. Run with:
 *
 *   tools/wp.sh eval-file /var/www/html/wp-content/plugins/heartland-k9s-core/tests/lite-payload-oldsite.php [full-payload-dir]
 *
 * The files are copied from the FULL payload (media/live__media__<id>/<file>); the
 * attachments get the live title/alt/date and only the thumbnail size (the old site
 * never had our theme's sizes; big images get their -scaled rendition exactly as on
 * the live site). Sensitive records receive a generic title. Never run against a
 * real site.
 *
 * @package HK9\Core
 */

if ( ! defined( 'HK9_LOCAL_DEV' ) || ! HK9_LOCAL_DEV ) {
	fwrite( STDERR, "Refusing: HK9_LOCAL_DEV is not defined (local docker stack only).\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$payload_dir = isset( $args[0] ) ? rtrim( (string) $args[0], '/' ) : WP_CONTENT_DIR . '/hk9-payload';
if ( ! is_file( $payload_dir . '/manifest.json' ) || ! is_file( $payload_dir . '/media-index.json' ) ) {
	fwrite( STDERR, "Need manifest.json + media-index.json in {$payload_dir} (the FULL payload).\n" );
	exit( 1 );
}

/* ------------------------------------------ pages, registry pages, menu, 6 attachments */

ob_start();
$args = [ $payload_dir ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
require __DIR__ . '/adopt-existing-oldsite.php';
$base = json_decode( (string) trim( (string) ob_get_clean() ), true );
if ( ! is_array( $base ) || empty( $base['pages'] ) ) {
	fwrite( STDERR, "adopt-existing-oldsite.php did not report its fixture.\n" );
	exit( 1 );
}

/* ------------------------------------------------------ every live attachment */

$manifest = json_decode( (string) file_get_contents( $payload_dir . '/manifest.json' ), true );
$index    = json_decode( (string) file_get_contents( $payload_dir . '/media-index.json' ), true );
$items    = is_array( $index['items'] ?? null ) ? $index['items'] : [];
$uploads  = wp_upload_dir();

$only_thumbnail = static fn( $sizes ) => array_intersect_key( (array) $sizes, [ 'thumbnail' => 1 ] );
add_filter( 'intermediate_image_sizes_advanced', $only_thumbnail, 999 );

$created = 0;
$kept    = 0;
$paths   = [];
$errors  = [];
foreach ( (array) ( $manifest['records'] ?? [] ) as $record ) {
	$key = (string) ( $record['key'] ?? '' );
	if ( 'attachment' !== ( $record['type'] ?? '' ) || ! preg_match( '/^live:media:(\d+)$/', $key, $m ) ) {
		continue;
	}
	$id   = (int) $m[1];
	$item = $items[ $key ] ?? null;
	$url  = (string) ( $item['source_url'] ?? $item['served_full_url'] ?? '' );
	if ( ! preg_match( '#^https?://[^/]+/wp-content/uploads/(.+)$#i', $url, $u ) ) {
		$errors[] = "{$key}: no uploads source_url in the media index";
		continue;
	}
	$live_path = ltrim( rawurldecode( strtok( $u[1], '?' ) ), '/' );
	$src       = $payload_dir . '/' . (string) $record['file'];
	if ( ! is_file( $src ) ) {
		$errors[] = "{$key}: payload file missing ({$record['file']})";
		continue;
	}
	$existing = get_post( $id );
	if ( $existing instanceof WP_Post && 'attachment' === $existing->post_type ) {
		++$kept; // Built by adopt-existing-oldsite.php already (3028, 1942, 1963, 2149, 2806).
		$paths[ $id ] = (string) get_post_meta( $id, '_wp_attached_file', true );
		continue;
	}
	if ( $existing instanceof WP_Post ) {
		wp_delete_post( $id, true );
	}
	$dest = trailingslashit( $uploads['basedir'] ) . $live_path;
	wp_mkdir_p( dirname( $dest ) );
	if ( ! copy( $src, $dest ) ) {
		$errors[] = "{$key}: could not copy to {$live_path}";
		continue;
	}
	$sensitive = ! empty( $record['sensitive'] );
	$title     = $sensitive ? 'Registry image' : (string) ( $record['title'] ?? pathinfo( $live_path, PATHINFO_FILENAME ) );
	$date      = (string) ( $record['date'] ?? '' );
	$type      = wp_check_filetype( basename( $live_path ) );
	$post      = [
		'import_id'      => $id,
		'post_mime_type' => (string) ( $type['type'] ?: ( $record['mime'] ?? 'application/octet-stream' ) ),
		'post_title'     => $title,
		'post_status'    => 'inherit',
		'guid'           => trailingslashit( $uploads['baseurl'] ) . $live_path,
	];
	if ( '' !== $date && false !== strtotime( $date ) ) {
		$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
		$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] );
	}
	$new = wp_insert_attachment( $post, $dest, 0, true );
	if ( is_wp_error( $new ) || (int) $new !== $id ) {
		$errors[] = sprintf( '%s: could not create attachment #%d (%s)', $key, $id, is_wp_error( $new ) ? $new->get_error_message() : 'got #' . (int) $new );
		continue;
	}
	$meta = wp_generate_attachment_metadata( $id, $dest );
	if ( is_array( $meta ) && $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}
	if ( ! $sensitive && '' !== (string) ( $record['alt'] ?? '' ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', (string) $record['alt'] );
	}
	$paths[ $id ] = (string) get_post_meta( $id, '_wp_attached_file', true );
	++$created;
}
remove_filter( 'intermediate_image_sizes_advanced', $only_thumbnail, 999 );

foreach ( $errors as $e ) {
	fwrite( STDERR, $e . "\n" );
}
if ( $errors ) {
	exit( 1 );
}

ksort( $paths );
echo wp_json_encode(
	[
		'pages'         => (int) $base['pages'],
		'menu_items'    => (int) $base['menu_items'],
		'page_on_front' => (int) get_option( 'page_on_front' ),
		'show_on_front' => (string) get_option( 'show_on_front' ),
		'live_media'    => count( $paths ),
		'created'       => $created,
		'kept'          => $kept,
		'attachments'   => (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 ),
		'paths_hash'    => md5( wp_json_encode( $paths ) ),
	]
) . "\n";
