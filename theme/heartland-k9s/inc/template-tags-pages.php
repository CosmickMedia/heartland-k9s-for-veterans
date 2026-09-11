<?php
/**
 * Helpers for the reference page templates (about, program, veterans,
 * get-involved, barkode, stories, contact) and their section parts.
 *
 * - Maps the shared section ids whose reference layout differs from the
 *   generic part (`featured` → featured.php, `ways` → ways.php).
 * - Provides plugin-less structure defaults so the templates still render
 *   imported section meta when the companion plugin is inactive.
 * - Record helpers (stories, teams, partners) and contact-row resolution.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Section ids that have a dedicated reference part although the plugin
 * registers them under a shared type (stories `featured` = testimonial,
 * get-involved `ways` = feature_cards).
 *
 * @return array<string, array<string, string>> template => [ id => part type ].
 */
function hk9_pages_part_overrides(): array {
	return [
		'stories'      => [ 'featured' => 'featured' ],
		'get-involved' => [ 'ways' => 'ways' ],
	];
}

/**
 * Swap the part type for sections with a dedicated reference layout.
 *
 * @param array  $sections Resolved sections [ ['id','type','data'], … ].
 * @param int    $post_id  Page id.
 * @param string $template Template slug.
 * @return array
 */
function hk9_pages_render_sections( array $sections, int $post_id, string $template ): array {
	$overrides = hk9_pages_part_overrides()[ $template ] ?? [];
	if ( empty( $overrides ) ) {
		return $sections;
	}

	foreach ( $sections as &$section ) {
		$id = (string) ( $section['id'] ?? '' );
		if ( isset( $overrides[ $id ] ) && locate_template( 'template-parts/sections/' . $overrides[ $id ] . '.php' ) ) {
			$section['type'] = $overrides[ $id ];
		}
	}
	unset( $section );

	return $sections;
}
add_filter( 'hk9/theme/render_sections', 'hk9_pages_render_sections', 10, 3 );

/**
 * Plugin-less structure defaults for the reference templates.
 *
 * Only the section ids, part types and empty data are declared: with the plugin
 * inactive, hk9_section() merges any imported `hk9_sec_*` meta on top, so the
 * page keeps rendering. The reference copy itself lives in the plugin registry.
 *
 * @param array  $sections id => [type, hidden, data].
 * @param string $template Template slug.
 * @return array
 */
function hk9_pages_section_defaults( array $sections, string $template ): array {
	$link = static fn(): array => hk9_theme_link( '', '' );
	$sec  = static fn( string $type, array $data ): array => [
		'type'   => $type,
		'hidden' => false,
		'data'   => $data,
	];

	$hero_band = $sec(
		'hero_band',
		[
			'eyebrow' => '',
			'heading' => '',
			'text'    => '',
			'pattern' => 'none',
		]
	);
	$hero_image = $sec(
		'hero_image',
		[
			'eyebrow'             => '',
			'eyebrow_icon'        => '',
			'heading'             => '',
			'heading_break_after' => '',
			'text'                => '',
			'image'               => 0,
			'image_mobile'        => 0,
			'focal'               => 'center',
			'height'              => '60vh',
			'overlay'             => '70',
			'gradient'            => false,
			'buttons'             => [],
			'animate'             => false,
		]
	);
	$cta = static fn( string $tone ): array => $sec(
		'cta_band',
		[
			'heading' => '',
			'text'    => '',
			'buttons' => [],
			'tone'    => $tone,
		]
	);
	$cards = static fn( string $align ): array => $sec(
		'feature_cards',
		[
			'heading' => '',
			'intro'   => '',
			'divider' => true,
			'align'   => $align,
			'columns' => '3',
			'cards'   => [],
		]
	);

	switch ( $template ) {
		case 'about':
			return [
				'hero_band' => $hero_band,
				'legacy'    => $sec( 'legacy', [ 'eyebrow' => '', 'heading' => '', 'body' => '', 'image' => 0, 'image_side' => 'left' ] ),
				'values'    => $cards( 'left' ),
				'cta'       => $cta( 'tint' ),
			];

		case 'program':
			$hero_image['data']['height'] = '60vh';
			return [
				'hero_image' => $hero_image,
				'steps'      => $sec( 'steps', [ 'heading' => '', 'intro' => '', 'steps' => [] ] ),
				'providers'  => $sec( 'providers', [ 'icon' => 'shield-alert', 'heading' => '', 'text' => '', 'button' => $link() ] ),
				'cta'        => $cta( 'navy' ),
			];

		case 'veterans':
			$hero_band['data']['pattern'] = 'stars';
			return [
				'hero_band' => $hero_band,
				'questions' => $sec( 'questions', [ 'heading' => '', 'intro' => '', 'items' => [], 'footer_text' => '', 'button' => $link(), 'secondary_link' => $link() ] ),
				'expect'    => $sec( 'expect', [ 'heading' => '', 'steps' => [], 'card_icon' => 'shield-check', 'card_title' => '', 'card_text' => '', 'card_button' => $link() ] ),
				'ada'       => $cta( 'navy' ),
			];

		case 'get-involved':
			return [
				'hero_band' => $hero_band,
				'ways'      => $sec( 'ways', [ 'heading' => '', 'intro' => '', 'cards' => [] ] ),
				'partners'  => $sec( 'partners', [ 'icon' => 'heart', 'heading' => '', 'body' => '', 'button' => $link(), 'show_logos' => false ] ),
			];

		case 'barkode':
			$hero_image['data']['height']   = '70vh';
			$hero_image['data']['overlay']  = '80';
			$hero_image['data']['gradient'] = true;
			return [
				'hero_image' => $hero_image,
				'story'      => $sec( 'story', [ 'icon' => 'heart-pulse', 'heading' => '', 'body' => '' ] ),
				'protects'   => $cards( 'left' ),
				'cta'        => $cta( 'plain' ),
			];

		case 'stories':
			return [
				'hero_band' => $hero_band,
				'featured'  => $sec( 'featured', [ 'source' => 'story', 'story' => 0, 'quote' => '', 'name' => '', 'meta' => '', 'image' => 0 ] ),
				'list'      => $sec( 'list', [ 'heading' => '', 'mode' => 'auto', 'stories' => [], 'count' => 6, 'empty_text' => '' ] ),
				'teams'     => $sec( 'teams', [ 'heading' => '', 'intro' => '', 'mode' => 'auto', 'teams' => [], 'show' => true ] ),
				'cta'       => $cta( 'plain' ),
			];

		case 'contact':
			return [
				'hero_band' => $hero_band,
				'info'      => $sec( 'info', [ 'heading' => '', 'rows' => [] ] ),
				'form'      => $sec( 'form', [ 'heading' => '', 'intro' => '', 'form' => 'contact', 'success_heading' => '', 'success_text' => '' ] ),
			];
	}

	return $sections;
}
add_filter( 'hk9/theme/section_defaults', 'hk9_pages_section_defaults', 10, 2 );

if ( ! function_exists( 'hk9_section_meta_key' ) ) {
	/**
	 * Plugin-compatible meta key resolver (plugin-less fallback).
	 *
	 * The plugin stores the `list`/`form` sections under template-specific keys.
	 *
	 * @param string $template   Template slug.
	 * @param string $section_id Section id.
	 * @return string
	 */
	function hk9_section_meta_key( string $template, string $section_id ): string {
		$special = [
			'stories:list'      => 'hk9_sec_stories_list',
			'campaigns:list'    => 'hk9_sec_campaigns_list',
			'teams:list'        => 'hk9_sec_teams_list',
			'contact:form'      => 'hk9_sec_contact_form',
			'application:form'  => 'hk9_sec_application_form',
		];
		return $special[ $template . ':' . $section_id ] ?? 'hk9_sec_' . $section_id;
	}
}

/**
 * Section data for the reference templates, honouring the plugin's
 * template-specific meta keys when the plugin is inactive.
 *
 * @param int    $post_id  Page id.
 * @param string $template Template slug.
 * @param string $id       Section id.
 * @return array
 */
function hk9_pages_section( int $post_id, string $template, string $id ): array {
	$data = hk9_section( $post_id, $id );

	if ( ! hk9_plugin_active() && $post_id > 0 ) {
		$key = hk9_section_meta_key( $template, $id );
		if ( 'hk9_sec_' . $id !== $key ) {
			$meta = get_post_meta( $post_id, $key, true );
			if ( is_array( $meta ) ) {
				unset( $meta['__present'] );
				$data = array_replace( $data, $meta );
			}
		}
	}

	return $data;
}

/**
 * Rich-text field → safe HTML (paragraphs added when the value is plain text).
 *
 * @param string $html Richtext value.
 * @return string
 */
function hk9_pages_richtext( string $html ): string {
	$html = trim( $html );
	if ( '' === $html ) {
		return '';
	}
	if ( false === stripos( $html, '<p' ) ) {
		$html = wpautop( $html );
	}
	return wp_kses_post( $html );
}

/**
 * Read a CPT field (`hk9_<key>`), through the plugin when available.
 *
 * @param int    $post_id Record id.
 * @param string $key     Field key without the prefix.
 * @return mixed
 */
function hk9_pages_meta( int $post_id, string $key ) {
	if ( function_exists( 'hk9_cpt_meta' ) ) {
		return hk9_cpt_meta( $post_id, $key );
	}
	return get_post_meta( $post_id, 'hk9_' . $key, true );
}

/**
 * Read a CPT text field as a trimmed string.
 *
 * @param int    $post_id Record id.
 * @param string $key     Field key.
 * @return string
 */
function hk9_pages_text( int $post_id, string $key ): string {
	$value = hk9_pages_meta( $post_id, $key );
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Whether a post id is a published record of the given type.
 *
 * @param int    $post_id   Post id.
 * @param string $post_type Post type.
 * @return bool
 */
function hk9_pages_is_published( int $post_id, string $post_type ): bool {
	return $post_id > 0 && $post_type === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
}

/**
 * Published stories: featured first, then menu order, then newest.
 *
 * @param int   $count   Number of stories.
 * @param int[] $exclude Ids to skip.
 * @return WP_Post[]
 */
function hk9_pages_stories_auto( int $count, array $exclude = [] ): array {
	if ( $count <= 0 ) {
		return [];
	}

	$args = [
		'post_type'              => 'hk9_story',
		'post_status'            => 'publish',
		'posts_per_page'         => $count,
		'post__not_in'           => array_values( array_filter( array_map( 'intval', $exclude ) ) ),
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'orderby'                => [
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		],
	];

	$posts = get_posts( $args );
	hk9_rec_prime( array_map( static fn( WP_Post $p ): int => (int) $p->ID, $posts ) );

	// Featured first, keeping the menu order inside each group.
	usort(
		$posts,
		static function ( WP_Post $a, WP_Post $b ): int {
			$fa = (int) ( (bool) hk9_pages_meta( $a->ID, 'featured' ) );
			$fb = (int) ( (bool) hk9_pages_meta( $b->ID, 'featured' ) );
			if ( $fa !== $fb ) {
				return $fb <=> $fa;
			}
			if ( $a->menu_order !== $b->menu_order ) {
				return $a->menu_order <=> $b->menu_order;
			}
			return strcmp( $b->post_date, $a->post_date );
		}
	);

	return $posts;
}

/**
 * The latest featured story (or the latest story when none is featured).
 *
 * @param int[] $exclude Ids to skip.
 * @return int 0 when there is no published story.
 */
function hk9_pages_featured_story_id( array $exclude = [] ): int {
	$posts = hk9_pages_stories_auto( 1, $exclude );
	return ! empty( $posts ) ? (int) $posts[0]->ID : 0;
}

/**
 * Published records by explicit id list (order kept, unknown/unpublished dropped).
 *
 * @param int[]  $ids       Ids.
 * @param string $post_type Post type.
 * @return WP_Post[]
 */
function hk9_pages_records_by_ids( array $ids, string $post_type ): array {
	hk9_rec_prime( $ids );
	$out = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( hk9_pages_is_published( $id, $post_type ) ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post ) {
				$out[] = $post;
			}
		}
	}
	return $out;
}

/**
 * Attribution line for a story: "USMC Veteran · Paired with Gunther".
 *
 * @param int $story_id Story id.
 * @return string
 */
function hk9_pages_story_attribution( int $story_id ): string {
	$branch = hk9_pages_text( $story_id, 'branch' );
	$canine = hk9_pages_text( $story_id, 'canine_name' );
	$year   = hk9_pages_text( $story_id, 'pairing_year' );

	$parts = [];
	if ( '' !== $branch ) {
		$parts[] = $branch;
	}
	if ( '' !== $canine ) {
		/* translators: %s: dog name */
		$parts[] = sprintf( __( 'Paired with %s', 'heartland-k9s' ), $canine );
	}
	if ( '' !== $year ) {
		/* translators: %s: year */
		$parts[] = sprintf( __( 'Paired in %s', 'heartland-k9s' ), $year );
	}

	return implode( ' · ', $parts );
}

/**
 * Story quote or, failing that, the excerpt/content summary (plain text).
 *
 * @param WP_Post $post  Story.
 * @param int     $words Word cap for the fallback summary.
 * @return string
 */
function hk9_pages_story_summary( WP_Post $post, int $words = 40 ): string {
	$quote = hk9_pages_text( (int) $post->ID, 'quote' );
	if ( '' !== $quote ) {
		return trim( $quote, "\"“” \n\r\t" );
	}
	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		return wp_strip_all_tags( (string) $post->post_excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), $words, '…' );
}

/**
 * Story card (stories listing).
 *
 * @param WP_Post $post Story.
 */
function hk9_pages_story_card( WP_Post $post ): void {
	$story_id = (int) $post->ID;
	$url      = get_permalink( $post );
	$name     = hk9_pages_text( $story_id, 'veteran_name' );
	$title    = get_the_title( $post );
	$summary  = hk9_pages_story_summary( $post );
	$meta     = hk9_pages_story_attribution( $story_id );
	$is_quote = '' !== hk9_pages_text( $story_id, 'quote' );
	$heading  = '' !== $name ? $name : $title;
	?>
	<article class="hk9-story-card">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<a class="hk9-story-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo hk9_image( (int) get_post_thumbnail_id( $post ), 'hk9-card', [ 'sizes' => '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 48px), 496px', 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			</a>
		<?php endif; ?>
		<div class="hk9-story-card__body">
			<?php echo hk9_icon( 'quote', [ 'class' => 'hk9-story-card__glyph', 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $summary ) : ?>
				<p class="hk9-story-card__quote"><?php echo $is_quote ? '“' . esc_html( $summary ) . '”' : esc_html( $summary ); ?></p>
			<?php endif; ?>
			<div class="hk9-story-card__footer">
				<h3 class="hk9-story-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $heading ); ?></a></h3>
				<?php if ( '' !== $meta ) : ?>
					<p class="hk9-story-card__meta"><?php echo esc_html( $meta ); ?></p>
				<?php endif; ?>
				<a class="hk9-link hk9-story-card__link" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Read the story', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: story title */ __( ': %s', 'heartland-k9s' ), $title ) ); ?></span></a>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Team status label.
 *
 * @param string $status Status key.
 * @return string
 */
function hk9_pages_team_status_label( string $status ): string {
	$labels = [
		'in-training' => __( 'In training', 'heartland-k9s' ),
		'graduated'   => __( 'Graduated', 'heartland-k9s' ),
		'therapy'     => __( 'Therapy team', 'heartland-k9s' ),
	];
	return $labels[ $status ] ?? '';
}

/**
 * Team card (stories listing "Teams in Training" block and team listings).
 *
 * @param WP_Post $post Team.
 */
function hk9_pages_team_card( WP_Post $post, array $args = [] ): void {
	$team_id = (int) $post->ID;
	// Rendered card width: 1 column below 768px, 2 columns to 1023px, then 3 (320px) or 2 (496px).
	$sizes   = ! empty( $args['sizes'] ) ? (string) $args['sizes'] : '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 48px), 320px';
	$url     = get_permalink( $post );
	$title   = get_the_title( $post );
	$canine  = hk9_pages_text( $team_id, 'canine_name' );
	$handler = hk9_pages_text( $team_id, 'handler_name' );
	$status  = sanitize_key( hk9_pages_text( $team_id, 'status' ) );
	$label   = hk9_pages_team_status_label( $status );
	$summary = hk9_pages_text( $team_id, 'summary' );
	if ( '' === $summary ) {
		$summary = has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 30, '…' );
	}

	$pair = [];
	if ( '' !== $canine ) {
		/* translators: %s: dog name */
		$pair[] = sprintf( __( 'K9: %s', 'heartland-k9s' ), $canine );
	}
	if ( '' !== $handler ) {
		/* translators: %s: handler name */
		$pair[] = sprintf( __( 'Handler: %s', 'heartland-k9s' ), $handler );
	}
	?>
	<article class="hk9-team-card">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<a class="hk9-team-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo hk9_image( (int) get_post_thumbnail_id( $post ), 'hk9-card', [ 'sizes' => $sizes, 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			</a>
		<?php endif; ?>
		<div class="hk9-team-card__body">
			<?php if ( '' !== $label ) : ?>
				<span class="hk9-status hk9-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
			<h3 class="hk9-team-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<?php if ( ! empty( $pair ) ) : ?>
				<p class="hk9-team-card__pair"><?php echo esc_html( implode( ' · ', $pair ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $summary ) : ?>
				<p class="hk9-team-card__text"><?php echo esc_html( $summary ); ?></p>
			<?php endif; ?>
			<a class="hk9-link hk9-team-card__link" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Meet the team', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: team title */ __( ': %s', 'heartland-k9s' ), $title ) ); ?></span></a>
		</div>
	</article>
	<?php
}

/**
 * Published partners of a type (taxonomy term slug), in menu order.
 *
 * @param string $type  `hk9_partner_type` slug.
 * @param int    $limit Max items.
 * @return WP_Post[]
 */
function hk9_pages_partners_by_type( string $type, int $limit = 24 ): array {
	if ( ! post_type_exists( 'hk9_partner' ) ) {
		return [];
	}

	$args = [
		'post_type'      => 'hk9_partner',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => [
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		],
	];
	if ( '' !== $type && taxonomy_exists( 'hk9_partner_type' ) ) {
		$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small curated set.
			[
				'taxonomy' => 'hk9_partner_type',
				'field'    => 'slug',
				'terms'    => $type,
			],
		];
	}

	return get_posts( $args );
}

/**
 * Partner logo grid item (logo linked to the website when set).
 *
 * @param WP_Post $post Partner.
 */
function hk9_pages_partner_logo_item( WP_Post $post ): void {
	$partner_id = (int) $post->ID;
	$name       = get_the_title( $post );
	$website    = hk9_pages_meta( $partner_id, 'website' );
	$website    = is_array( $website ) ? $website : [];
	$url        = hk9_link_is_set( $website ) ? hk9_theme_link_url( $website ) : '';
	$logo_id    = has_post_thumbnail( $post ) ? (int) get_post_thumbnail_id( $post ) : 0;

	$inner = '';
	if ( $logo_id > 0 ) {
		// The logo's alt text is the accessible name (a duplicate sr-only span would be read twice).
		$inner .= hk9_image( $logo_id, 'hk9-logo', [ 'alt' => $name, 'sizes' => '(max-width: 767px) calc(50vw - 56px), 160px' ] );
	} else {
		$inner .= '<span class="hk9-partners__name">' . esc_html( $name ) . '</span>';
	}

	if ( '' !== $url ) {
		echo '<li><a class="hk9-partners__item" href="' . esc_url( $url ) . '"' . hk9_theme_link_attrs( $website ) . '>' . $inner . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	} else {
		echo '<li><div class="hk9-partners__item">' . $inner . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}

/**
 * Resolve a contact info row (`source` phone|phone_secondary|email|hours|address|custom)
 * to display lines and an optional href.
 *
 * @param array $row Row data { source, icon, label, value, link }.
 * @return array{label:string,lines:string[],href:string,external:bool}|null Null when the row has nothing to show.
 */
function hk9_pages_contact_row( array $row ): ?array {
	$source = (string) ( $row['source'] ?? 'custom' );
	$label  = trim( (string) ( $row['label'] ?? '' ) );
	$lines  = [];
	$href   = '';
	$link   = is_array( $row['link'] ?? null ) ? $row['link'] : [];

	switch ( $source ) {
		case 'phone':
		case 'phone_secondary':
			$number = trim( (string) hk9_theme_option( 'phone' === $source ? 'contact.phone_main' : 'contact.phone_secondary' ) );
			if ( '' === $number ) {
				return null;
			}
			$lines[] = $number;
			$href    = hk9_tel_href( $number );
			if ( '' === $label ) {
				$label = (string) hk9_theme_option( 'phone' === $source ? 'contact.phone_main_label' : 'contact.phone_secondary_label' );
			}
			break;

		case 'email':
			$email = sanitize_email( (string) hk9_theme_option( 'contact.email' ) );
			if ( '' === $email ) {
				return null;
			}
			$lines[] = $email;
			$href    = 'mailto:' . $email;
			break;

		case 'hours':
			$days  = trim( (string) hk9_theme_option( 'contact.hours_days' ) );
			$hours = trim( (string) hk9_theme_option( 'contact.hours' ) );
			if ( '' === $days && '' === $hours ) {
				return null;
			}
			if ( '' !== $days ) {
				$lines[] = $days;
			}
			if ( '' !== $hours ) {
				$lines[] = $hours;
			}
			break;

		case 'address':
			$street = trim( (string) hk9_theme_option( 'contact.address_line1' ) );
			$line2  = trim( (string) hk9_theme_option( 'contact.address_line2' ) );
			$city   = trim( trim( (string) hk9_theme_option( 'contact.city' ) ) . ', ' . trim( (string) hk9_theme_option( 'contact.state' ) ) . ' ' . trim( (string) hk9_theme_option( 'contact.zip' ) ), ', ' );
			foreach ( [ $street, $line2, $city ] as $part ) {
				if ( '' !== $part && ',' !== $part ) {
					$lines[] = $part;
				}
			}
			if ( empty( $lines ) ) {
				return null;
			}
			break;

		default:
			$value = trim( (string) ( $row['value'] ?? '' ) );
			if ( '' === $value ) {
				return null;
			}
			$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' ) );
			break;
	}

	// An explicit link overrides the derived one.
	if ( hk9_link_is_set( $link ) ) {
		$href = hk9_theme_link_url( $link );
	}

	return [
		'label'    => $label,
		'lines'    => $lines,
		'href'     => $href,
		'external' => hk9_link_is_set( $link ),
		'link'     => $link,
	];
}

/**
 * Sanitize an ordered list of relationship ids.
 *
 * @param mixed $value Relationship field value.
 * @return int[]
 */
function hk9_pages_ids( $value ): array {
	if ( is_int( $value ) ) {
		return $value > 0 ? [ $value ] : [];
	}
	if ( ! is_array( $value ) ) {
		return [];
	}
	return array_values( array_filter( array_map( 'intval', $value ), static fn( int $id ): bool => $id > 0 ) );
}
