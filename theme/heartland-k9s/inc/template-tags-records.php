<?php
/**
 * Helpers for the migrated page templates and the record (CPT) singles:
 * section renderer with type remapping, CPT meta access, status badges,
 * record queries, event date helpers, record cards, galleries with the core
 * lightbox, and plugin-less section defaults for the migrated templates.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Sections
 * ---------------------------------------------------------------------- */

/**
 * Section types that share a plugin type name with another template
 * (`list` on stories/campaigns/teams, `form` on contact/application) mapped
 * to the dedicated template part of a given template.
 *
 * @param string $template Template slug.
 * @return array<string, string> section id => part type.
 */
function hk9_rec_section_type_map( string $template ): array {
	$map = [
		'campaigns'   => [ 'list' => 'campaigns_list' ],
		'teams'       => [ 'list' => 'teams_list' ],
		'application' => [ 'form' => 'application_form' ],
	];

	/**
	 * Filter the section id → part type overrides of a template.
	 *
	 * @param array  $map      id => type.
	 * @param string $template Template slug.
	 */
	return (array) apply_filters( 'hk9/theme/section_type_map', $map[ $template ] ?? [], $template );
}

/**
 * Render the visible sections of a page like hk9_render_sections(), with
 * per-template part overrides (see hk9_rec_section_type_map()) and optional
 * callbacks injected before/after a given section id.
 *
 * @param int    $post_id  Page id.
 * @param string $template Template slug.
 * @param array  $inject   { before: [id => callable], after: [id => callable] }.
 */
function hk9_rec_render_sections( int $post_id, string $template, array $inject = [] ): void {
	$layout = hk9_sections_layout( $post_id, $template );
	$map    = hk9_rec_section_type_map( $template );

	$sections = [];
	foreach ( $layout as $id ) {
		$type = $map[ $id ] ?? hk9_section_type( $template, $id );
		if ( ! locate_template( 'template-parts/sections/' . $type . '.php' ) ) {
			continue;
		}
		$sections[] = [
			'id'   => $id,
			'type' => $type,
			'data' => hk9_section( $post_id, $id ),
		];
	}

	/** This filter is documented in inc/sections.php */
	$sections = apply_filters( 'hk9/theme/render_sections', $sections, $post_id, $template );

	$before = is_array( $inject['before'] ?? null ) ? $inject['before'] : [];
	$after  = is_array( $inject['after'] ?? null ) ? $inject['after'] : [];

	foreach ( $sections as $section ) {
		$id = (string) $section['id'];
		if ( isset( $before[ $id ] ) && is_callable( $before[ $id ] ) ) {
			call_user_func( $before[ $id ] );
			unset( $before[ $id ] );
		}

		get_template_part(
			'template-parts/sections/' . $section['type'],
			null,
			[
				'data'     => $section['data'],
				'post_id'  => $post_id,
				'id'       => $id,
				'template' => $template,
			]
		);

		if ( isset( $after[ $id ] ) && is_callable( $after[ $id ] ) ) {
			call_user_func( $after[ $id ] );
			unset( $after[ $id ] );
		}
	}

	// Anchors that never rendered (hidden section): still run the callbacks so page content is never lost.
	foreach ( array_merge( $before, $after ) as $callback ) {
		if ( is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}

/**
 * Whether a site URL points at an existing page (by path), so a settings default
 * that names a reference slug only counts when that page exists here.
 *
 * @param string $url Absolute URL under home_url().
 * @return bool
 */
function hk9_rec_site_path_exists( string $url ): bool {
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$rel  = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $rel && str_starts_with( $path, $rel ) ) {
		$path = trim( substr( $path, strlen( $rel ) ), '/' );
	}
	if ( '' === $path ) {
		return true;
	}
	$page = get_page_by_path( $path );
	return $page instanceof WP_Post && 'publish' === $page->post_status;
}

/**
 * CTA link from a `links.*` / `forms.*` setting with the label overridden, falling
 * back to a site path when the setting is empty or its default names a page that
 * does not exist here (same rule as hk9_footer_fallback_links()).
 *
 * @param string $setting  Settings key (`links.donate`, …).
 * @param string $label    Link label.
 * @param string $fallback Fallback path (`/donate/`).
 * @return array Link value.
 */
function hk9_rec_settings_link( string $setting, string $label, string $fallback ): array {
	$link = hk9_theme_option( $setting );
	if ( is_array( $link ) ) {
		$url = hk9_theme_link_url( $link );
		if ( '' !== $url && empty( $link['post_id'] ) && str_starts_with( $url, home_url( '/' ) ) && ! hk9_rec_site_path_exists( $url ) ) {
			$url = '';
		}
		if ( '' !== $url ) {
			return array_merge( $link, [ 'label' => $label ] );
		}
	}
	return hk9_theme_link( $label, home_url( $fallback ) );
}

/**
 * Plugin-less fallback section defaults for the migrated templates so the
 * page structure (and the record lists) still render without the plugin.
 *
 * @param array  $sections id => [type, hidden, data].
 * @param string $template Template slug.
 * @return array
 */
function hk9_rec_section_defaults( array $sections, string $template ): array {
	$cta = static fn( bool $hidden = false ): array => [
		'type'   => 'cta_band',
		'hidden' => $hidden,
		'data'   => [
			'heading' => __( 'Join Us in Our Mission', 'heartland-k9s' ),
			'text'    => __( 'Every donation, volunteer hour, and shared story helps us provide another service dog to a veteran in need.', 'heartland-k9s' ),
			'buttons' => [
				[
					'link'  => hk9_rec_settings_link( 'links.donate', __( 'Make a Donation', 'heartland-k9s' ), '/donate/' ),
					'style' => 'primary',
				],
				[
					'link'  => hk9_rec_settings_link( 'links.volunteer', __( 'Ways to Volunteer', 'heartland-k9s' ), '/get-involved/' ),
					'style' => 'outline',
				],
			],
			'tone'    => 'tint',
		],
	];

	$extra = [];
	switch ( $template ) {
		case 'donate':
			$extra = [
				'options' => [ 'type' => 'options', 'hidden' => false, 'data' => [ 'heading' => __( 'Ways to Give', 'heartland-k9s' ), 'intro' => '', 'items' => [] ] ],
				'mail_in' => [ 'type' => 'mail_in', 'hidden' => false, 'data' => [ 'heading' => __( 'Donate by Mail', 'heartland-k9s' ), 'text' => '', 'use_settings_address' => true ] ],
				'tax'     => [ 'type' => 'tax', 'hidden' => false, 'data' => [ 'text' => '' ] ],
				'cta'     => $cta(),
			];
			break;
		case 'events':
			$extra = [
				'upcoming' => [ 'type' => 'upcoming', 'hidden' => false, 'data' => [ 'heading' => __( 'Upcoming Events', 'heartland-k9s' ), 'empty_text' => __( 'There are no upcoming events scheduled right now. Check back soon.', 'heartland-k9s' ), 'count' => 10 ] ],
				'past'     => [ 'type' => 'past', 'hidden' => false, 'data' => [ 'show' => true, 'heading' => __( 'Past Events', 'heartland-k9s' ), 'count' => 6 ] ],
				'cta'      => $cta(),
			];
			break;
		case 'campaigns':
			$extra = [
				'list' => [ 'type' => 'campaigns_list', 'hidden' => false, 'data' => [ 'heading' => __( 'Campaigns', 'heartland-k9s' ), 'intro' => '', 'mode' => 'auto', 'campaigns' => [], 'show_sponsors' => true ] ],
				'cta'  => $cta(),
			];
			break;
		case 'partners':
			$extra = [
				'logos' => [ 'type' => 'logos', 'hidden' => false, 'data' => [ 'heading' => __( 'Back the Pack Partners', 'heartland-k9s' ), 'intro' => '', 'mode' => 'auto', 'type' => 'back-the-pack', 'partners' => [], 'columns' => '4' ] ],
				'cta'   => $cta(),
			];
			break;
		case 'people':
			$extra = [
				'grid' => [ 'type' => 'grid', 'hidden' => false, 'data' => [ 'heading' => __( 'Meet the Team', 'heartland-k9s' ), 'intro' => '', 'mode' => 'auto', 'people' => [], 'columns' => '3' ] ],
				'cta'  => $cta(),
			];
			break;
		case 'teams':
			$extra = [
				'list' => [ 'type' => 'teams_list', 'hidden' => false, 'data' => [ 'heading' => __( 'Current Teams in Training', 'heartland-k9s' ), 'intro' => '', 'mode' => 'auto', 'status' => 'in-training', 'teams' => [] ] ],
				'cta'  => $cta(),
			];
			break;
		case 'highlighted-team':
			$extra = [
				'team' => [ 'type' => 'team', 'hidden' => false, 'data' => [ 'team' => 0, 'heading' => __( 'Our Highlighted Team', 'heartland-k9s' ), 'intro' => '' ] ],
				'cta'  => $cta(),
			];
			break;
		case 'gallery':
			$extra = [
				'gallery_options' => [ 'type' => 'gallery_options', 'hidden' => false, 'data' => [ 'lightbox' => true, 'columns' => '3', 'captions' => true, 'images' => [] ] ],
			];
			break;
		case 'application':
			$extra = [
				'form' => [ 'type' => 'application_form', 'hidden' => false, 'data' => [ 'heading' => __( 'Initial Application Inquiry', 'heartland-k9s' ), 'notice' => '', 'success_page' => hk9_rec_settings_link( 'forms.application_success_page', '', '/thank-you/' ), 'show_five_questions_link' => true ] ],
			];
			break;
		case 'thank-you':
			$extra = [ 'cta' => $cta( true ) ];
			break;
		case 'landing':
			$extra = [ 'tiers' => [ 'type' => 'tiers', 'hidden' => true, 'data' => [ 'heading' => '', 'intro' => '', 'items' => [] ] ] ];
			break;
	}

	foreach ( $extra as $id => $section ) {
		if ( ! isset( $sections[ $id ] ) ) {
			$sections[ $id ] = $section;
		}
	}

	// Keep the CTA last (landing declares it before the tiers were appended).
	if ( isset( $sections['cta'] ) ) {
		$last = $sections['cta'];
		unset( $sections['cta'] );
		$sections['cta'] = $last;
	}

	return $sections;
}
add_filter( 'hk9/theme/section_defaults', 'hk9_rec_section_defaults', 10, 2 );

/* -------------------------------------------------------------------------
 * CPT meta + status
 * ---------------------------------------------------------------------- */

/**
 * CPT field value (`hk9_<key>` meta) with the field default when unset.
 *
 * @param int    $post_id Post id.
 * @param string $key     Field key without the prefix.
 * @param mixed  $default Fallback when the plugin is absent or the meta is empty.
 * @return mixed
 */
function hk9_rec_meta( int $post_id, string $key, $default = null ) {
	if ( $post_id <= 0 ) {
		return $default;
	}
	if ( function_exists( 'hk9_cpt_meta' ) ) {
		$value = hk9_cpt_meta( $post_id, $key, $default );
		return null === $value ? $default : $value;
	}
	$value = get_post_meta( $post_id, 'hk9_' . $key, true );
	if ( '' === $value || null === $value || false === $value ) {
		return $default;
	}
	return $value;
}

/**
 * Human label for a record status / program type.
 *
 * @param string $status Status slug.
 * @return string
 */
function hk9_rec_status_label( string $status ): string {
	$labels = [
		'in-training' => __( 'In Training', 'heartland-k9s' ),
		'graduated'   => __( 'Graduated', 'heartland-k9s' ),
		'therapy'     => __( 'Therapy Dog', 'heartland-k9s' ),
		'service'     => __( 'Service Dog', 'heartland-k9s' ),
		'active'      => __( 'Active', 'heartland-k9s' ),
		'completed'   => __( 'Completed', 'heartland-k9s' ),
		'paused'      => __( 'Paused', 'heartland-k9s' ),
		'scheduled'   => __( 'Scheduled', 'heartland-k9s' ),
		'cancelled'   => __( 'Cancelled', 'heartland-k9s' ),
		'postponed'   => __( 'Postponed', 'heartland-k9s' ),
	];
	return $labels[ $status ] ?? ucwords( str_replace( '-', ' ', $status ) );
}

/**
 * Status pill markup.
 *
 * @param string $status Status slug.
 * @param string $class  Extra class.
 * @return string
 */
function hk9_rec_status_badge( string $status, string $class = '' ): string {
	$status = sanitize_key( $status );
	if ( '' === $status ) {
		return '';
	}
	$classes = array_filter( [ 'hk9-status', 'hk9-status--' . $status, $class ] );
	return '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">' . esc_html( hk9_rec_status_label( $status ) ) . '</span>';
}

/* -------------------------------------------------------------------------
 * Record queries
 * ---------------------------------------------------------------------- */

/**
 * Upper bound for an "all records" listing query.
 *
 * @param string $post_type Post type being listed.
 * @return int posts_per_page value (> 0).
 */
function hk9_rec_query_limit( string $post_type ): int {
	/**
	 * Filter the maximum number of records an auto-mode listing loads.
	 *
	 * @param int    $limit     Default 200.
	 * @param string $post_type Post type.
	 */
	$limit = (int) apply_filters( 'hk9/theme/rec_query_limit', 200, $post_type );
	return $limit > 0 ? $limit : 200;
}

/**
 * Prime the object cache for a set of records: the posts, their meta and their
 * featured images (post + meta), so a card loop over them issues no per-record
 * queries. Already-cached ids are skipped by core.
 *
 * @param int[] $ids Post ids.
 */
function hk9_rec_prime( array $ids ): void {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ) => $id > 0 ) ) );
	if ( empty( $ids ) ) {
		return;
	}
	_prime_post_caches( $ids, false, true );

	$thumbs = [];
	foreach ( $ids as $id ) {
		$thumb = (int) get_post_meta( $id, '_thumbnail_id', true );
		if ( $thumb > 0 ) {
			$thumbs[] = $thumb;
		}
	}
	if ( ! empty( $thumbs ) ) {
		_prime_post_caches( array_values( array_unique( $thumbs ) ), false, true );
	}
}

/**
 * Published records of a type in editorial order (menu_order, then title).
 *
 * Bounded by hk9_rec_query_limit() unless `posts_per_page` is passed; featured
 * images are primed so the cards render from the object cache.
 *
 * @param string $post_type Post type.
 * @param array  $args      Extra WP_Query args (meta_query, tax_query, posts_per_page…).
 * @return WP_Post[]
 */
function hk9_rec_query( string $post_type, array $args = [] ): array {
	if ( ! post_type_exists( $post_type ) ) {
		return [];
	}
	$query = new WP_Query(
		array_merge(
			[
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => hk9_rec_query_limit( $post_type ),
				'orderby'                => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
			],
			$args
		)
	);
	update_post_thumbnail_cache( $query );
	return array_values( array_filter( $query->posts, static fn( $p ) => $p instanceof WP_Post ) );
}

/**
 * Published records by id, in the given order (manual mode lists).
 *
 * @param array  $ids       Post ids.
 * @param string $post_type Expected post type.
 * @return WP_Post[]
 */
function hk9_rec_by_ids( array $ids, string $post_type ): array {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ) => $id > 0 ) ) );
	if ( empty( $ids ) ) {
		return [];
	}
	hk9_rec_prime( $ids );
	$out = [];
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( $post instanceof WP_Post && $post_type === $post->post_type && 'publish' === $post->post_status ) {
			$out[] = $post;
		}
	}
	return $out;
}

/**
 * Records for a listing section: manual ids when `mode` is manual, otherwise auto.
 *
 * @param array  $data      Section data (mode + <key> ids).
 * @param string $key       Relationship field key.
 * @param string $post_type Post type.
 * @param array  $auto_args Extra query args for auto mode.
 * @return WP_Post[]
 */
function hk9_rec_section_records( array $data, string $key, string $post_type, array $auto_args = [] ): array {
	$mode = ( $data['mode'] ?? 'auto' ) === 'manual' ? 'manual' : 'auto';
	$ids  = is_array( $data[ $key ] ?? null ) ? $data[ $key ] : [];

	if ( 'manual' === $mode ) {
		$posts = hk9_rec_by_ids( $ids, $post_type );
		if ( ! empty( $posts ) ) {
			return $posts;
		}
	}
	return hk9_rec_query( $post_type, $auto_args );
}

/**
 * Whether a section heading repeats the page title / hero heading (so it can be
 * visually hidden and the section left without a duplicate landmark name).
 *
 * @param string $heading Heading text.
 * @param int    $post_id Page id.
 * @return bool
 */
function hk9_rec_heading_duplicate( string $heading, int $post_id ): bool {
	$heading = trim( $heading );
	if ( '' === $heading ) {
		return false;
	}
	$title     = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
	$hero      = hk9_section( $post_id, 'hero_band' );
	$hero_text = trim( (string) ( $hero['heading'] ?? '' ) );
	return 0 === strcasecmp( $heading, $title ) || ( '' !== $hero_text && 0 === strcasecmp( $heading, $hero_text ) );
}

/**
 * Section attributes for a listing: aria-labelledby only when the heading is not a duplicate.
 *
 * @param string $id      Section id.
 * @param string $heading Heading text.
 * @param int    $post_id Page id.
 * @return array
 */
function hk9_rec_section_attrs( string $id, string $heading, int $post_id ): array {
	return hk9_rec_heading_duplicate( $heading, $post_id ) ? [] : [ 'aria-labelledby' => 'hk9-' . sanitize_html_class( $id ) . '-title' ];
}

/**
 * Section header for a listing: the h2 is visually hidden when it merely repeats the
 * page title shown in the hero band (the divider/intro still render when there is an intro).
 *
 * @param string $id      Section id (for the heading id attribute).
 * @param string $heading Heading text (already defaulted).
 * @param string $intro   Intro text.
 * @param int    $post_id Page id.
 */
function hk9_rec_section_header( string $id, string $heading, string $intro, int $post_id ): void {
	$duplicate = hk9_rec_heading_duplicate( $heading, $post_id );
	$intro     = trim( $intro );
	$dom_id    = 'hk9-' . sanitize_html_class( $id ) . '-title';

	if ( $duplicate && '' === $intro ) {
		echo '<h2 id="' . esc_attr( $dom_id ) . '" class="screen-reader-text">' . esc_html( $heading ) . '</h2>';
		return;
	}

	echo '<div class="hk9-section__header">';
	if ( $duplicate ) {
		echo '<h2 id="' . esc_attr( $dom_id ) . '" class="screen-reader-text">' . esc_html( $heading ) . '</h2>';
	} else {
		echo '<h2 id="' . esc_attr( $dom_id ) . '" class="hk9-section__title">' . esc_html( $heading ) . '</h2>';
		echo '<span class="hk9-divider" aria-hidden="true"></span>';
	}
	echo hk9_paragraphs( $intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
	echo '</div>';
}

/**
 * Card template part for a record.
 *
 * @param string  $type Card slug (event, campaign, team, person, partner, story).
 * @param WP_Post $post Record.
 * @param array   $args Extra args passed to the part.
 */
function hk9_rec_card( string $type, WP_Post $post, array $args = [] ): void {
	get_template_part( 'template-parts/cards/' . sanitize_key( $type ), null, array_merge( $args, [ 'post' => $post ] ) );
}

/**
 * Sponsor / partner logo strip.
 *
 * @param array  $ids   Partner ids.
 * @param string $class Extra class.
 * @return string
 */
function hk9_rec_sponsor_logos( array $ids, string $class = '' ): string {
	$partners = hk9_rec_by_ids( $ids, 'hk9_partner' );
	if ( empty( $partners ) ) {
		return '';
	}
	$html = '<ul class="hk9-sponsors' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '">';
	foreach ( $partners as $partner ) {
		$name    = get_the_title( $partner );
		$logo    = has_post_thumbnail( $partner ) ? hk9_image( (int) get_post_thumbnail_id( $partner ), 'hk9-logo', [ 'alt' => $name, 'sizes' => '160px' ] ) : '';
		$website = hk9_rec_meta( (int) $partner->ID, 'website', [] );
		$url     = is_array( $website ) ? hk9_theme_link_url( $website ) : '';
		$inner   = '' !== $logo ? $logo : '<span class="hk9-sponsors__name">' . esc_html( $name ) . '</span>';

		$html .= '<li class="hk9-sponsors__item">';
		if ( '' !== $url ) {
			$html .= '<a class="hk9-sponsors__link" href="' . esc_url( $url ) . '"' . hk9_theme_link_attrs( is_array( $website ) ? $website : [] ) . ' aria-label="' . esc_attr( $name ) . '">' . $inner . '</a>';
		} else {
			$html .= $inner;
		}
		$html .= '</li>';
	}
	return $html . '</ul>';
}

/* -------------------------------------------------------------------------
 * Events
 * ---------------------------------------------------------------------- */

/**
 * Parsed start/end for an event (plugin helper, or a site-timezone fallback).
 *
 * @param int $post_id Event id.
 * @return array{start:?DateTimeImmutable,end:?DateTimeImmutable,timezone:DateTimeZone,all_day:bool,time_tbd:bool,has_time:bool}
 */
function hk9_rec_event_dates( int $post_id ): array {
	if ( function_exists( 'hk9_event_dates' ) ) {
		$data = hk9_event_dates( $post_id );
		return [
			'start'    => $data['start'] ?? null,
			'end'      => $data['end'] ?? null,
			'timezone' => $data['timezone'] ?? wp_timezone(),
			'all_day'  => ! empty( $data['all_day'] ),
			'time_tbd' => ! empty( $data['time_tbd'] ),
			'has_time' => ! empty( $data['has_time'] ),
		];
	}

	$tz    = wp_timezone();
	$parse = static function ( $value ) use ( $tz ): ?DateTimeImmutable {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		try {
			return new DateTimeImmutable( $value, $tz );
		} catch ( Exception $e ) {
			return null;
		}
	};
	$start = $parse( get_post_meta( $post_id, 'hk9_start', true ) );
	$end   = $parse( get_post_meta( $post_id, 'hk9_end', true ) );

	return [
		'start'    => $start,
		'end'      => $end,
		'timezone' => $tz,
		'all_day'  => (bool) get_post_meta( $post_id, 'hk9_all_day', true ),
		'time_tbd' => (bool) get_post_meta( $post_id, 'hk9_time_tbd', true ),
		'has_time' => $start instanceof DateTimeImmutable && (bool) preg_match( '/\d{1,2}:\d{2}/', (string) get_post_meta( $post_id, 'hk9_start', true ) ),
	];
}

/**
 * Whether an event is upcoming.
 *
 * @param int $post_id Event id.
 * @return bool
 */
function hk9_rec_event_upcoming( int $post_id ): bool {
	if ( function_exists( 'hk9_event_is_upcoming' ) ) {
		return (bool) hk9_event_is_upcoming( $post_id );
	}
	$dates = hk9_rec_event_dates( $post_id );
	$ref   = $dates['end'] ?? $dates['start'];
	return $ref instanceof DateTimeImmutable && $ref->getTimestamp() >= time();
}

/**
 * Formatted date/time range for an event.
 *
 * @param int $post_id Event id.
 * @return string
 */
function hk9_rec_event_range( int $post_id ): string {
	if ( function_exists( 'hk9_event_datetime_range' ) ) {
		return (string) hk9_event_datetime_range( $post_id );
	}
	$dates = hk9_rec_event_dates( $post_id );
	if ( ! $dates['start'] instanceof DateTimeImmutable ) {
		return '';
	}
	$out = wp_date( 'D, M j, Y', $dates['start']->getTimestamp(), $dates['timezone'] );
	if ( $dates['has_time'] && ! $dates['all_day'] && ! $dates['time_tbd'] ) {
		$out .= ' · ' . wp_date( (string) get_option( 'time_format', 'g:i a' ), $dates['start']->getTimestamp(), $dates['timezone'] );
		if ( $dates['end'] instanceof DateTimeImmutable ) {
			$out .= ' – ' . wp_date( (string) get_option( 'time_format', 'g:i a' ), $dates['end']->getTimestamp(), $dates['timezone'] );
		}
	}
	return $out;
}

/**
 * Events by scope (plugin query, or a simple fallback).
 *
 * @param string $scope `upcoming` | `past`.
 * @param int    $count Max events.
 * @return WP_Post[]
 */
function hk9_rec_events( string $scope, int $count ): array {
	$scope = 'past' === $scope ? 'past' : 'upcoming';
	if ( function_exists( 'hk9_events_query' ) ) {
		$posts = hk9_events_query( [ 'scope' => $scope, 'count' => $count, 'return' => 'posts' ] );
		$posts = array_values( array_filter( (array) $posts, static fn( $p ) => $p instanceof WP_Post ) );
		hk9_rec_prime( array_map( static fn( WP_Post $p ): int => (int) $p->ID, $posts ) );
		return $posts;
	}

	// Plugin-less fallback: classify in SQL against the site-local "now" (`hk9_start`/`hk9_end`
	// are stored as `Y-m-d H:i` or `Y-m-d`, so a CHAR comparison orders correctly) and load
	// only `$count` rows. An event with an end date is upcoming until its end; otherwise
	// until its start — the same rule hk9_rec_event_upcoming() applies per event.
	$upcoming = 'upcoming' === $scope;
	$boundary = current_time( 'Y-m-d H:i' );
	$no_end   = [
		'relation' => 'OR',
		[
			'key'     => 'hk9_end',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => 'hk9_end',
			'value'   => '',
			'compare' => '=',
		],
	];
	if ( $upcoming ) {
		$by_end = [
			'key'     => 'hk9_end',
			'value'   => $boundary,
			'compare' => '>=',
			'type'    => 'CHAR',
		];
	} else {
		$by_end = [
			'relation' => 'AND',
			[
				'key'     => 'hk9_end',
				'value'   => '',
				'compare' => '!=',
			],
			[
				'key'     => 'hk9_end',
				'value'   => $boundary,
				'compare' => '<',
				'type'    => 'CHAR',
			],
		];
	}
	$meta_query = [
		'relation' => 'AND',
		[
			'key'     => 'hk9_start',
			'value'   => '',
			'compare' => '!=',
		],
		[
			'relation' => 'OR',
			$by_end,
			[
				'relation' => 'AND',
				$no_end,
				[
					'key'     => 'hk9_start',
					'value'   => $boundary,
					'compare' => $upcoming ? '>=' : '<',
					'type'    => 'CHAR',
				],
			],
		],
	];

	return hk9_rec_query(
		'hk9_event',
		[
			'posts_per_page' => $count > 0 ? $count : hk9_rec_query_limit( 'hk9_event' ),
			'meta_key'       => 'hk9_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'orderby'        => 'meta_value',
			'order'          => $upcoming ? 'ASC' : 'DESC',
		]
	);
}

/**
 * Month/day date block for an event card.
 *
 * @param int $post_id Event id.
 * @return string
 */
function hk9_rec_event_date_block( int $post_id ): string {
	$dates = hk9_rec_event_dates( $post_id );
	$start = $dates['start'];
	if ( ! $start instanceof DateTimeImmutable ) {
		return '';
	}
	$ts = $start->getTimestamp();
	$tz = $dates['timezone'];

	return sprintf(
		'<time class="hk9-date" datetime="%s"><span class="hk9-date__month">%s</span><span class="hk9-date__day">%s</span><span class="hk9-date__year">%s</span></time>',
		esc_attr( $start->format( $dates['has_time'] ? 'c' : 'Y-m-d' ) ),
		esc_html( wp_date( 'M', $ts, $tz ) ),
		esc_html( wp_date( 'j', $ts, $tz ) ),
		esc_html( wp_date( 'Y', $ts, $tz ) )
	);
}

/**
 * Google Maps search URL for an address (a plain link; nothing is embedded).
 *
 * @param string $query Venue and/or address.
 * @return string
 */
function hk9_rec_maps_url( string $query ): string {
	$query = trim( preg_replace( '/\s+/', ' ', str_replace( [ "\r", "\n" ], ' ', $query ) ) );
	if ( '' === $query ) {
		return '';
	}
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
}

/**
 * Registration link for an event: external ticket pages open in a new tab.
 *
 * @param array $link Link value.
 * @return array
 */
function hk9_rec_event_registration_link( array $link ): array {
	$url = hk9_theme_link_url( $link );
	if ( '' !== $url && ! str_starts_with( $url, home_url() ) ) {
		$link['target'] = '_blank';
	}
	if ( '' === trim( (string) ( $link['label'] ?? '' ) ) ) {
		$link['label'] = __( 'Register', 'heartland-k9s' );
	}
	return $link;
}

/* -------------------------------------------------------------------------
 * Galleries (core gallery block + core lightbox)
 * ---------------------------------------------------------------------- */

/**
 * Gallery rendering options applied by the block filters while a gallery is
 * being rendered through hk9_rec_gallery_begin()/hk9_rec_gallery_end().
 *
 * @param array|null $set New options, or null to read.
 * @return array|null
 */
function hk9_rec_gallery_options( ?array $set = null ): ?array {
	static $options = null;
	if ( func_num_args() > 0 ) {
		$options = $set;
	}
	return $options;
}

/**
 * Start applying gallery options (lightbox, captions, columns) to core gallery blocks.
 *
 * @param array $options { lightbox: bool, captions: bool, columns: int }.
 */
function hk9_rec_gallery_begin( array $options ): void {
	hk9_rec_gallery_options(
		[
			'lightbox' => ! empty( $options['lightbox'] ),
			'captions' => ! isset( $options['captions'] ) || ! empty( $options['captions'] ),
			'columns'  => max( 0, min( 8, (int) ( $options['columns'] ?? 0 ) ) ),
		]
	);
}

/**
 * Stop applying gallery options.
 */
function hk9_rec_gallery_end(): void {
	hk9_rec_gallery_options( null );
}

/**
 * Apply the active gallery options to core gallery / image blocks:
 * lightbox on (link removed so the click opens the lightbox), captions
 * stripped when disabled, and the column count overridden.
 *
 * @param array|mixed $parsed Parsed block.
 * @return array|mixed
 */
function hk9_rec_gallery_block_data( $parsed ) {
	$options = hk9_rec_gallery_options();
	if ( null === $options || ! is_array( $parsed ) || empty( $parsed['blockName'] ) ) {
		return $parsed;
	}

	if ( 'core/gallery' === $parsed['blockName'] ) {
		if ( $options['columns'] > 0 ) {
			$parsed['attrs']['columns'] = $options['columns'];
			$parsed                     = hk9_rec_gallery_rewrite_html( $parsed, static fn( string $html ): string => preg_replace( '/\bcolumns-(?:\d+|default)\b/', 'columns-' . $options['columns'], $html, 1 ) );
		}
		if ( $options['lightbox'] ) {
			$parsed['attrs']['linkTo'] = 'none';
		}
		if ( ! $options['captions'] ) {
			$parsed = hk9_rec_gallery_rewrite_html( $parsed, static fn( string $html ): string => preg_replace( '#<figcaption\b[^>]*>.*?</figcaption>#is', '', $html ) );
		}
		return $parsed;
	}

	if ( 'core/image' !== $parsed['blockName'] ) {
		return $parsed;
	}

	if ( $options['lightbox'] ) {
		$parsed['attrs']['lightbox']        = [ 'enabled' => true ];
		$parsed['attrs']['linkDestination'] = 'none';
		unset( $parsed['attrs']['href'] );
		$parsed = hk9_rec_gallery_rewrite_html( $parsed, static fn( string $html ): string => preg_replace( '#<a\b[^>]*>\s*(<img\b[^>]*>)\s*</a>#is', '$1', $html ) );
	}
	if ( ! $options['captions'] ) {
		// Strip the figcaption from the markup only: `caption` is a rich-text attribute and
		// setting it in attrs trips core's schema validation notice.
		$parsed = hk9_rec_gallery_rewrite_html( $parsed, static fn( string $html ): string => preg_replace( '#<figcaption\b[^>]*>.*?</figcaption>#is', '', $html ) );
	}

	return $parsed;
}
add_filter( 'render_block_data', 'hk9_rec_gallery_block_data' );

/**
 * Apply a callback to the HTML chunks of a parsed block.
 *
 * @param array    $parsed   Parsed block.
 * @param callable $callback string → string.
 * @return array
 */
function hk9_rec_gallery_rewrite_html( array $parsed, callable $callback ): array {
	if ( isset( $parsed['innerHTML'] ) && is_string( $parsed['innerHTML'] ) ) {
		$parsed['innerHTML'] = (string) $callback( $parsed['innerHTML'] );
	}
	if ( isset( $parsed['innerContent'] ) && is_array( $parsed['innerContent'] ) ) {
		foreach ( $parsed['innerContent'] as $i => $chunk ) {
			if ( is_string( $chunk ) ) {
				$parsed['innerContent'][ $i ] = (string) $callback( $chunk );
			}
		}
	}
	return $parsed;
}

/**
 * Prime the attachments referenced by image/gallery blocks in a block-content
 * string (`"id":N` attributes and `wp-image-N` classes) before it is rendered, so
 * the core image block (lightbox metadata, srcset, alt) works from the cache.
 *
 * @param string $content Block content.
 */
function hk9_rec_prime_content_images( string $content ): void {
	if ( '' === $content ) {
		return;
	}
	$ids = [];
	if ( preg_match_all( '/"id":\s*(\d+)/', $content, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}
	if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ) => $id > 0 ) ) );
	if ( ! empty( $ids ) ) {
		_prime_post_caches( $ids, false, true );
	}
}

/**
 * Render attachment ids as a core gallery block (so the core lightbox and
 * gallery navigation apply), wrapped in `.hk9-gallery-wrap`.
 *
 * @param array $ids     Attachment ids.
 * @param array $options { lightbox: bool (default true), captions: bool (default true), columns: int (default 3), class: string, sizes: string }.
 * @return string
 */
function hk9_rec_gallery( array $ids, array $options = [] ): string {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ) => $id > 0 ) ) );
	if ( empty( $ids ) ) {
		return '';
	}
	// One round trip for the attachment posts + meta (attached file, image meta, alt) so the
	// per-image checks and hk9_image() below run from the object cache.
	_prime_post_caches( $ids, false, true );
	$ids = array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
	if ( empty( $ids ) ) {
		return '';
	}

	$columns  = max( 1, min( 8, (int) ( $options['columns'] ?? 3 ) ) );
	$lightbox = ! isset( $options['lightbox'] ) || ! empty( $options['lightbox'] );
	$captions = ! isset( $options['captions'] ) || ! empty( $options['captions'] );
	$sizes    = (string) ( $options['sizes'] ?? '(max-width: 767px) calc(50vw - 24px), (max-width: 1023px) 33vw, 320px' );

	$inner = '';
	foreach ( $ids as $id ) {
		$img = hk9_image( $id, 'large', [ 'sizes' => $sizes, 'class' => 'wp-image-' . $id ] );
		if ( '' === $img ) {
			continue;
		}
		$attrs   = [ 'id' => $id, 'sizeSlug' => 'large' ];
		$caption = $captions ? trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $id ) ) ) : '';
		if ( $lightbox ) {
			$attrs['lightbox']        = [ 'enabled' => true ];
			$attrs['linkDestination'] = 'none';
			$figure                   = $img;
		} else {
			$attrs['linkDestination'] = 'media';
			$figure                   = '<a href="' . esc_url( (string) wp_get_attachment_url( $id ) ) . '">' . $img . '</a>';
		}
		if ( '' !== $caption ) {
			$figure .= '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>';
		}
		$inner .= '<!-- wp:image ' . wp_json_encode( $attrs ) . ' --><figure class="wp-block-image size-large">' . $figure . '</figure><!-- /wp:image -->';
	}
	if ( '' === $inner ) {
		return '';
	}

	$block = '<!-- wp:gallery ' . wp_json_encode( [ 'columns' => $columns, 'imageCrop' => true, 'linkTo' => $lightbox ? 'none' : 'media', 'sizeSlug' => 'large' ] ) . ' -->'
		. '<figure class="wp-block-gallery has-nested-images columns-' . $columns . ' is-cropped">' . $inner . '</figure>'
		. '<!-- /wp:gallery -->';

	hk9_rec_gallery_begin( [ 'lightbox' => $lightbox, 'captions' => $captions, 'columns' => $columns ] );
	$html = do_blocks( $block );
	hk9_rec_gallery_end();

	$class = 'hk9-gallery-wrap hk9-gallery-wrap--' . $columns . ( ! empty( $options['class'] ) ? ' ' . (string) $options['class'] : '' );
	return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
}

/* -------------------------------------------------------------------------
 * Record singles
 * ---------------------------------------------------------------------- */

/**
 * Allowed HTML for the small inline fragments the record heroes accept (a status
 * pill, an hk9_icon() SVG followed by a text span): `span` plus the SVG subset the
 * sprite is built from (lucide primitives + the inlined <symbol>/<use>).
 *
 * @return array wp_kses allowed-HTML array.
 */
function hk9_rec_fragment_kses(): array {
	static $allowed = null;
	if ( null !== $allowed ) {
		return $allowed;
	}
	$allowed = [
		'span'     => [ 'class' => true ],
		'svg'      => [
			'xmlns'           => true,
			'xmlns:xlink'     => true,
			'class'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'role'            => true,
			'aria-hidden'     => true,
			'aria-labelledby' => true,
			'focusable'       => true,
		],
		'symbol'   => [
			'id'              => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		],
		'use'      => [ 'href' => true, 'xlink:href' => true ],
		'title'    => [ 'id' => true ],
		'path'     => [ 'd' => true ],
		'circle'   => [ 'cx' => true, 'cy' => true, 'r' => true ],
		'ellipse'  => [ 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ],
		'rect'     => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ],
		'line'     => [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ],
		'polyline' => [ 'points' => true ],
		'polygon'  => [ 'points' => true ],
	];
	return $allowed;
}

/**
 * Sanitise a hero fragment (badge / meta chip) to the allowed span + icon markup.
 *
 * @param string $html Fragment.
 * @return string
 */
function hk9_rec_fragment( string $html ): string {
	if ( '' === $html ) {
		return '';
	}
	// kses lowercases attribute names; restore the one camelCase SVG attribute the sprite uses
	// (HTML parsers adjust it inside <svg> anyway, this keeps the markup byte-identical).
	return str_replace( ' viewbox="', ' viewBox="', wp_kses( $html, hk9_rec_fragment_kses() ) );
}

/**
 * Hero band for a record single: eyebrow, title (h1), optional meta chips and text.
 *
 * `badge` and each `meta` entry are small HTML fragments (hk9_rec_status_badge(),
 * hk9_icon() + <span>); they are run through hk9_rec_fragment() (wp_kses) here.
 *
 * @param array $args { eyebrow: string, title: string, text: string, meta: string[] (HTML fragments), badge: string (HTML), pattern: string }.
 */
function hk9_rec_hero( array $args ): void {
	$title   = trim( (string) ( $args['title'] ?? get_the_title() ) );
	$eyebrow = trim( (string) ( $args['eyebrow'] ?? '' ) );
	$text    = trim( (string) ( $args['text'] ?? '' ) );
	$meta    = array_values( array_filter( is_array( $args['meta'] ?? null ) ? $args['meta'] : [], static fn( $m ) => is_string( $m ) && '' !== trim( $m ) ) );
	$meta    = array_values( array_filter( array_map( 'hk9_rec_fragment', $meta ), static fn( string $m ): bool => '' !== trim( $m ) ) );
	$badge   = hk9_rec_fragment( (string) ( $args['badge'] ?? '' ) );
	$pattern = (string) ( $args['pattern'] ?? 'none' );
	$pattern = in_array( $pattern, [ 'none', 'stars', 'grid' ], true ) ? $pattern : 'none';

	$classes = [ 'hk9-hero', 'hk9-hero--band', 'hk9-hero--record' ];
	if ( 'none' !== $pattern ) {
		$classes[] = 'hk9-pattern';
		$classes[] = 'hk9-pattern--' . $pattern;
	}
	?>
	<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" aria-labelledby="hk9-hero-title">
		<div class="hk9-hero__content">
			<?php if ( '' !== $eyebrow || '' !== $badge ) : ?>
				<div class="hk9-hero__eyebrow-row">
					<?php if ( '' !== $eyebrow ) : ?>
						<span class="hk9-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
					<?php endif; ?>
					<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses'd in hk9_rec_fragment(). ?>
				</div>
			<?php endif; ?>
			<h1 id="hk9-hero-title" class="hk9-hero__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( ! empty( $meta ) ) : ?>
				<ul class="hk9-hero__meta">
					<?php foreach ( $meta as $item ) : ?>
						<li><?php echo $item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses'd in hk9_rec_fragment(). ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== $text ) : ?>
				<p class="hk9-hero__text hk9-copy"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * "Back to listing" link.
 *
 * @param string $setting Settings link key (`links.stories`, …).
 * @param string $label   Link text.
 * @param string $fallback Fallback path when the setting is empty.
 * @return string
 */
function hk9_rec_back_link( string $setting, string $label, string $fallback = '/' ): string {
	$link = hk9_theme_option( $setting );
	$url  = is_array( $link ) ? hk9_theme_link_url( $link ) : '';
	if ( '' === $url ) {
		$url = home_url( $fallback );
	}
	return '<a class="hk9-link hk9-back-link" href="' . esc_url( $url ) . '">' . hk9_icon( 'arrow-right', [ 'class' => 'hk9-icon--flip', 'size' => 16 ] ) . esc_html( $label ) . '</a>';
}

/**
 * Meta line fragments for a story (veteran, branch, canine) — escaped HTML.
 *
 * @param int $post_id Story id.
 * @return string[]
 */
function hk9_rec_story_meta( int $post_id ): array {
	$veteran = trim( (string) hk9_rec_meta( $post_id, 'veteran_name', '' ) );
	$branch  = trim( (string) hk9_rec_meta( $post_id, 'branch', '' ) );
	$canine  = trim( (string) hk9_rec_meta( $post_id, 'canine_name', '' ) );
	$year    = trim( (string) hk9_rec_meta( $post_id, 'pairing_year', '' ) );

	$items = [];
	if ( '' !== $veteran ) {
		$items[] = hk9_icon( 'star', [ 'size' => 16 ] ) . '<span>' . esc_html( $veteran ) . '</span>';
	}
	if ( '' !== $branch ) {
		$items[] = hk9_icon( 'shield-check', [ 'size' => 16 ] ) . '<span>' . esc_html( $branch ) . '</span>';
	}
	if ( '' !== $canine ) {
		/* translators: %s: dog name */
		$items[] = hk9_icon( 'paw-print', [ 'size' => 16 ] ) . '<span>' . esc_html( sprintf( __( 'Paired with %s', 'heartland-k9s' ), $canine ) ) . '</span>';
	}
	if ( '' !== $year ) {
		$items[] = hk9_icon( 'calendar', [ 'size' => 16 ] ) . '<span>' . esc_html( $year ) . '</span>';
	}
	return $items;
}

/**
 * Link value → button when set, '' otherwise (thin wrapper used by the record templates).
 *
 * @param mixed  $link  Link value.
 * @param string $style Button style.
 * @param array  $attrs Button attrs.
 * @return string
 */
function hk9_rec_button( $link, string $style = 'primary', array $attrs = [] ): string {
	return is_array( $link ) && hk9_link_is_set( $link ) ? hk9_button( $link, $style, $attrs ) : '';
}
