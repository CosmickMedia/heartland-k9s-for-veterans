<?php
/**
 * In-process checks for the plugin core review fixes (run with WP-CLI):
 *
 *   tools/wp.sh eval-file /var/www/html/wp-content/plugins/heartland-k9s-core/tests/core-fixes-test.php --user=admin
 *
 * Creates temporary posts (titles prefixed "tmp-core-fixes") and deletes them again.
 * Exits non-zero when a check fails. Local development only.
 *
 * @package HK9\Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$pass = 0;
$fail = 0;
$check = static function ( string $name, mixed $got, mixed $want ) use ( &$pass, &$fail ): void {
	if ( $got === $want ) {
		++$pass;
		WP_CLI::log( sprintf( '  PASS  %s (%s)', $name, is_scalar( $got ) ? (string) $got : wp_json_encode( $got ) ) );
	} else {
		++$fail;
		WP_CLI::log( sprintf( '  FAIL  %s (got %s, want %s)', $name, wp_json_encode( $got ), wp_json_encode( $want ) ) );
	}
};
$cleanup = [];

/* ---------------------------------------------------------------- (A) admin event list keeps undated events */
WP_CLI::log( '-- (A) event list sort' );
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
$dated  = wp_insert_post( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes dated', 'meta_input' => [ 'hk9_start' => '2030-01-01 10:00' ] ] );
$other  = wp_insert_post( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes other meta', 'meta_input' => [ '_edit_lock' => '1:1' ] ] );
$nometa = wp_insert_post( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes no meta' ] );
$cleanup = [ $dated, $other, $nometa ];
$check( 'no-meta event really has zero meta rows', count( get_post_meta( $nometa ) ), 0 );

$admin_query = static function ( array $vars ): array {
	$GLOBALS['current_screen'] = WP_Screen::get( 'edit-hk9_event' );
	$_GET                      = $vars;
	$q                         = new WP_Query();
	$GLOBALS['wp_the_query']   = $q;
	$q->query( array_merge( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids' ], $vars ) );
	$ids = array_map( 'intval', $q->posts );
	unset( $GLOBALS['current_screen'] );
	$_GET = [];
	return $ids;
};
$ids = $admin_query( [] );
$check( 'default sort keeps dated event', in_array( $dated, $ids, true ), true );
$check( 'default sort keeps event with other meta only', in_array( $other, $ids, true ), true );
$check( 'default sort keeps event with NO meta', in_array( $nometa, $ids, true ), true );
$check( 'dated event sorts first (DESC, undated after)', array_search( $dated, $ids, true ) < array_search( $other, $ids, true ), true );
$ids = $admin_query( [ 'orderby' => 'hk9_start', 'order' => 'asc' ] );
$check( 'explicit start sort keeps all three', count( array_intersect( [ $dated, $other, $nometa ], $ids ) ), 3 );
$ids = $admin_query( [ 'orderby' => 'hk9_end' ] );
$check( 'end sort keeps all three', count( array_intersect( [ $dated, $other, $nometa ], $ids ) ), 3 );
update_post_meta( $dated, 'hk9_status', 'cancelled' );
$ids = $admin_query( [ 'hk9_status' => 'cancelled' ] );
$check( 'status filter still narrows (with sort clause)', $ids, [ $dated ] );

/* ---------------------------------------------------------------- (C) Custom Fields box hidden + protected meta */
WP_CLI::log( '-- (C) custom fields box / protected meta' );
$check( 'hk9_ meta is protected', is_protected_meta( 'hk9_veteran_name', 'post' ), true );
$check( 'hk9_sec_ meta is protected', is_protected_meta( 'hk9_sec_hero_band', 'post' ), true );
$check( 'other meta untouched', is_protected_meta( 'my_field', 'post' ), false );
$check( 'custom-fields support kept (REST meta exposure)', post_type_supports( 'hk9_person', 'custom-fields' ), true );
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
$person = wp_insert_post( [ 'post_type' => 'hk9_person', 'post_status' => 'draft', 'post_title' => 'tmp-core-fixes person' ] );
$cleanup[] = $person;
add_meta_box( 'postcustom', 'Custom Fields', 'post_custom_meta_box', 'hk9_person', 'normal', 'core' ); // as core does before add_meta_boxes
do_action( 'add_meta_boxes', 'hk9_person', get_post( $person ) );
global $wp_meta_boxes;
$check( 'postcustom box removed for hk9_person', $wp_meta_boxes['hk9_person']['normal']['core']['postcustom'] ?? false, false );
$page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'tmp-core-fixes page' ] );
$cleanup[] = $page;
add_meta_box( 'postcustom', 'Custom Fields', 'post_custom_meta_box', 'page', 'normal', 'core' );
do_action( 'add_meta_boxes', 'page', get_post( $page ) );
$check( 'postcustom box kept for pages', isset( $wp_meta_boxes['page']['normal']['core']['postcustom'] ), true );
// REST-registered meta must remain editable through its auth callback despite the protection.
wp_set_current_user( 1 );
$check( 'admin can still edit protected registered meta (auth_callback wins)', current_user_can( 'edit_post_meta', $person, 'hk9_role' ), true );

/* ---------------------------------------------------------------- (K) settings link sanitiser */
WP_CLI::log( '-- (K) sanitize_link' );
$san = static fn( string $url ): string => HK9\Core\Settings\Store::sanitize_link( [ 'label' => 'x', 'url' => $url, 'post_id' => 0 ] )['url'];
$check( "bare '#' becomes empty", $san( '#' ), '' );
$check( "'#top' kept", $san( '#top' ), '#top' );
$check( 'protocol-relative gets https', $san( '//evil.com/x' ), 'https://evil.com/x' );
$check( 'relative path kept', $san( '/donate/' ), '/donate/' );
$check( 'javascript: dropped', $san( 'javascript:alert(1)' ), '' );

/* ---------------------------------------------------------------- (K) tax statement default */
$check( 'tax statement verbatim (typo corrected)', HK9\Core\Settings\Schema::defaults()['contact']['tax_statement'], 'Heartland Canines For Veterans is an IRS recognized 501(c)(3) non-profit organization. IRS EIN 47-4991572. All donations are tax deductible to the extent allowed by law. Consult your CPA if you have questions.' );

/* ---------------------------------------------------------------- (K) Dates::query bounds */
WP_CLI::log( '-- (K) events query' );
$past_open = wp_insert_post( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes past open-ended', 'meta_input' => [ 'hk9_start' => '2001-01-01 10:00' ] ] );
$future    = wp_insert_post( [ 'post_type' => 'hk9_event', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes future', 'meta_input' => [ 'hk9_start' => '2031-06-01 10:00', 'hk9_end' => '2031-06-01 12:00' ] ] );
$cleanup[] = $past_open;
$cleanup[] = $future;
$up = HK9\Core\Events\Dates::query( [ 'scope' => 'upcoming', 'count' => -1, 'return' => 'ids' ] );
$pa = HK9\Core\Events\Dates::query( [ 'scope' => 'past', 'count' => -1, 'return' => 'ids' ] );
$check( 'upcoming has future + 2030 dated', count( array_intersect( [ $future, $dated ], $up ) ), 2 );
$check( 'upcoming excludes past open-ended', in_array( $past_open, $up, true ), false );
$check( 'past has past open-ended', in_array( $past_open, $pa, true ), true );
$check( 'past excludes future', in_array( $future, $pa, true ), false );
$check( 'upcoming ordered ascending', array_search( $dated, $up, true ) < array_search( $future, $up, true ), true );

/* ---------------------------------------------------------------- (K) redirect key encoding in row actions */
WP_CLI::log( '-- (K) redirect admin URLs' );
$url = HK9\Core\Redirects\Admin::url( [ 'action' => 'edit', 'key' => '/?a=1&b=2' ] );
$check( 'Admin::url encodes the key', str_contains( $url, 'key=%2F%3Fa%3D1%26b%3D2' ), true );
parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $parsed );
$check( 'round-trips through parse_str', $parsed['key'] ?? '', '/?a=1&b=2' );

/* ---------------------------------------------------------------- (L) story meta keys */
$reg = get_registered_meta_keys( 'post', 'hk9_story' );
$check( 'story meta keys registered', array_values( array_intersect( [ 'hk9_quote', 'hk9_veteran_name', 'hk9_branch', 'hk9_canine_name' ], array_keys( $reg ) ) ), [ 'hk9_quote', 'hk9_veteran_name', 'hk9_branch', 'hk9_canine_name' ] );

/* ---------------------------------------------------------------- (B) oEmbed response refused for records */
WP_CLI::log( '-- (B) oEmbed data' );
$rec = wp_insert_post( [ 'post_type' => 'hk9_barkode', 'post_status' => 'publish', 'post_title' => 'tmp-core-fixes record', 'post_name' => 'tmp-core-fixes-record' ] );
$cleanup[] = $rec;
$check( 'oembed_response_data is false for a record', apply_filters( 'oembed_response_data', [ 'title' => 'x', 'author_name' => 'a' ], get_post( $rec ), 600, 400 ), false );
$normal = apply_filters( 'oembed_response_data', [ 'title' => 'x', 'author_name' => 'a' ], get_post( $page ), 600, 400 );
$check( 'oembed data for a normal post survives (author scrubbed)', is_array( $normal ) && 'x' === $normal['title'] && ! isset( $normal['author_name'] ), true );
$check( 'get_oembed_response_data() refuses records', get_oembed_response_data( get_post( $rec ), 600 ), false );

/* ---------------------------------------------------------------- cleanup */
foreach ( $cleanup as $id ) {
	wp_delete_post( (int) $id, true );
}
WP_CLI::log( sprintf( '== %d passed, %d failed ==', $pass, $fail ) );
if ( $fail > 0 ) {
	WP_CLI::halt( 1 );
}
