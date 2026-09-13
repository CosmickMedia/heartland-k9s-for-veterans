<?php
/**
 * Sections / meta test plan (docs/ARCHITECTURE.md §4–6, discovery metaDesign).
 *
 * Run inside the docker stack:
 *   tools/wp.sh eval-file /var/www/html/wp-content/plugins/heartland-k9s-core/tests/sections-test.php
 *
 * Prints one PASS/FAIL/BLOCKED line per check with evidence; exits non-zero on
 * any FAIL. (No strict_types: eval-file wraps the code.) Creates and deletes its own pages/users (titles prefixed "HK9 Test").
 *
 * @package HK9\Core
 */

use HK9\Core\Fields\Sanitizer;
use HK9\Core\Meta\RevisionGuard;
use HK9\Core\Sections\Accessor;
use HK9\Core\Sections\Layout;
use HK9\Core\Sections\MetaBox;
use HK9\Core\Sections\Registry;

if ( ! defined( 'WP_CLI' ) && ! defined( 'ABSPATH' ) ) {
	exit( 'Run via WP-CLI eval-file.' );
}

$hk9_results = [];
$hk9_report  = static function ( string $name, string $status, string $evidence ) use ( &$hk9_results ): void {
	$hk9_results[] = [ $name, $status, $evidence ];
	echo str_pad( $status, 7 ), ' ', $name, ' — ', $evidence, "\n";
};
$hk9_pass    = static fn( string $n, string $e ) => $hk9_report( $n, 'PASS', $e );
$hk9_fail    = static fn( string $n, string $e ) => $hk9_report( $n, 'FAIL', $e );
$hk9_blocked = static fn( string $n, string $e ) => $hk9_report( $n, 'BLOCKED', $e );

$hk9_admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ] )[0] ?? 1;
wp_set_current_user( (int) $hk9_admin );
if ( ! function_exists( 'wp_check_post_lock' ) ) {
	require_once ABSPATH . 'wp-admin/includes/post.php';
}
require_once ABSPATH . 'wp-admin/includes/revision.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$hk9_rest = static function ( string $method, string $route, array $params = [] ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
};
$hk9_revisions = static fn( int $post_id ): array => array_map( 'intval', get_posts( [ 'post_type' => 'revision', 'post_parent' => $post_id, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ] ) );
$hk9_latest    = static fn( int $post_id ) => wp_get_post_revisions( $post_id, [ 'numberposts' => 1 ] );

/* 0. Loading the definitions (init) never fetches the Gravity Forms list: GFAPI::get_forms() only runs when a picker is rendered/saved with a non-default value. */
if ( class_exists( 'HK9\\Core\\Support\\FormProviders' ) ) {
	$gf_cache_prop = new ReflectionProperty( HK9\Core\Support\FormProviders::class, 'forms_cache' );
	$gf_cache_prop->setAccessible( true );
	foreach ( array_keys( Registry::templates() ) as $hk9_t ) {
		foreach ( Registry::definitions( $hk9_t ) as $hk9_d ) {
			$hk9_d->schema();
			$hk9_d->defaults();
			$hk9_d->sanitize( $hk9_d->defaults() );
		}
	}
	null === $gf_cache_prop->getValue()
		? $hk9_pass( 'gravity_list_not_fetched_at_boot', 'FormProviders::$forms_cache still null after init + schema/defaults/sanitize(defaults) of every definition (WP-CLI context would allow the fetch)' )
		: $hk9_fail( 'gravity_list_not_fetched_at_boot', 'forms list was fetched while definitions loaded: ' . wp_json_encode( $gf_cache_prop->getValue() ) );
}

/* 1. Schema validity + idempotency of every registered default. */
$bad = [];
foreach ( get_registered_meta_keys( 'post', 'page' ) as $key => $args ) {
	if ( 0 !== strpos( $key, 'hk9_' ) ) {
		continue;
	}
	$schema  = $args['show_in_rest']['schema'];
	$default = $schema['default'];
	if ( is_wp_error( rest_validate_value_from_schema( $default, $schema, $key ) ) ) {
		$bad[] = "$key invalid";
	}
	if ( $args['sanitize_callback']( $default ) !== $default ) {
		$bad[] = "$key not idempotent";
	}
	if ( rest_sanitize_value_from_schema( $default, $schema ) !== $default ) {
		$bad[] = "$key changed by rest_sanitize";
	}
}
$count = count( array_filter( array_keys( get_registered_meta_keys( 'post', 'page' ) ), static fn( $k ) => 0 === strpos( $k, 'hk9_' ) ) );
empty( $bad ) ? $hk9_pass( 'schema_defaults_valid', "$count page meta keys: defaults validate, sanitizer idempotent, rest_sanitize identity" ) : $hk9_fail( 'schema_defaults_valid', implode( '; ', $bad ) );

/* 2. Sanitizer: coercion, unknown keys, __present, repeater order. */
$def   = Registry::definition( 'about', 'values' );
$clean = $def->sanitize(
	[
		'__present' => '1',
		'heading'   => ' <b>Values</b> ',
		'divider'   => '1',
		'columns'   => '9',
		'bogus'     => 'x',
		'cards'     => [
			5 => [ 'title' => 'C', 'icon' => 'heart', 'tone' => 'purple', 'link' => 'javascript:alert(1)' ],
			2 => [ 'title' => 'A', 'icon' => 'not-an-icon', 'decorate' => 'true' ],
			9 => 'junk',
		],
	]
);
$ok    = 'Values' === $clean['heading'] && true === $clean['divider'] && '3' === $clean['columns'] && ! isset( $clean['bogus'] ) && ! isset( $clean['__present'] )
	&& [ 'C', 'A' ] === array_column( $clean['cards'], 'title' ) && 'navy' === $clean['cards'][0]['tone'] && '' === $clean['cards'][1]['icon'] && true === $clean['cards'][1]['decorate']
	&& '' === $clean['cards'][0]['link']['url'] && $clean === $def->sanitize( $clean ) && ! is_wp_error( rest_validate_value_from_schema( $clean, $def->schema() ) );
$ok ? $hk9_pass( 'sanitizer_coerces_and_drops', 'kses+trim, "1"→true, invalid select→default, unknown/__present dropped, row order kept, bad icon/url cleared, idempotent, schema-valid' ) : $hk9_fail( 'sanitizer_coerces_and_drops', wp_json_encode( $clean ) );

/* Fixture page (about template). */
$page_id = wp_insert_post(
	[
		'post_type'    => 'page',
		'post_title'   => 'HK9 Test Sections About',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->',
		'post_author'  => (int) $hk9_admin,
	],
	true
);
if ( is_wp_error( $page_id ) ) {
	$hk9_fail( 'fixture_page', $page_id->get_error_message() );
	exit( 1 );
}
update_post_meta( $page_id, '_wp_page_template', 'page-templates/about.php' );
// A key belonging to another template (home), to prove template-scoped writes never touch it.
$mission = Registry::definition( 'home', 'mission' )->sanitize( [ 'heading' => 'Home mission stays', 'divider' => false, 'text' => 'x' ] );
update_post_meta( $page_id, 'hk9_sec_mission', $mission );
Accessor::flush();

'about' === Accessor::template_for_post( $page_id ) ? $hk9_pass( 'template_for_post', "page $page_id → 'about'" ) : $hk9_fail( 'template_for_post', Accessor::template_for_post( $page_id ) );

/* 3. REST GET: absent keys expose the page-template defaults; view context strips private fields. */
$get = $hk9_rest( 'GET', "/wp/v2/pages/$page_id", [ 'context' => 'edit' ] );
$meta = $get->get_data()['meta'] ?? [];
$hero = $meta['hk9_sec_hero_band'] ?? [];
( 200 === $get->get_status() && 'Our Mission' === ( $hero['heading'] ?? null ) && isset( $meta['hk9_sec_faq']['source_note'] ) )
	? $hk9_pass( 'rest_get_edit_defaults', 'GET context=edit: hk9_sec_hero_band.heading = "Our Mission" (about defaults), faq.source_note present' )
	: $hk9_fail( 'rest_get_edit_defaults', wp_json_encode( [ $get->get_status(), $hero, array_keys( $meta['hk9_sec_faq'] ?? [] ) ] ) );
$view = $hk9_rest( 'GET', "/wp/v2/pages/$page_id", [ 'context' => 'view' ] )->get_data()['meta'] ?? [];
( isset( $view['hk9_sec_faq'] ) && ! array_key_exists( 'source_note', $view['hk9_sec_faq'] ) )
	? $hk9_pass( 'rest_view_strips_private', 'context=view: hk9_sec_faq has no source_note' )
	: $hk9_fail( 'rest_view_strips_private', wp_json_encode( array_keys( $view['hk9_sec_faq'] ?? [] ) ) );

/* Home-template page: shared key hk9_sec_hero_image must expose HOME defaults, not the canonical (barkode) ones. */
$home_id = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'HK9 Test Sections Home', 'post_status' => 'publish', 'post_author' => (int) $hk9_admin ], true );
update_post_meta( $home_id, '_wp_page_template', 'page-templates/home.php' );
$home_hero = $hk9_rest( 'GET', "/wp/v2/pages/$home_id", [ 'context' => 'edit' ] )->get_data()['meta']['hk9_sec_hero_image'] ?? [];
'So They Never Walk Alone.' === ( $home_hero['heading'] ?? null ) ? $hk9_pass( 'rest_get_shared_key_template_defaults', 'home page hk9_sec_hero_image.heading = "So They Never Walk Alone."' ) : $hk9_fail( 'rest_get_shared_key_template_defaults', wp_json_encode( $home_hero ) );

/* 4. Block-editor save = REST PUT (+ meta-box-loader POST): exactly one revision. */
$before  = $hk9_revisions( $page_id );
$hero_v1 = array_merge( $hero, [ 'heading' => 'Heading v1' ] );
$full    = $meta; // block editor round-trips every key (persisted ⊕ edits)
$full['hk9_sec_hero_band'] = $hero_v1;
$put = $hk9_rest( 'PUT', "/wp/v2/pages/$page_id", [ 'meta' => $full ] );
$after = $hk9_revisions( $page_id );
$stored = get_post_meta( $page_id, 'hk9_sec_hero_band', true );
$r1 = end( $after );
( 200 === $put->get_status() && count( $after ) === count( $before ) + 1 && 'Heading v1' === ( $stored['heading'] ?? null ) && get_post_meta( $r1, 'hk9_sec_hero_band', true ) === $stored )
	? $hk9_pass( 'rest_put_one_revision', sprintf( 'PUT 200, revisions %d→%d (R1=%d), page+revision hero heading = "Heading v1"', count( $before ), count( $after ), $r1 ) )
	: $hk9_fail( 'rest_put_one_revision', wp_json_encode( [ $put->get_status(), count( $before ), count( $after ), $stored ] ) );

// Untouched keys sent back as defaults must not be materialized.
! metadata_exists( 'post', $page_id, 'hk9_sec_legacy' ) && ! metadata_exists( 'post', $page_id, 'hk9_sec_features' )
	? $hk9_pass( 'default_write_skipped', 'hk9_sec_legacy / hk9_sec_features still absent after a full-meta PUT' )
	: $hk9_fail( 'default_write_skipped', 'defaults were materialized' );

/* 4b. Block-editor-style PUT carrying the "— Select —" placeholder ('') for an enum select must not 400. */
$empty_select              = $full;
$empty_select['hk9_sec_hero_band'] = array_merge( $hero_v1, [ 'pattern' => '' ] );
$put_empty = $hk9_rest( 'PUT', "/wp/v2/pages/$page_id", [ 'meta' => $empty_select ] );
$pattern_now = get_post_meta( $page_id, 'hk9_sec_hero_band', true )['pattern'] ?? null;
$pattern_def = Registry::definition( 'about', 'hero_band' )->defaults()['pattern'];
( 200 === $put_empty->get_status() && $pattern_def === $pattern_now )
	? $hk9_pass( 'rest_put_empty_select_ok', "PUT with pattern '' → 200, stored pattern = default \"$pattern_def\"" )
	: $hk9_fail( 'rest_put_empty_select_ok', wp_json_encode( [ $put_empty->get_status(), $put_empty->get_data()['message'] ?? '', $pattern_now ] ) );
// Every enum property of every registered key: sanitizing '' (and garbage) yields a schema-valid value.
$enum_bad   = [];
$enum_count = 0;
$hk9_walk   = static function ( array $props, array $path, callable $visit ) use ( &$hk9_walk ): void {
	foreach ( $props as $name => $prop ) {
		if ( isset( $prop['enum'] ) ) {
			$visit( array_merge( $path, [ $name ] ), $prop );
		}
		if ( isset( $prop['properties'] ) ) {
			$hk9_walk( $prop['properties'], array_merge( $path, [ $name ] ), $visit );
		}
		if ( isset( $prop['items']['properties'] ) ) {
			$hk9_walk( $prop['items']['properties'], array_merge( $path, [ $name, '[]' ] ), $visit );
		}
	}
};
foreach ( Registry::meta_keys() as $key ) {
	$def = Registry::by_key( $key );
	$hk9_walk(
		$def->schema()['properties'],
		[],
		static function ( array $path, array $prop ) use ( $def, $key, &$enum_bad, &$enum_count ): void {
			if ( in_array( '[]', $path, true ) ) {
				return; // Repeater sub-fields are covered by the direct Sanitizer check below.
			}
			$enum_count++;
			foreach ( [ '', 'not-an-option' ] as $bad ) {
				$clean = $def->sanitize( [ $path[0] => $bad ] );
				if ( is_wp_error( rest_validate_value_from_schema( $clean, $def->schema(), $key ) ) ) {
					$enum_bad[] = $key . '.' . implode( '.', $path ) . "='" . $bad . "'";
				}
			}
		}
	);
}
$sub_ok = '' === Sanitizer::sanitize_field( [ 'type' => 'select', 'key' => 's', 'options' => [ 'a' => 'A' ] ], '' )
	&& 'a' === Sanitizer::sanitize_field( [ 'type' => 'select', 'key' => 's', 'options' => [ 'a' => 'A' ], 'default' => 'a' ], '' )
	&& 'a' === Sanitizer::sanitize_field( [ 'type' => 'select', 'key' => 's', 'options' => [ 'a' => 'A' ], 'default' => 'a' ], 'zzz' );
( [] === $enum_bad && $enum_count > 0 && $sub_ok )
	? $hk9_pass( 'select_empty_always_schema_valid', "$enum_count top-level enum properties: '' and garbage sanitize to schema-valid values; '' allowed only when the default is ''" )
	: $hk9_fail( 'select_empty_always_schema_valid', wp_json_encode( [ $enum_bad, $sub_ok ] ) );
// Rendered control: no placeholder option when the default is non-empty; placeholder kept when '' is legal.
$sel_renderer = new HK9\Core\Fields\Renderer();
$sel_html_def = HK9\Core\Fields\Field::type( 'select' )->render( HK9\Core\Fields\Field::normalize( [ 'type' => 'select', 'key' => 'p', 'options' => [ 'a' => 'A', 'b' => 'B' ], 'default' => 'a' ] ), '', 'n', 'i', $sel_renderer );
$sel_html_opt = HK9\Core\Fields\Field::type( 'select' )->render( HK9\Core\Fields\Field::normalize( [ 'type' => 'select', 'key' => 'p', 'options' => [ 'a' => 'A' ] ] ), '', 'n', 'i', $sel_renderer );
( ! str_contains( $sel_html_def, 'value=""' ) && str_contains( $sel_html_def, '<option value="a" selected>' ) && str_contains( $sel_html_opt, '<option value="" selected>' ) )
	? $hk9_pass( 'select_placeholder_only_when_empty_default', 'non-empty default: no "" option, default selected; empty default: placeholder offered' )
	: $hk9_fail( 'select_placeholder_only_when_empty_default', $sel_html_def . ' | ' . $sel_html_opt );

/* 4c. Collection route (no id): absent keys report each page's own template defaults (shared key hk9_sec_hero_image: home vs canonical barkode). */
$list = $hk9_rest( 'GET', '/wp/v2/pages', [ 'include' => [ $page_id, $home_id ], 'context' => 'edit', 'per_page' => 10 ] );
$list_meta = [];
foreach ( (array) $list->get_data() as $row ) {
	$list_meta[ (int) ( $row['id'] ?? 0 ) ] = $row['meta'] ?? [];
}
$canonical_hero_image = Registry::by_key( 'hk9_sec_hero_image' )->template;
( 200 === $list->get_status() && 'home' !== $canonical_hero_image
	&& 'So They Never Walk Alone.' === ( $list_meta[ $home_id ]['hk9_sec_hero_image']['heading'] ?? null )
	&& 'Heading v1' === ( $list_meta[ $page_id ]['hk9_sec_hero_band']['heading'] ?? null ) )
	? $hk9_pass( 'rest_collection_template_defaults', "GET /wp/v2/pages?include=: home page hk9_sec_hero_image = home defaults (canonical key owner: $canonical_hero_image); stored about hero_band kept (rest_prepare_page)" )
	: $hk9_fail( 'rest_collection_template_defaults', wp_json_encode( [ $list->get_status(), $canonical_hero_image, $list_meta[ $home_id ]['hk9_sec_hero_image']['heading'] ?? null, $list_meta[ $page_id ]['hk9_sec_hero_band']['heading'] ?? null ] ) );

/* 5. Other template keys untouched. */
get_post_meta( $page_id, 'hk9_sec_mission', true ) === $mission ? $hk9_pass( 'other_template_keys_untouched', 'hk9_sec_mission (home) unchanged after about-page saves' ) : $hk9_fail( 'other_template_keys_untouched', wp_json_encode( get_post_meta( $page_id, 'hk9_sec_mission', true ) ) );

/* 6. Restore R1 restores the hero heading; keys absent from R1 survive. */
$legacy_after_r1 = Registry::definition( 'about', 'legacy' )->sanitize( [ 'heading' => 'Legacy added after R1' ] );
update_post_meta( $page_id, 'hk9_sec_legacy', $legacy_after_r1 );
$full['hk9_sec_legacy']    = $legacy_after_r1; // the editor store would hold it after its GET
$full['hk9_sec_hero_band'] = array_merge( $hero, [ 'heading' => 'Heading v2' ] );
$put2 = $hk9_rest( 'PUT', "/wp/v2/pages/$page_id", [ 'meta' => $full ] );
$revs = $hk9_revisions( $page_id );
$r2   = end( $revs );
$restored = wp_restore_post_revision( $r1 );
$heading_now = get_post_meta( $page_id, 'hk9_sec_hero_band', true )['heading'] ?? null;
( 'Heading v2' === get_post_meta( $r2, 'hk9_sec_hero_band', true )['heading'] && $restored && 'Heading v1' === $heading_now )
	? $hk9_pass( 'restore_r1_restores_hero', "R2=$r2 had v2; wp_restore_post_revision(R1=$r1) → page heading = \"Heading v1\"" )
	: $hk9_fail( 'restore_r1_restores_hero', wp_json_encode( [ $put2->get_status(), $restored, $heading_now ] ) );
( ! metadata_exists( 'post', $r1, 'hk9_sec_legacy' ) && get_post_meta( $page_id, 'hk9_sec_legacy', true ) === $legacy_after_r1 )
	? $hk9_pass( 'restore_absent_key_protected', 'hk9_sec_legacy absent in R1 → kept on the page after restore (prio 9/11 guard)' )
	: $hk9_fail( 'restore_absent_key_protected', wp_json_encode( get_post_meta( $page_id, 'hk9_sec_legacy', true ) ) );
get_post_meta( $page_id, 'hk9_sec_mission', true ) === $mission ? $hk9_pass( 'restore_other_keys_untouched', 'hk9_sec_mission unchanged by restore' ) : $hk9_fail( 'restore_other_keys_untouched', 'changed' );

/* 7. Readable revision diff. */
RevisionGuard::enable_diff_mode();
$diff = wp_get_revision_ui_diff( get_post( $page_id ), get_post( $r1 ), get_post( $r2 ) );
$ids  = array_column( $diff, 'id' );
$hero_diff = '';
foreach ( $diff as $row ) {
	if ( 'hk9_sec_hero_band' === $row['id'] ) {
		$hero_diff = $row['diff'];
	}
}
$hero_text = wp_strip_all_tags( $hero_diff );
( in_array( 'hk9_sec_hero_band', $ids, true ) && str_contains( $hero_text, 'Heading v1' ) && str_contains( $hero_text, 'Heading v2' ) && str_contains( $hero_text, 'Heading:' ) )
	? $hk9_pass( 'revision_diff_readable', 'wp_get_revision_ui_diff(R1,R2) lists hk9_sec_hero_band with "Heading: Heading v1" → "Heading v2"' )
	: $hk9_fail( 'revision_diff_readable', wp_json_encode( $ids ) . ' ' . $hero_text );

/* 10. Quick edit (no nonce) leaves meta alone. */
$snapshot = [ get_post_meta( $page_id, 'hk9_sec_hero_band', true ), get_post_meta( $page_id, 'hk9_sec_mission', true ), get_post_meta( $page_id, 'hk9_sec_legacy', true ) ];
$_POST = [ 'post_ID' => $page_id, 'post_title' => 'HK9 Test Sections About (quick edit)', 'page_template' => 'default', '_inline_edit' => wp_create_nonce( 'inlineeditnonce' ) ];
wp_update_post( [ 'ID' => $page_id, 'post_title' => 'HK9 Test Sections About (quick edit)', 'page_template' => 'default' ] );
$_POST = [];
$same = [ get_post_meta( $page_id, 'hk9_sec_hero_band', true ), get_post_meta( $page_id, 'hk9_sec_mission', true ), get_post_meta( $page_id, 'hk9_sec_legacy', true ) ] === $snapshot;
$same && '' === get_page_template_slug( $page_id ) ? $hk9_pass( 'quick_edit_untouched', 'template switched to default via wp_update_post without nonce: all hk9_sec_* unchanged' ) : $hk9_fail( 'quick_edit_untouched', 'meta changed or template not switched' );

/* 11. Template switch keeps old keys: switch to contact and save contact sections. */
update_post_meta( $page_id, '_wp_page_template', 'page-templates/contact.php' );
Accessor::flush();
$info = Registry::definition( 'contact', 'info' )->sanitize( [ 'heading' => 'Reach us' ] );
$put3 = $hk9_rest( 'PUT', "/wp/v2/pages/$page_id", [ 'meta' => [ 'hk9_sec_info' => $info ] ] );
( 200 === $put3->get_status() && 'Reach us' === get_post_meta( $page_id, 'hk9_sec_info', true )['heading'] && 'Heading v1' === get_post_meta( $page_id, 'hk9_sec_hero_band', true )['heading'] && get_post_meta( $page_id, 'hk9_sec_legacy', true ) === $legacy_after_r1 )
	? $hk9_pass( 'template_switch_keeps_keys', 'contact template saved hk9_sec_info; about keys (hero_band, legacy) retained' )
	: $hk9_fail( 'template_switch_keeps_keys', wp_json_encode( $put3->get_status() ) );
// The ajax panel normalizes templates and renders the requested one.
$slug = MetaBox::normalize_template( 'page-templates/about.php' );
'about' === $slug && 'default' === MetaBox::normalize_template( '' ) ? $hk9_pass( 'template_slug_normalization', 'page-templates/about.php → about, "" → default' ) : $hk9_fail( 'template_slug_normalization', $slug );

/* 12. Layout resolution. */
update_post_meta( $page_id, '_wp_page_template', 'page-templates/about.php' );
update_post_meta( $page_id, 'hk9_sections_layout', [ 'order' => [ 'cta', 'values', 'legacy' ], 'hidden' => [ 'values' ] ] );
Accessor::flush();
$layout = hk9_sections_layout( $page_id, 'about' );
[ 'hero_band', 'cta', 'legacy' ] === $layout ? $hk9_pass( 'layout_order_and_hidden', 'order [cta,values,legacy] + hidden [values] → [hero_band, cta, legacy]' ) : $hk9_fail( 'layout_order_and_hidden', wp_json_encode( $layout ) );
$landing = hk9_sections_layout( 0, 'landing' );
[ 'hero_band' ] === $landing ? $hk9_pass( 'layout_hidden_by_default', 'landing with no layout → [hero_band] (cards/faq/tiers/cta hidden by default)' ) : $hk9_fail( 'layout_hidden_by_default', wp_json_encode( $landing ) );
$form_layout = Layout::sanitize( [ 'order' => [ 'legacy', 'values', 'nope' ], 'shown' => [ 'legacy' ], '__present' => '1' ] );
[ 'order' => [ 'legacy', 'values' ], 'hidden' => [ 'values' ], 'content_position' => 'after' ] === $form_layout ? $hk9_pass( 'layout_form_shape_sanitized', 'form shape {order, shown} → {order, hidden, content_position=after}, unknown ids dropped' ) : $hk9_fail( 'layout_form_shape_sanitized', wp_json_encode( $form_layout ) );
/* 12a. Editor-content position: enum-validated, defaults to 'after', schema-valid, kept through the REST shape. */
$pos_before = Layout::sanitize( [ 'order' => [ 'legacy' ], 'hidden' => [], 'content_position' => 'before' ] );
$pos_bogus  = Layout::sanitize( [ 'order' => [ 'legacy' ], 'hidden' => [], 'content_position' => '<script>' ] );
$pos_hide   = Layout::sanitize( [ 'order' => [], 'hidden' => [], 'content_position' => 'hide' ] );
$pos_ok     = 'before' === $pos_before['content_position'] && 'after' === $pos_bogus['content_position'] && 'hide' === $pos_hide['content_position']
	&& ! is_wp_error( rest_validate_value_from_schema( $pos_hide, Layout::schema(), 'hk9_sections_layout' ) )
	&& is_wp_error( rest_validate_value_from_schema( [ 'order' => [], 'hidden' => [], 'content_position' => 'top' ], Layout::schema(), 'hk9_sections_layout' ) )
	&& [ 'order' => [], 'hidden' => [], 'content_position' => 'after' ] === Layout::empty_value();
$pos_ok ? $hk9_pass( 'layout_content_position', "before kept, garbage → after, hide kept; schema enum rejects 'top'; empty value carries content_position=after" ) : $hk9_fail( 'layout_content_position', wp_json_encode( [ $pos_before, $pos_bogus, $pos_hide ] ) );

/* 12b. Reference layout (declaration order + hidden-by-default) is a default write; a switched request template is honoured. */
delete_post_meta( $page_id, 'hk9_sections_layout' );
$ref_layout = Layout::reference( Registry::definitions( 'about' ) );
$ref_is_default = Registry::is_default_write( Layout::META_KEY, $page_id, $ref_layout );
$moved = $ref_layout;
$moved['order'] = array_reverse( $moved['order'] );
$moved_is_default = Registry::is_default_write( Layout::META_KEY, $page_id, $moved );
$landing_ref = Layout::reference( Registry::definitions( 'landing' ) );
$switch_is_default = Registry::is_default_write( Layout::META_KEY, $page_id, $landing_ref, 'landing' ) && ! Registry::is_default_write( Layout::META_KEY, $page_id, $landing_ref );
( $ref_is_default && ! $moved_is_default && $switch_is_default && [] !== $landing_ref['hidden'] )
	? $hk9_pass( 'layout_reference_is_default_write', 'about reference layout skipped, reordered layout stored; landing reference (hidden: ' . implode( ',', $landing_ref['hidden'] ) . ') only default with template=landing' )
	: $hk9_fail( 'layout_reference_is_default_write', wp_json_encode( [ $ref_is_default, $moved_is_default, $switch_is_default ] ) );

/* 12c. Repeater min: schema minItems + sanitizer fills missing rows from the default rows. */
$rep = HK9\Core\Fields\Field::normalize( [ 'type' => 'repeater', 'key' => 'rows', 'min' => 2, 'max' => 3, 'fields' => [ [ 'type' => 'text', 'key' => 't' ] ], 'default' => [ [ 't' => 'one' ], [ 't' => 'two' ] ] ] );
$rep_schema = HK9\Core\Fields\Schema::property( $rep );
$rep_one    = Sanitizer::sanitize_field( $rep, [ [ 't' => 'mine' ] ] );
$rep_none   = Sanitizer::sanitize_field( $rep, [] );
$rep_four   = Sanitizer::sanitize_field( $rep, [ [ 't' => 'a' ], [ 't' => 'b' ], [ 't' => 'c' ], [ 't' => 'd' ] ] );
( 2 === ( $rep_schema['minItems'] ?? 0 ) && [ [ 't' => 'mine' ], [ 't' => 'two' ] ] === $rep_one && [ [ 't' => 'one' ], [ 't' => 'two' ] ] === $rep_none && 3 === count( $rep_four )
	&& ! is_wp_error( rest_validate_value_from_schema( $rep_one, $rep_schema, 'rows' ) ) && $rep_one === Sanitizer::sanitize_field( $rep, $rep_one ) )
	? $hk9_pass( 'repeater_min_enforced', 'minItems=2 in schema; 1 row → padded with default row 2; [] → defaults; 4 rows → capped at max 3; idempotent' )
	: $hk9_fail( 'repeater_min_enforced', wp_json_encode( [ $rep_schema['minItems'] ?? null, $rep_one, $rep_none, count( $rep_four ) ] ) );

/* 12d. Write-path capability check: an editor cannot introduce a BarKode record id, but keeps one already stored. */
if ( post_type_exists( 'hk9_barkode' ) && post_type_exists( 'hk9_team' ) ) {
	$ed_id  = wp_insert_user( [ 'user_login' => 'hk9_test_editor_' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
	$rec_a  = wp_insert_post( [ 'post_type' => 'hk9_barkode', 'post_title' => 'HK9 Test Record A', 'post_status' => 'publish', 'post_author' => (int) $hk9_admin ] );
	$rec_b  = wp_insert_post( [ 'post_type' => 'hk9_barkode', 'post_title' => 'HK9 Test Record B', 'post_status' => 'publish', 'post_author' => (int) $hk9_admin ] );
	$team   = wp_insert_post( [ 'post_type' => 'hk9_team', 'post_title' => 'HK9 Test Team', 'post_status' => 'publish', 'post_author' => (int) $hk9_admin ] );
	update_post_meta( $team, 'hk9_barkode', $rec_a );
	$bark_field = HK9\Core\Meta\Definitions::field( 'hk9_team', 'barkode' );
	wp_set_current_user( (int) $ed_id );
	$editor_can_edit_record = current_user_can( 'edit_post', $rec_b );
	$keep = HK9\Core\Fields\Access::restrict_field( $bark_field, $rec_a, $rec_a );
	$deny = HK9\Core\Fields\Access::restrict_field( $bark_field, $rec_b, $rec_a );
	// REST write as editor: hk9_barkode meta pre-sanitized + restricted (team supports custom-fields → meta in REST).
	$put_team = $hk9_rest( 'PUT', "/wp/v2/hk9_team/$team", [ 'meta' => [ 'hk9_barkode' => $rec_b ] ] );
	$team_now = (int) get_post_meta( $team, 'hk9_barkode', true );
	wp_set_current_user( (int) $hk9_admin );
	$admin_ok = (int) HK9\Core\Fields\Access::restrict_field( $bark_field, $rec_b, 0 ) === $rec_b;
	( ! $editor_can_edit_record && $keep === $rec_a && 0 === $deny && $admin_ok && 200 === $put_team->get_status() && 0 === $team_now )
		? $hk9_pass( 'relationship_write_capability', "editor: stored record $rec_a kept when re-sent, new record $rec_b zeroed (REST PUT 200 → meta 0, not $rec_b); admin may reference it" )
		: $hk9_fail( 'relationship_write_capability', wp_json_encode( [ $editor_can_edit_record, $keep, $deny, $admin_ok, $put_team->get_status(), $team_now ] ) );
	wp_delete_post( $team, true );
	wp_delete_post( $rec_a, true );
	wp_delete_post( $rec_b, true );
	if ( ! is_wp_error( $ed_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $ed_id );
	}
} else {
	$hk9_blocked( 'relationship_write_capability', 'hk9_barkode/hk9_team not registered' );
}

/* 13. hero_band fallbacks + hk9_section for unknown ids. */
delete_post_meta( $page_id, 'hk9_sec_hero_band' );
Accessor::flush();
$hb = hk9_section( $page_id, 'hero_band' );
$legacy_absent = hk9_section( $home_id, 'mission' ); // home page, key never saved → reference copy
update_post_meta( $home_id, '_wp_page_template', 'page-templates/landing.php' );
Accessor::flush();
$landing_hero = hk9_section( $home_id, 'hero_band' ); // landing hero_band has no default heading → page title
( 'Our Mission' === $hb['heading'] && 'We believe those who served our nation deserve the highest level of care and support upon their return.' === $legacy_absent['heading']
	&& get_the_title( $home_id ) === $landing_hero['heading'] && [] === hk9_section( $page_id, 'does_not_exist' ) )
	? $hk9_pass( 'accessor_defaults_and_fallbacks', 'absent keys return the template reference defaults; empty hero heading falls back to the page title; unknown section → []' )
	: $hk9_fail( 'accessor_defaults_and_fallbacks', wp_json_encode( [ $hb['heading'], $legacy_absent['heading'], $landing_hero['heading'] ] ) );
update_post_meta( $home_id, '_wp_page_template', 'page-templates/home.php' );
$list_key = hk9_section_meta_key( 'stories', 'list' );
'hk9_sec_stories_list' === $list_key && 'hk9_sec_campaigns_list' === hk9_section_meta_key( 'campaigns', 'list' ) && 'hk9_sec_contact_form' === hk9_section_meta_key( 'contact', 'form' )
	? $hk9_pass( 'conflicting_ids_use_distinct_keys', 'list/form ids map to hk9_sec_stories_list, hk9_sec_campaigns_list, hk9_sec_contact_form' )
	: $hk9_fail( 'conflicting_ids_use_distinct_keys', $list_key );

/* 16b. Form providers: section fields sanitize (provider enum, numeric Gravity id, shortcode-only text) and resolve to the built-in form by default. */
$form_def = Registry::definition( 'contact', 'form' );
$form_raw = $form_def->sanitize(
	[
		'__present'       => '1',
		'heading'         => 'Write to us',
		'provider'        => 'nope',
		'gravity_form_id' => '12abc',
		'shortcode'       => '<b>x</b> [gravityform id="3" title="false"] trailing <script>alert(1)</script>',
		'form'            => 'contact',
	]
);
$form_sc  = $form_def->sanitize( [ '__present' => '1', 'provider' => 'shortcode', 'shortcode' => 'no brackets here' ] );
$app_def  = Registry::definition( 'application', 'form' );
$app_keys = array_column( $app_def->fields, 'key' );
$resolved = hk9_form_provider( $form_def->defaults(), 'contact' );
$sc_res   = hk9_form_provider( [ 'provider' => 'shortcode', 'shortcode' => '[hk9_not_a_real_shortcode_xyz]' ], 'contact' );
$gf_res   = hk9_form_provider( [ 'provider' => 'gravity', 'gravity_form_id' => '999999' ], 'application' );
$prov_ok  = 'inherit' === $form_raw['provider'] && '' === $form_raw['gravity_form_id'] && '[gravityform id="3" title="false"]' === $form_raw['shortcode']
	&& '' === $form_sc['shortcode'] && 'shortcode' === $form_sc['provider']
	&& $form_raw === $form_def->sanitize( $form_raw ) && ! is_wp_error( rest_validate_value_from_schema( $form_raw, $form_def->schema(), 'hk9_sec_contact_form' ) )
	&& in_array( 'provider', $app_keys, true ) && in_array( 'gravity_form_id', $app_keys, true ) && in_array( 'shortcode', $app_keys, true )
	&& 'builtin' === $resolved['provider'] && true === $resolved['available'] && 'settings' === $resolved['source']
	&& 'shortcode' === $sc_res['provider'] && false === $sc_res['available'] && '' !== $sc_res['notice']
	&& 'gravity' === $gf_res['provider'] && false === $gf_res['available'] && '' !== $gf_res['notice']
	&& '' === hk9_render_form_provider( $sc_res ) && '' === hk9_render_form_provider( $gf_res );
$prov_ok
	? $hk9_pass( 'form_provider_fields_and_resolution', 'invalid provider → inherit, non-numeric GF id → "", shortcode keeps only [tag …] (HTML/text dropped), idempotent + schema-valid; both form sections carry provider/gravity_form_id/shortcode; inherit → builtin (settings); unknown shortcode / missing GF form → unavailable with an editor notice and no markup' )
	: $hk9_fail( 'form_provider_fields_and_resolution', wp_json_encode( [ $form_raw, $form_sc, $resolved, $sc_res, $gf_res ] ) );
$sc_real  = hk9_form_provider( [ 'provider' => 'shortcode', 'shortcode' => '[hk9_test_form_sc]' ], 'contact' );
add_shortcode( 'hk9_test_form_sc', static fn(): string => '<div class="hk9-test-sc-form">form</div>' );
$sc_real2 = hk9_form_provider( [ 'provider' => 'shortcode', 'shortcode' => '[hk9_test_form_sc]' ], 'contact' );
$sc_html  = hk9_render_form_provider( $sc_real2 );
remove_shortcode( 'hk9_test_form_sc' );
( false === $sc_real['available'] && true === $sc_real2['available'] && str_contains( $sc_html, 'hk9-shortcode-form' ) && str_contains( $sc_html, 'hk9-test-sc-form' ) )
	? $hk9_pass( 'form_provider_shortcode_renders', 'registered shortcode → available, rendered inside .hk9-form-provider.hk9-shortcode-form via do_shortcode' )
	: $hk9_fail( 'form_provider_shortcode_renders', wp_json_encode( [ $sc_real, $sc_real2, $sc_html ] ) );

/* 16c. A registered shortcode that renders nothing → flagged unavailable with an editor note (built-in fallback); no-op for builtin / already-unavailable. */
add_shortcode( 'hk9_test_form_empty', static fn(): string => '' );
$sc_empty      = hk9_form_provider( [ 'provider' => 'shortcode', 'shortcode' => '[hk9_test_form_empty]' ], 'contact' );
$sc_empty_html = hk9_render_form_provider( $sc_empty );
$sc_flagged    = hk9_form_provider_no_output( $sc_empty );
remove_shortcode( 'hk9_test_form_empty' );
$builtin_res   = hk9_form_provider( [ 'provider' => 'builtin' ], 'contact' );
( true === $sc_empty['available'] && '' === $sc_empty_html && false === $sc_flagged['available'] && str_contains( $sc_flagged['notice'], '[hk9_test_form_empty]' )
	&& $builtin_res === hk9_form_provider_no_output( $builtin_res ) && $sc_res === hk9_form_provider_no_output( $sc_res ) )
	? $hk9_pass( 'form_provider_empty_output_flagged', 'available shortcode with empty output → hk9_form_provider_no_output() sets available=false + notice naming the tag; builtin and already-unavailable providers unchanged' )
	: $hk9_fail( 'form_provider_empty_output_flagged', wp_json_encode( [ $sc_empty, $sc_empty_html, $sc_flagged ] ) );

/* 16d. Gravity picker: definitions load without GFAPI; the options callback receives the current value, so a stored id whose form is gone survives a save as "(unavailable)". */
$gf_field = null;
foreach ( $form_def->fields as $f ) {
	if ( 'gravity_form_id' === $f['key'] ) {
		$gf_field = $f;
	}
}
$gf_help_static = is_array( $gf_field ) && is_string( $gf_field['help'] ) && str_contains( $gf_field['help'], 'Shown when the provider is Gravity Forms' );
if ( ! class_exists( 'GFAPI' ) ) {
	$hk9_blocked( 'gravity_picker_keeps_stale_id', 'Gravity Forms not active' );
} elseif ( ! $gf_help_static ) {
	$hk9_fail( 'gravity_picker_keeps_stale_id', 'gravity_form_id help is not the static text: ' . wp_json_encode( $gf_field['help'] ?? null ) );
} else {
	$gf_tmp = GFAPI::add_form( [ 'title' => 'HK9 Test GF Form', 'fields' => [], 'is_active' => true ] );
	if ( is_wp_error( $gf_tmp ) || (int) $gf_tmp <= 0 ) {
		$hk9_blocked( 'gravity_picker_keeps_stale_id', 'GFAPI::add_form failed: ' . ( is_wp_error( $gf_tmp ) ? $gf_tmp->get_error_message() : 'no id' ) );
	} else {
		$gf_tmp = (int) $gf_tmp;
		HK9\Core\Support\FormProviders::flush();
		$gf_opts_live = HK9\Core\Fields\Field::options( $gf_field, '' );
		$gf_opts_stale = HK9\Core\Fields\Field::options( $gf_field, '424242' );
		$gf_saved_live = $form_def->sanitize( [ '__present' => '1', 'provider' => 'gravity', 'gravity_form_id' => (string) $gf_tmp ] );
		$gf_saved_stale = $form_def->sanitize( [ '__present' => '1', 'provider' => 'gravity', 'gravity_form_id' => '424242' ] );
		$gf_saved_bogus = $form_def->sanitize( [ '__present' => '1', 'provider' => 'gravity', 'gravity_form_id' => '12abc' ] );
		$gf_control = ( new HK9\Core\Fields\Renderer() )->render_field( $gf_field, '424242', 'hk9_sec_contact_form', 'hk9_sec_contact_form' );
		$gf_ok = isset( $gf_opts_live[ (string) $gf_tmp ] ) && ! isset( $gf_opts_live['424242'] ) && ! isset( $gf_opts_live[''] )
			&& isset( $gf_opts_stale['424242'] ) && str_contains( $gf_opts_stale['424242'], '(unavailable)' )
			&& (string) $gf_tmp === $gf_saved_live['gravity_form_id'] && '424242' === $gf_saved_stale['gravity_form_id'] && '' === $gf_saved_bogus['gravity_form_id']
			&& $gf_saved_stale === $form_def->sanitize( $gf_saved_stale ) && ! is_wp_error( rest_validate_value_from_schema( $gf_saved_stale, $form_def->schema(), 'hk9_sec_contact_form' ) )
			&& str_contains( $gf_control, 'value="424242" selected' ) && str_contains( $gf_control, '(unavailable)' )
			&& false === hk9_form_provider( $gf_saved_stale, 'contact' )['available'] && true === hk9_form_provider( $gf_saved_live, 'contact' )['available'];
		GFAPI::delete_form( $gf_tmp );
		HK9\Core\Support\FormProviders::flush();
		$gf_status_opts = HK9\Core\Fields\Field::options( $gf_field, '' );
		$gf_status_ok   = [] === HK9\Core\Support\FormProviders::gravity_forms() ? ( isset( $gf_status_opts[''] ) && str_contains( $gf_status_opts[''], 'No Gravity Forms forms yet' ) && '' === $form_def->sanitize( [ '__present' => '1', 'provider' => 'gravity', 'gravity_form_id' => '' ] )['gravity_form_id'] ) : true;
		( $gf_ok && $gf_status_ok )
			? $hk9_pass( 'gravity_picker_keeps_stale_id', "static help in definition; live list has form #{$gf_tmp}; stored 424242 (no such form) kept as 'Form #424242 (unavailable)' on save, idempotent + schema-valid, selected in the rendered control, resolves unavailable (built-in fallback); '12abc' → ''; with no forms the '' option carries the status" )
			: $hk9_fail( 'gravity_picker_keeps_stale_id', wp_json_encode( [ $gf_opts_live, $gf_opts_stale, $gf_saved_live['gravity_form_id'] ?? null, $gf_saved_stale['gravity_form_id'] ?? null, $gf_saved_bogus['gravity_form_id'] ?? null, $gf_status_opts, substr( $gf_control, 0, 600 ) ] ) );
	}
}

/* 16e. Blank editor canvas: empty paragraph blocks / classic markup with no text count as no content (no empty band, no content.css); any other block or text counts. */
$blank_id = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'HK9 Test Blank Canvas', 'post_status' => 'publish', 'post_content' => "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\">&nbsp;</p>\n<!-- /wp:paragraph -->", 'page_template' => 'page-templates/about.php' ] );
$blank_cases = [];
$blank_set   = static function ( string $content ) use ( $blank_id ): void {
	wp_update_post( [ 'ID' => $blank_id, 'post_content' => $content ] );
	clean_post_cache( $blank_id );
};
$blank_cases['empty_paragraphs'] = hk9_content_is_blank( $blank_id ) && '' === hk9_editor_content_position( $blank_id, 'about' );
$blank_set( '' );
$blank_cases['empty_string'] = hk9_content_is_blank( $blank_id );
$blank_set( "<p>&nbsp;</p>\n<p><br></p>" );
$blank_cases['classic_nbsp'] = hk9_content_is_blank( $blank_id );
$blank_set( "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->" );
$blank_cases['text_paragraph'] = ! hk9_content_is_blank( $blank_id ) && 'after' === hk9_editor_content_position( $blank_id, 'about' );
$blank_set( "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:image {\"id\":1} -->\n<figure class=\"wp-block-image\"><img src=\"x.jpg\" alt=\"\"/></figure>\n<!-- /wp:image -->" );
$blank_cases['image_block'] = ! hk9_content_is_blank( $blank_id );
$blank_set( "<!-- wp:spacer {\"height\":\"40px\"} -->\n<div style=\"height:40px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->" );
$blank_cases['spacer_block'] = ! hk9_content_is_blank( $blank_id );
$blank_set( '<img src="x.jpg" alt="">' );
$blank_cases['classic_image'] = ! hk9_content_is_blank( $blank_id );
$blank_set( '[gallery ids="1,2"]' );
$blank_cases['classic_shortcode'] = ! hk9_content_is_blank( $blank_id );
wp_delete_post( $blank_id, true );
[] === array_keys( array_filter( $blank_cases, static fn( $ok ) => ! $ok ) )
	? $hk9_pass( 'blank_canvas_detection', 'blank: empty paragraph blocks (incl. &nbsp;/align), empty string, classic <p>&nbsp;</p><p><br></p> → position ""; content: text paragraph (position "after"), image block, spacer block, classic <img>, classic shortcode' )
	: $hk9_fail( 'blank_canvas_detection', 'failed cases: ' . implode( ', ', array_keys( array_filter( $blank_cases, static fn( $ok ) => ! $ok ) ) ) );

/* 16f. Layout revision formatter names the editor-content position. */
$fmt_post   = get_post( $page_id );
$fmt_before = RevisionGuard::format_for_diff( Layout::META_KEY, [ 'order' => [ 'legacy' ], 'hidden' => [], 'content_position' => 'before' ], $fmt_post );
$fmt_hide   = RevisionGuard::format_for_diff( Layout::META_KEY, [ 'order' => [ 'legacy' ], 'hidden' => [], 'content_position' => 'hide' ], $fmt_post );
( str_contains( $fmt_before, 'Editor content: ' . Layout::content_labels()['before'] ) && str_contains( $fmt_hide, 'Editor content: ' . Layout::content_labels()['hide'] ) && $fmt_before !== $fmt_hide && str_starts_with( $fmt_before, 'Order: legacy' ) )
	? $hk9_pass( 'layout_revision_formatter_shows_content_position', 'format_for_diff prints Order / Hidden / Editor content: <label>; before vs hide differ' )
	: $hk9_fail( 'layout_revision_formatter_shows_content_position', wp_json_encode( [ $fmt_before, $fmt_hide ] ) );

/* 14. Subscriber PUT → 403. */
$sub_id = wp_insert_user( [ 'user_login' => 'hk9_test_subscriber_' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ] );
wp_set_current_user( (int) $sub_id );
$forbidden = $hk9_rest( 'PUT', "/wp/v2/pages/$page_id", [ 'meta' => [ 'hk9_sec_hero_band' => array_merge( $hero, [ 'heading' => 'Hacked' ] ) ] ] );
wp_set_current_user( (int) $hk9_admin );
$heading_after = hk9_section( $page_id, 'hero_band' )['heading'];
( in_array( $forbidden->get_status(), [ 401, 403 ], true ) && 'Hacked' !== $heading_after )
	? $hk9_pass( 'subscriber_put_forbidden', 'PUT as subscriber → ' . $forbidden->get_status() . ', meta unchanged' )
	: $hk9_fail( 'subscriber_put_forbidden', 'status ' . $forbidden->get_status() );
// Direct meta write attempt via update_metadata with auth: edit_post_meta cap for subscriber.
$can = user_can( (int) $sub_id, 'edit_post_meta', $page_id, 'hk9_sec_hero_band' );
! $can ? $hk9_pass( 'auth_callback_denies_subscriber', 'edit_post_meta(hk9_sec_hero_band) false for subscriber' ) : $hk9_fail( 'auth_callback_denies_subscriber', 'allowed' );

/* 15. CPT meta (needs the post types from PostTypes\Registrar). */
if ( post_type_exists( 'hk9_story' ) && post_type_exists( 'hk9_barkode' ) ) {
	$story = wp_insert_post( [ 'post_type' => 'hk9_story', 'post_title' => 'HK9 Test Story', 'post_status' => 'publish', 'post_author' => (int) $hk9_admin ], true );
	$story_keys = get_registered_meta_keys( 'post', 'hk9_story' );
	$ok = isset( $story_keys['hk9_veteran_name'], $story_keys['hk9_gallery'], $story_keys['hk9_featured'] ) && 'array' === $story_keys['hk9_gallery']['type'] && 'boolean' === $story_keys['hk9_featured']['type'] && ! empty( $story_keys['hk9_gallery']['show_in_rest']['schema']['items'] );
	$ok ? $hk9_pass( 'cpt_meta_registered_typed', 'hk9_story: veteran_name string, gallery array(items), featured boolean, all with REST schemas' ) : $hk9_fail( 'cpt_meta_registered_typed', wp_json_encode( array_keys( $story_keys ) ) );
	// Details box save simulation.
	$_POST = [
		'post_ID'                   => $story,
		HK9\Core\Meta\MetaBox::NONCE_FIELD => wp_create_nonce( HK9\Core\Meta\MetaBox::NONCE_ACTION . $story ),
		'hk9_meta'                  => wp_slash( [ '__present' => '1', 'veteran_name' => ' Madison ', 'featured' => '1', 'relationship' => 'therapy', 'pairing_year' => '2022', 'gallery' => [ '999999' ] ] ),
	];
	wp_update_post( [ 'ID' => $story, 'post_title' => 'HK9 Test Story' ] );
	$_POST = [];
	( 'Madison' === get_post_meta( $story, 'hk9_veteran_name', true ) && true === hk9_cpt_meta( $story, 'featured' ) && 'therapy' === hk9_cpt_meta( $story, 'relationship' ) && [] === hk9_cpt_meta( $story, 'gallery' ) && '' === hk9_cpt_meta( $story, 'quote' ) )
		? $hk9_pass( 'cpt_details_save_and_accessor', 'Details save: trimmed text, "1"→true, invalid attachment dropped, hk9_cpt_meta() defaults' )
		: $hk9_fail( 'cpt_details_save_and_accessor', wp_json_encode( [ get_post_meta( $story, 'hk9_veteran_name', true ), hk9_cpt_meta( $story, 'featured' ), hk9_cpt_meta( $story, 'gallery' ) ] ) );
	$story_keys_rest = array_keys( array_filter( $story_keys, static fn( $a ) => ! empty( $a['show_in_rest'] ) ) );
	if ( ! post_type_supports( 'hk9_story', 'custom-fields' ) ) {
		( ! in_array( 'hk9_source_note', $story_keys_rest, true ) && in_array( 'hk9_veteran_name', $story_keys_rest, true ) )
			? $hk9_blocked( 'cpt_rest_hides_private', 'hk9_source_note registered without show_in_rest (' . count( $story_keys_rest ) . ' story keys exposed), but hk9_story lacks "custom-fields" support so REST exposes no meta at all — block-editor meta mirroring/preview for CPTs needs that support (PostTypes\\Registrar)' )
			: $hk9_fail( 'cpt_rest_hides_private', wp_json_encode( $story_keys_rest ) );
	} else {
		$story_rest = $hk9_rest( 'GET', "/wp/v2/hk9_story/$story", [ 'context' => 'view' ] )->get_data()['meta'] ?? [];
		( isset( $story_rest['hk9_veteran_name'] ) && ! array_key_exists( 'hk9_source_note', $story_rest ) )
			? $hk9_pass( 'cpt_rest_hides_private', 'hk9_story REST meta exposes hk9_veteran_name but not hk9_source_note' )
			: $hk9_fail( 'cpt_rest_hides_private', wp_json_encode( array_keys( $story_rest ) ) );
	}
	$bark_keys = get_registered_meta_keys( 'post', 'hk9_barkode' );
	$exposed = array_keys( array_filter( $bark_keys, static fn( $a ) => ! empty( $a['show_in_rest'] ) ) );
	( isset( $bark_keys['hk9_review_notes'] ) && empty( $exposed ) ) ? $hk9_pass( 'barkode_meta_not_in_rest', count( $bark_keys ) . ' barkode keys registered, none in REST (incl. hk9_review_notes)' ) : $hk9_fail( 'barkode_meta_not_in_rest', wp_json_encode( $exposed ) );
	if ( post_type_exists( 'hk9_person' ) ) {
		$person_field = HK9\Core\Meta\Definitions::field( 'hk9_person', 'email' );
		( '' === Sanitizer::sanitize_field( $person_field, 'someone@gmail.com' ) && 'info@heartlandk9s.org' === Sanitizer::sanitize_field( $person_field, 'Info@HeartlandK9s.org' ) )
			? $hk9_pass( 'person_email_org_domain_only', 'gmail dropped, org email kept' ) : $hk9_fail( 'person_email_org_domain_only', 'validation failed' );
	}
	wp_delete_post( $story, true );
} else {
	$hk9_blocked( 'cpt_meta_registered_typed', 'hk9_story/hk9_barkode post types are not registered on this site yet (PostTypes\Registrar)' );
}

/* 16. Datetime + link + relationship field coercion. */
$dt = HK9\Core\Fields\Field::normalize( [ 'type' => 'datetime', 'key' => 'start', 'time_optional' => true ] );
$ok = '2026-07-25 18:00' === Sanitizer::sanitize_field( $dt, '2026-07-25T18:00' ) && '2026-07-25' === Sanitizer::sanitize_field( $dt, [ 'date' => '2026-07-25', 'time' => '' ] ) && '' === Sanitizer::sanitize_field( $dt, '2026-13-40' );
$link = HK9\Core\Fields\Field::normalize( [ 'type' => 'link', 'key' => 'l' ] );
$lv   = Sanitizer::sanitize_field( $link, [ 'label' => 'Go', 'url' => '/donate/', 'post_id' => $page_id, 'target' => '_blank', 'rel' => 'noopener sponsored <b>' ] );
$ok   = $ok && $lv === [ 'label' => 'Go', 'url' => '/donate/', 'post_id' => $page_id, 'target' => '_blank', 'rel' => 'noopener sponsored' ];
$rel  = HK9\Core\Fields\Field::normalize( [ 'type' => 'relationship', 'key' => 'r', 'post_type' => 'page', 'multiple' => true, 'max' => 2 ] );
$rv   = Sanitizer::sanitize_field( $rel, [ $page_id, '999999', $home_id, $page_id, $home_id ] );
$ok   = $ok && [ $page_id, $home_id ] === $rv && 0 === Sanitizer::sanitize_field( HK9\Core\Fields\Field::normalize( [ 'type' => 'relationship', 'key' => 'r', 'post_type' => 'post' ] ), $page_id );
$ok ? $hk9_pass( 'field_types_coercion', 'datetime T→space / date-only / invalid; link rel filtered + post validated; relationship dedupe/max/type check' ) : $hk9_fail( 'field_types_coercion', wp_json_encode( [ $lv, $rv ] ) );

/* 17. Published-page preview (last: the REST autosave endpoint defines DOING_AUTOSAVE for the rest of the process): REST autosave carries meta, page untouched, hk9_section() sees it in the preview query. */
update_post_meta( $page_id, '_wp_page_template', 'page-templates/about.php' );
update_post_meta( $page_id, 'hk9_sec_hero_band', array_merge( $hero, [ 'heading' => 'Heading v1' ] ) );
Accessor::flush();
$preview_meta = [ 'hk9_sec_hero_band' => array_merge( $hero, [ 'heading' => 'Preview heading' ] ) ];
$auto = $hk9_rest( 'POST', "/wp/v2/pages/$page_id/autosaves", [ 'meta' => $preview_meta, 'title' => get_the_title( $page_id ), 'content' => get_post_field( 'post_content', $page_id ) ] );
$autosave = wp_get_post_autosave( $page_id, (int) $hk9_admin );
$auto_heading = $autosave ? ( get_post_meta( $autosave->ID, 'hk9_sec_hero_band', true )['heading'] ?? null ) : null;
$page_heading = get_post_meta( $page_id, 'hk9_sec_hero_band', true )['heading'] ?? null;
( in_array( $auto->get_status(), [ 200, 201 ], true ) && 'Preview heading' === $auto_heading && 'Heading v1' === $page_heading )
	? $hk9_pass( 'rest_autosave_carries_meta', sprintf( 'POST /autosaves %d → autosave %d heading "Preview heading"; page still "Heading v1"', $auto->get_status(), $autosave->ID ) )
	: $hk9_fail( 'rest_autosave_carries_meta', wp_json_encode( [ $auto->get_status(), $auto_heading, $page_heading ] ) );

// Frontend preview query (what the theme sees with ?preview_id=&preview_nonce=).
$_GET['preview']       = 'true';
$_GET['preview_id']    = (string) $page_id;
$_GET['preview_nonce'] = wp_create_nonce( 'post_preview_' . $page_id );
_show_post_preview();
$q = new WP_Query( [ 'page_id' => $page_id, 'preview' => true ] );
$seen = null;
if ( $q->have_posts() ) {
	$q->the_post();
	Accessor::flush();
	$seen = hk9_section( $page_id, 'hero_band' )['heading'] ?? null;
	wp_reset_postdata();
}
remove_filter( 'the_preview', '_set_preview' );
remove_filter( 'get_post_metadata', '_wp_preview_meta_filter', 10 );
unset( $_GET['preview'], $_GET['preview_id'], $_GET['preview_nonce'] );
Accessor::flush();
'Preview heading' === $seen ? $hk9_pass( 'preview_query_shows_unsaved_heading', 'WP_Query preview + _wp_preview_meta_filter: hk9_section() heading = "Preview heading"; saved = "' . hk9_section( $page_id, 'hero_band' )['heading'] . '"' ) : $hk9_fail( 'preview_query_shows_unsaved_heading', wp_json_encode( $seen ) );

/* 18. Classic preview fallback: stale autosave is deleted so post_preview() takes the create branch. */
$_POST = [
	'wp-preview'         => 'dopreview',
	'post_ID'            => $page_id,
	'_wpnonce'           => wp_create_nonce( 'update-post_' . $page_id ),
	MetaBox::NONCE_FIELD => wp_create_nonce( MetaBox::NONCE_ACTION . $page_id ),
];
MetaBox::classic_preview_fallback();
$_POST = [];
! wp_get_post_autosave( $page_id, (int) $hk9_admin ) ? $hk9_pass( 'classic_preview_fallback_deletes_autosave', 'admin_action_editpost prio 5 removed the stale autosave' ) : $hk9_fail( 'classic_preview_fallback_deletes_autosave', 'autosave still present' );

/* Cleanup. */
wp_delete_post( $page_id, true );
wp_delete_post( $home_id, true );
if ( ! empty( $sub_id ) && ! is_wp_error( $sub_id ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $sub_id );
}

$fails = count( array_filter( $hk9_results, static fn( $r ) => 'FAIL' === $r[1] ) );
echo "\n", count( $hk9_results ), ' checks, ', $fails, " failed\n";
if ( $fails > 0 ) {
	exit( 1 );
}
