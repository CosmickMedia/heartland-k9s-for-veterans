<?php
/**
 * LOCAL-ONLY probe: a registry page whose in-place conversion (page -> hk9_barkode)
 * FAILS must stay a page with its page template — nothing half-converted, no map
 * row left behind. Run with:
 *
 *   tools/wp.sh eval-file .../tests/adopt-existing-convert-fail.php <payload-dir> <record-key> <page-id>
 *
 * Forces wp_update_post() to reject the conversion (the `wp_insert_post_empty_content`
 * filter returns true for the record type), runs posts_stub's adoption for that one
 * record in a throw-away context (nothing is persisted: no state, no run history)
 * and prints what the page looks like afterwards. Never run against a real site.
 *
 * @package HK9\Core
 */

use HK9\Core\Import\Context;
use HK9\Core\Import\Log;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;
use HK9\Core\Import\State;
use HK9\Core\Import\Steps\PostsStub;

if ( ! defined( 'HK9_LOCAL_DEV' ) || ! HK9_LOCAL_DEV ) {
	fwrite( STDERR, "Refusing: HK9_LOCAL_DEV is not defined (local docker stack only).\n" );
	exit( 1 );
}

$payload_dir = isset( $args[0] ) ? rtrim( (string) $args[0], '/' ) : WP_CONTENT_DIR . '/hk9-payload';
$key         = (string) ( $args[1] ?? 'live:barkode:2910' );
$page_id     = (int) ( $args[2] ?? 2910 );

$manifest = Manifest::load( $payload_dir );
if ( ! $manifest instanceof Manifest ) {
	fwrite( STDERR, 'Payload: ' . $manifest->get_error_message() . "\n" );
	exit( 1 );
}
$record = $manifest->get( $key );
if ( ! $record ) {
	fwrite( STDERR, "No record {$key}\n" );
	exit( 1 );
}
$type = (string) $record['type'];

$before = [
	'post_type' => get_post_type( $page_id ),
	'template'  => (string) get_post_meta( $page_id, '_wp_page_template', true ),
	'status'    => get_post_status( $page_id ),
];

// Make the conversion fail: wp_insert_post() returns WP_Error('empty_content') before writing.
add_filter(
	'wp_insert_post_empty_content',
	static function ( $maybe_empty, array $postarr ) use ( $type ) {
		return ( $postarr['post_type'] ?? '' ) === $type ? true : $maybe_empty;
	},
	10,
	2
);

Map::ensure();
$state                     = State::blank();
$state['run_id']           = 'probe' . substr( md5( (string) microtime( true ) ), 0, 8 );
$state['mode']             = [
	'dry_run'   => false,
	'overwrite' => false,
	'adopt'     => true,
];
$state['user_id']          = 1;
$state['failed_keys']      = [];
$state['status']           = State::STATUS_RUNNING;
Log::ensure_dir();
$log                       = new Log( $state['run_id'] );
$ctx                       = new Context( $manifest, $state, $log, microtime( true ) + 60, 1000 );
$step                      = new PostsStub( $ctx );
$process                   = new ReflectionMethod( $step, 'process' );
$process->setAccessible( true );
$process->invoke( $step, $key );

clean_post_cache( $page_id );
$row = Map::get( $key );
if ( is_file( $log->file() ) ) {
	unlink( $log->file() ); // Throw-away context: leave no probe log behind.
}

echo wp_json_encode(
	[
		'before'      => $before,
		'fail_count'  => (int) ( $state['counts']['posts_stub']['fail'] ?? 0 ),
		'adopt_count' => (int) ( $state['counts']['posts_stub']['adopt'] ?? 0 ),
		'error'       => (string) ( $state['errors'][0]['message'] ?? '' ),
		'post_type'   => get_post_type( $page_id ),
		'template'    => (string) get_post_meta( $page_id, '_wp_page_template', true ),
		'status'      => get_post_status( $page_id ),
		'map_row'     => $row ? (string) $row['status'] : 'none',
		'marker'      => (string) get_post_meta( $page_id, '_hk9_source_key', true ),
	]
) . "\n";
