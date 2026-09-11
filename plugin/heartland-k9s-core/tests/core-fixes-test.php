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

/* ---------------------------------------------------------------- (W4b) icon preview sprite ids */
WP_CLI::log( '-- (W4b) icon preview' );
$check( 'symbol prefix defaults to hk9-icon-', HK9\Core\Fields\Renderer::sprite_symbol_prefix(), 'hk9-icon-' );
$svg = HK9\Core\Fields\Renderer::icon_svg( 'heart' );
$check( 'icon_svg references #hk9-icon-heart', '' !== $svg && str_contains( $svg, 'icons.svg' ) && str_contains( $svg, '#hk9-icon-heart"' ), true );
$sprite = get_template_directory() . '/assets/dist/icons.svg';
$check( 'theme sprite defines that symbol', file_exists( $sprite ) && str_contains( (string) file_get_contents( $sprite ), 'id="hk9-icon-heart"' ), true );

/* ---------------------------------------------------------------- (W4b) settings link label survives a tab save */
WP_CLI::log( '-- (W4b) settings link label' );
$saved_settings = get_option( 'hk9_settings' );
$raw            = HK9\Core\Settings\Store::raw();
$posted         = [ '__groups' => 'header', 'header' => $raw['header'] ?? [] ];
$posted['header']['cta_link'] = [ 'mode' => 'post', 'post_id' => (string) ( $raw['header']['cta_link']['post_id'] ?? 0 ), 'url' => '', 'label' => 'tmp-core-fixes Label', 'target' => '_self' ];
$clean = HK9\Core\Settings\Store::sanitize_submission( $posted );
$check( 'sanitize_submission keeps header.cta_link.label', $clean['header']['cta_link']['label'] ?? null, 'tmp-core-fixes Label' );
$check( 'other groups untouched by a header-only post', $clean['links']['donate'] ?? null, HK9\Core\Settings\Store::sanitize( $raw )['links']['donate'] );
$check( 'option itself unchanged by the pure sanitiser', get_option( 'hk9_settings' ) === $saved_settings, true );
ob_start();
HK9\Core\Settings\Page::render_field( [ 'group' => 'header', 'key' => 'cta_link', 'field' => HK9\Core\Settings\Schema::fields()['header']['fields']['cta_link'], 'id' => 'hk9_header_cta_link' ] );
$link_html = (string) ob_get_clean();
$check( 'settings link field renders a [label] input', str_contains( $link_html, 'name="hk9_settings[header][cta_link][label]"' ), true );
$check( 'label input carries the saved value', str_contains( $link_html, 'value="' . esc_attr( (string) ( $raw['header']['cta_link']['label'] ?? '' ) ) . '" class="regular-text" data-hk9-link-label' ), true );

/* ---------------------------------------------------------------- (W4b) BarKode legacy path → redirect rule */
WP_CLI::log( '-- (W4b) legacy path redirect' );
$redirects_before = HK9\Core\Redirects\Store::all(); // normalized view (save_rules() always stores the normalized form)
$legacy_src       = '/tmp-core-fixes-legacy/';
$legacy_key       = HK9\Core\Redirects\Store::normalize_key( $legacy_src );
HK9\Core\Redirects\Store::delete( $legacy_src );
$rec2 = wp_insert_post( [ 'post_type' => 'hk9_barkode', 'post_status' => 'draft', 'post_title' => 'tmp-core-fixes legacy', 'post_name' => 'tmp-core-fixes-legacy-rec', 'meta_input' => [ 'hk9_legacy_path' => $legacy_src ] ] );
$cleanup[] = $rec2;
$check( 'draft record adds no rule', isset( HK9\Core\Redirects\Store::all()[ $legacy_key ] ), false );
wp_update_post( [ 'ID' => $rec2, 'post_status' => 'publish' ] );
$rule = HK9\Core\Redirects\Store::all()[ $legacy_key ] ?? null;
$check( 'publishing adds the rule', is_array( $rule ), true );
$check( 'rule targets the record slug', $rule['to'] ?? null, [ 'type' => 'record', 'slug' => 'tmp-core-fixes-legacy-rec' ] );
$check( 'rule is a 301, enabled, not a seed', [ $rule['status'] ?? 0, $rule['enabled'] ?? null, $rule['seed'] ?? null ], [ 301, true, false ] );
$resolved = HK9\Core\Redirects\Resolver::resolve( $legacy_src );
$check( 'resolver sends the legacy path to the record', $resolved['url'] ?? null, get_permalink( $rec2 ) );
// Never overwrite: point the rule elsewhere, re-save the record, the rule stays.
$rules = HK9\Core\Redirects\Store::all();
$rules[ $legacy_key ]['to'] = [ 'type' => 'path', 'path' => '/stories/' ];
HK9\Core\Redirects\Store::save_rules( $rules );
wp_update_post( [ 'ID' => $rec2, 'post_title' => 'tmp-core-fixes legacy 2' ] );
$check( 'existing rule is never overwritten', HK9\Core\Redirects\Store::all()[ $legacy_key ]['to'] ?? null, [ 'type' => 'path', 'path' => '/stories/' ] );
$check( 'ensure_rule reports "exists"', HK9\Core\Redirects\LegacyPaths::ensure_rule( get_post( $rec2 ) )['reason'], 'exists' );
// Own permalink path is never redirected.
update_post_meta( $rec2, 'hk9_legacy_path', wp_parse_url( get_permalink( $rec2 ), PHP_URL_PATH ) );
$own = HK9\Core\Redirects\LegacyPaths::ensure_rule( get_post( $rec2 ) );
$check( 'record permalink path is refused (own_permalink)', $own['reason'], 'own_permalink' );
$check( 'no rule for the permalink path', isset( HK9\Core\Redirects\Store::all()[ $own['key'] ] ), false );
$check( 'empty legacy path adds nothing', ( update_post_meta( $rec2, 'hk9_legacy_path', '' ) || true ) && 'empty' === HK9\Core\Redirects\LegacyPaths::ensure_rule( get_post( $rec2 ) )['reason'], true );
$help = HK9\Core\Meta\Definitions::field( 'hk9_barkode', 'legacy_path' )['help'] ?? '';
$check( 'legacy path help names the behaviour and the Redirects screen', str_contains( $help, 'added automatically' ) && str_contains( $help, 'Heartland → Redirects' ) && str_contains( $help, 'never changed' ), true );
HK9\Core\Redirects\Store::delete( $legacy_src );
$check( 'redirect rules restored', HK9\Core\Redirects\Store::all(), $redirects_before );

/* ---------------------------------------------------------------- (W4b) new records append at the end (menu_order) */
WP_CLI::log( '-- (W4b) default menu_order' );
foreach ( [ 'hk9_person', 'hk9_partner', 'hk9_team', 'hk9_campaign', 'hk9_story' ] as $mo_type ) {
	$max = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT MAX(menu_order) FROM {$GLOBALS['wpdb']->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", $mo_type ) );
	$auto = wp_insert_post( [ 'post_type' => $mo_type, 'post_status' => 'auto-draft', 'post_title' => 'tmp-core-fixes auto ' . $mo_type ] );
	$cleanup[] = $auto;
	$check( $mo_type . ' auto-draft gets max+1 (' . ( $max + 1 ) . ')', (int) get_post( $auto )->menu_order, $max + 1 );
}
$explicit = wp_insert_post( [ 'post_type' => 'hk9_person', 'post_status' => 'auto-draft', 'post_title' => 'tmp-core-fixes explicit', 'menu_order' => 3 ] );
$cleanup[] = $explicit;
$check( 'explicit menu_order is kept', (int) get_post( $explicit )->menu_order, 3 );
$draft = wp_insert_post( [ 'post_type' => 'hk9_person', 'post_status' => 'draft', 'post_title' => 'tmp-core-fixes draft' ] );
$cleanup[] = $draft;
$check( 'non-auto-draft inserts (importer/CLI) untouched', (int) get_post( $draft )->menu_order, 0 );
$check( 'event type not affected', in_array( 'hk9_event', HK9\Core\PostTypes\MenuOrder::types(), true ), false );
// Classic editor: the Post Attributes box is wrapped so the help sits under the Order input.
add_meta_box( 'pageparentdiv', 'Post Attributes', 'page_attributes_meta_box', 'hk9_person', 'side', 'core' ); // as core does before add_meta_boxes
do_action( 'add_meta_boxes', 'hk9_person', get_post( $draft ) );
$pbox = $GLOBALS['wp_meta_boxes']['hk9_person']['side']['core']['pageparentdiv'] ?? null;
ob_start();
if ( is_array( $pbox ) ) {
	call_user_func( $pbox['callback'], get_post( $draft ), $pbox );
}
$pbox_html = (string) ob_get_clean();
$check( 'classic Post Attributes box keeps the Order input', str_contains( $pbox_html, 'name="menu_order"' ), true );
$check( 'classic Post Attributes box shows the Order help', str_contains( $pbox_html, 'id="hk9-order-help"' ) && str_contains( $pbox_html, 'highest existing Order + 1' ), true );
add_meta_box( 'pageparentdiv', 'Post Attributes', 'page_attributes_meta_box', 'hk9_barkode', 'side', 'core' );
do_action( 'add_meta_boxes', 'hk9_barkode', get_post( $rec2 ) );
$check( 'BarKode Post Attributes box untouched', $GLOBALS['wp_meta_boxes']['hk9_barkode']['side']['core']['pageparentdiv']['callback'] ?? null, 'page_attributes_meta_box' );
$story_help = HK9\Core\Meta\Definitions::field( 'hk9_story', 'veteran_name' )['help'] ?? '';
$check( 'story help mentions the listing card heading', str_contains( $story_help, 'Stories listing' ) && str_contains( $story_help, 'heading' ), true );
$check( 'PayPal help text updated', HK9\Core\Settings\Schema::fields()['links']['fields']['paypal_hosted_button_id']['help'] ?? '', "Used by the Donate page's PayPal option: the PayPal button links to the hosted button checkout for this ID; leave empty to hide the PayPal option." );

/* ---------------------------------------------------------------- (W4b) payload archive pre-scan */
WP_CLI::log( '-- (W4b) payload pre-scan' );
$refused = [ HK9\Core\Import\Payload::class, 'refused_entry' ];
$check( 'manifest allowed', $refused( 'manifest.json' ), '' );
$check( 'content html allowed at root', $refused( 'content/mini__page__home.html' ), '' );
$check( 'content html allowed in one top folder', $refused( 'payload/content/page.html' ), '' );
$check( 'media allowed', $refused( 'media/x/photo.jpg' ), '' );
$check( 'directory entries allowed', $refused( 'media/x/' ), '' );
$check( 'html outside content refused', $refused( 'index.html' ), 'script' );
$check( 'html in nested folder refused', $refused( 'a/b/content/x.html' ), 'script' );
$check( 'php refused', $refused( 'media/shell.php' ), 'script' );
$check( 'double extension refused', $refused( 'media/x.php.jpg' ), 'script' );
$check( 'svg refused', $refused( 'media/logo.svg' ), 'script' );
$check( '.htaccess refused', $refused( '.htaccess' ), 'dotfile' );
$check( 'nested dotfile refused', $refused( 'content/.user.ini' ), 'dotfile' );
$check( 'hidden dir refused', $refused( '.git/config' ), 'dotfile' );
$check( 'traversal refused', $refused( '../x.json' ), 'traversal' );
$check( 'absolute refused', $refused( '/etc/passwd' ), 'traversal' );
$tmpzip = wp_tempnam( 'hk9-scan' );
$za = new ZipArchive();
$za->open( $tmpzip, ZipArchive::OVERWRITE );
$za->addFromString( 'manifest.json', '{}' );
$za->addFromString( 'content/a.html', '<p>x</p>' );
$za->addFromString( '__MACOSX/._manifest.json', 'x' );
$za->close();
$check( 'clean archive passes (__MACOSX ignored)', HK9\Core\Import\Payload::scan_archive( $tmpzip ), true );
$za->open( $tmpzip );
$za->addFromString( 'media/.htaccess', 'AddHandler' );
$za->close();
$scan = HK9\Core\Import\Payload::scan_archive( $tmpzip );
$check( 'archive with .htaccess refused before extraction', is_wp_error( $scan ) ? $scan->get_error_code() : 'ok', 'hk9_upload_refused' );
$check( 'refusal names the entry', is_wp_error( $scan ) && str_contains( $scan->get_error_message(), 'media/.htaccess' ), true );
wp_delete_file( $tmpzip );
$tmpdir = get_temp_dir() . 'tmp-core-fixes-purge-' . wp_generate_password( 8, false );
wp_mkdir_p( $tmpdir . '/content' );
file_put_contents( $tmpdir . '/content/ok.html', 'x' );
file_put_contents( $tmpdir . '/stray.html', 'x' );
file_put_contents( $tmpdir . '/content/.user.ini', 'x' );
$check( 'purge_scripts removes stray html + dotfile, keeps content html', [ HK9\Core\Import\Payload::purge_scripts( $tmpdir ), file_exists( $tmpdir . '/content/ok.html' ), file_exists( $tmpdir . '/stray.html' ) ], [ 2, true, false ] );
wp_delete_file( $tmpdir . '/content/ok.html' );
rmdir( $tmpdir . '/content' );
rmdir( $tmpdir );

/* ---------------------------------------------------------------- (W4b) forms: stateless failures + failure counter */
WP_CLI::log( '-- (W4b) forms failures' );
$count_res = static fn(): int => (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '\\_transient\\_hk9\\_form\\_res\\_%'" );
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
delete_transient( 'hk9_form_fail_' . substr( hash_hmac( 'sha256', '203.0.113.77', wp_salt( 'nonce' ) . '|fail' ), 0, 32 ) );
$before = $count_res();
$r = HK9\Core\Forms\Handler::process( 'contact', [ 'hk9_nonce' => 'bad' ] );
$check( 'bad nonce → code nonce, no values', [ $r->ok, $r->code, $r->values ], [ false, 'nonce', [] ] );
$check( 'process() itself writes no result transient', $count_res(), $before );
$check( 'failure counted for the IP hash', HK9\Core\Forms\Antispam::too_many_failures( '203.0.113.77' ), false );
for ( $i = 0; $i < HK9\Core\Forms\Antispam::failure_limit(); $i++ ) {
	HK9\Core\Forms\Antispam::record_failure( '203.0.113.77' );
}
$check( 'failure limit reached after N failures', HK9\Core\Forms\Antispam::too_many_failures( '203.0.113.77' ), true );
$check( 'canned message for a stateless code', HK9\Core\Forms\Handler::canned_message( 'nonce' ), HK9\Core\Forms\Antispam::outcome( 'nonce' )['message'] );
$check( 'unknown code → empty message', HK9\Core\Forms\Handler::canned_message( 'nope' ), '' );
$check( 'every outcome has a non-empty message', count( array_filter( HK9\Core\Forms\Antispam::outcomes(), static fn( $o ) => '' === $o['message'] ) ), 0 );
delete_transient( 'hk9_form_fail_' . substr( hash_hmac( 'sha256', '203.0.113.77', wp_salt( 'nonce' ) . '|fail' ), 0, 32 ) );
unset( $_SERVER['REMOTE_ADDR'] );

/* ---------------------------------------------------------------- (W4b) redirect test verifies TLS by default */
WP_CLI::log( '-- (W4b) redirect test sslverify' );
$seen_args = null;
$capture   = static function ( $pre, $args ) use ( &$seen_args ) {
	$seen_args = $args;
	return new WP_Error( 'tmp', 'captured' );
};
add_filter( 'pre_http_request', $capture, 10, 2 );
HK9\Core\Redirects\Resolver::test( '/hk923-005/', true );
$check( 'sslverify defaults to true', $seen_args['sslverify'] ?? null, true );
add_filter( 'hk9/redirects/test_sslverify', '__return_false' );
HK9\Core\Redirects\Resolver::test( '/hk923-005/', true );
$check( 'filter can opt out', $seen_args['sslverify'] ?? null, false );
remove_filter( 'hk9/redirects/test_sslverify', '__return_false' );
remove_filter( 'pre_http_request', $capture, 10 );

/* ---------------------------------------------------------------- cleanup */
foreach ( $cleanup as $id ) {
	wp_delete_post( (int) $id, true );
}
WP_CLI::log( sprintf( '== %d passed, %d failed ==', $pass, $fail ) );
if ( $fail > 0 ) {
	WP_CLI::halt( 1 );
}
