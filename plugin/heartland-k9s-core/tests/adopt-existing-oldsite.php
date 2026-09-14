<?php
/**
 * LOCAL-ONLY fixture: rebuild the shape of the client's live site (heartlandk9s.org,
 * Avada) on the emptied docker stack so the "existing site" migration mode can be
 * simulated end to end. Run with:
 *
 *   tools/wp.sh eval-file /var/www/html/wp-content/plugins/heartland-k9s-core/tests/adopt-existing-oldsite.php [payload-dir]
 *
 * Creates pages with the LIVE post ids + slugs the payload keys reference (Avada
 * shortcode content, Avada page templates), attachments with the live ids at the
 * live upload paths (files copied from the payload), an "Old Menu" assigned to the
 * primary location, and the live Reading settings (page 6 = front page). Nothing
 * here carries real registry data. Never run against a real site.
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
if ( ! is_file( $payload_dir . '/manifest.json' ) ) {
	fwrite( STDERR, "No manifest.json in {$payload_dir}\n" );
	exit( 1 );
}

$fusion = static fn( string $title ): string => '[fusion_builder_container hundred_percent="no" equal_height_columns="no"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_text]<h1>' . $title . '</h1><p>Old Avada builder content for ' . $title . ' (pre-migration state).</p>[/fusion_text][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]';

/* ---------------------------------------------------------------- pages */

// [id, slug, title, status, avada template meta]
$pages = [
	[ 6, 'home', 'Home', 'publish', '100-width.php' ],
	[ 16, '5-questions', '5 Questions', 'publish', 'default' ],
	[ 2032, 'contact', 'Contact', 'publish', 'default' ],
	[ 2944, 'barkode', 'BarKode', 'publish', '100-width.php' ],
	[ 3, 'privacy-policy', 'Privacy Policy', 'publish', '' ],
	[ 2910, 'larry-and-archie-service-k9', 'Registry page A', 'publish', '100-width.php' ],
	[ 3675, 'barkode-mosby-hk9t26-01', 'Registry page B', 'publish', 'default' ],
	[ 2483, 'events', 'Events', 'publish', 'default' ],
	// Slug-only match: the payload id (2120) does not exist here, the slug does.
	[ 9999, 'donate', 'Donate', 'publish', 'default' ],
	// Id exists but the slug was renamed on the old site: must warn and NOT be adopted.
	[ 2072, 'back-the-pack-old', 'Back the Pack (renamed)', 'publish', 'default' ],
];
$created = [];
foreach ( $pages as [ $id, $slug, $title, $status, $tpl ] ) {
	$existing = get_post( $id );
	if ( $existing ) {
		wp_delete_post( $id, true );
	}
	$new = wp_insert_post(
		[
			'import_id'    => $id,
			'post_type'    => 'page',
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $fusion( $title ),
			'post_excerpt' => '',
			'post_date'    => '2021-01-01 10:00:00',
			'post_author'  => 1,
			'menu_order'   => 5,
		],
		true
	);
	if ( is_wp_error( $new ) || (int) $new !== $id ) {
		fwrite( STDERR, sprintf( "Could not create page #%d (%s): %s\n", $id, $slug, is_wp_error( $new ) ? $new->get_error_message() : 'got #' . (int) $new ) );
		exit( 1 );
	}
	if ( '' !== $tpl ) {
		update_post_meta( $id, '_wp_page_template', $tpl );
	}
	update_post_meta( $id, 'pyre_page_title', 'yes' ); // Avada leaves its own meta behind; must survive untouched.
	$created['pages'][] = $id;
}

// A trashed page carrying a payload id/slug: never adopted (core renames the slug to *__trashed).
$trashed = wp_insert_post(
	[
		'import_id'    => 2124,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'volunteer',
		'post_title'   => 'Volunteer (old, trashed)',
		'post_content' => $fusion( 'Volunteer' ),
	],
	true
);
if ( ! is_wp_error( $trashed ) ) {
	wp_trash_post( 2124 );
	$created['trashed'] = 2124;
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', 6 );
update_option( 'page_for_posts', 0 );
update_option( 'wp_page_for_privacy_policy', 3 );

/* ----------------------------------------------------------- attachments */

$uploads = wp_upload_dir();
// [id, live subdir, basename, payload media dir, title, alt]
$media = [
	[ 3028, '2023/05', 'Concept-1-rocker-outlined-2.png', 'live__media__3028', 'Concept 1 rocker outlined (2)', '' ],
	[ 1942, '2020/01', 'Heartland-Logo-150px.png', 'live__media__1942', 'Heartland-Logo-150px', 'Old logo alt' ],
	[ 1963, '2020/01', 'about.jpg', 'live__media__1963', 'about', 'Old alt text' ],
	// A byte-identical pair that exists TWICE on the live site (same file uploaded in 2020 and
	// again in 2022): both must be adopted by their own id, neither shared with the other.
	[ 2149, '2020/03', 'Heartland-Logo-200px.png', 'live__media__2149', 'Heartland-Logo-200px', 'Heartland Canines for Veterans' ],
	[ 2806, '2022/12', 'Heartland-Logo-200px.png', 'live__media__2806', 'Heartland-Logo-200px', '' ],
];
// Live id gone, but identical bytes under another name/id: adopted through the sha256 index.
$by_sha = [ 555, '2019/12', 'old-background.jpg', 'live__media__1961', 'bg2.jpg', 'Old background' ];

$only_thumbnail = static fn( $sizes ) => array_intersect_key( (array) $sizes, [ 'thumbnail' => 1 ] );
add_filter( 'intermediate_image_sizes_advanced', $only_thumbnail, 999 ); // The old site never had our theme's sizes.
add_filter( 'image_editor_output_format', '__return_empty_array', 999 ); // ...nor its WebP output (1.3.0): -scaled stays JPEG as on the live site.

$insert_attachment = static function ( int $id, string $subdir, string $basename, string $src, string $title, string $alt ) use ( $uploads, &$created ): void {
	$dir = trailingslashit( $uploads['basedir'] ) . $subdir;
	wp_mkdir_p( $dir );
	$dest = $dir . '/' . $basename;
	if ( ! is_file( $src ) ) {
		fwrite( STDERR, "Missing payload file {$src}\n" );
		exit( 1 );
	}
	if ( ! copy( $src, $dest ) ) {
		fwrite( STDERR, "Could not copy {$src} -> {$dest}\n" );
		exit( 1 );
	}
	$existing = get_post( $id );
	if ( $existing ) {
		wp_delete_post( $id, true );
	}
	$type = wp_check_filetype( $basename );
	$new  = wp_insert_attachment(
		[
			'import_id'      => $id,
			'post_mime_type' => (string) $type['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_date'      => '2021-01-01 10:00:00',
			'guid'           => trailingslashit( $uploads['baseurl'] ) . $subdir . '/' . $basename,
		],
		$dest,
		0,
		true
	);
	if ( is_wp_error( $new ) || (int) $new !== $id ) {
		fwrite( STDERR, sprintf( "Could not create attachment #%d: %s\n", $id, is_wp_error( $new ) ? $new->get_error_message() : 'got #' . (int) $new ) );
		exit( 1 );
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
	if ( '' !== $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	$created['media'][] = $id;
};

foreach ( $media as [ $id, $subdir, $basename, $pdir, $title, $alt ] ) {
	$insert_attachment( $id, $subdir, $basename, $payload_dir . '/media/' . $pdir . '/' . $basename, $title, $alt );
}
[ $id, $subdir, $basename, $pdir, $srcname, $alt ] = $by_sha;
$insert_attachment( $id, $subdir, $basename, $payload_dir . '/media/' . $pdir . '/' . $srcname, 'Old background', $alt );
remove_filter( 'intermediate_image_sizes_advanced', $only_thumbnail, 999 );
remove_filter( 'image_editor_output_format', '__return_empty_array', 999 );

/* ------------------------------------------------------------------ menu */

$old_menu = wp_get_nav_menu_object( 'Old Menu' );
$menu_id  = $old_menu ? (int) $old_menu->term_id : (int) wp_create_nav_menu( 'Old Menu' );
$items    = [
	[ 'post_type', 6, 'Home', '' ],
	[ 'post_type', 2032, 'Contact', '' ],
	[ 'custom', 0, 'Facebook', 'https://www.facebook.com/' ],
];
foreach ( $items as $i => [ $kind, $object, $title, $url ] ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		[
			'menu-item-status'    => 'publish',
			'menu-item-type'      => $kind,
			'menu-item-object'    => 'post_type' === $kind ? 'page' : 'custom',
			'menu-item-object-id' => $object,
			'menu-item-url'       => $url,
			'menu-item-title'     => $title,
			'menu-item-position'  => $i + 1,
		]
	);
}
set_theme_mod( 'nav_menu_locations', [ 'primary' => $menu_id ] );
$created['menu'] = $menu_id;

clean_post_cache( 6 );
flush_rewrite_rules( false );

echo wp_json_encode(
	[
		'pages'         => count( $created['pages'] ?? [] ),
		'trashed'       => $created['trashed'] ?? 0,
		'media'         => count( $created['media'] ?? [] ),
		'menu'          => $menu_id,
		'menu_items'    => count( wp_get_nav_menu_items( $menu_id ) ?: [] ),
		'page_on_front' => (int) get_option( 'page_on_front' ),
		'show_on_front' => (string) get_option( 'show_on_front' ),
	]
) . "\n";
