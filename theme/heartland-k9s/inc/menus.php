<?php
/**
 * Navigation menus: link/item classes for the header + footer menus, a
 * reference-order fallback when no menu is assigned, duplicate-id suppression
 * (the primary menu renders twice: desktop + mobile).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reference navigation used when the `primary` location has no menu.
 * Only pages that exist are linked.
 *
 * @return array<int, array{label:string,path:string}>
 */
function hk9_reference_nav(): array {
	return [
		[ 'label' => __( 'About', 'heartland-k9s' ), 'path' => 'about' ],
		[ 'label' => __( 'Program', 'heartland-k9s' ), 'path' => 'program' ],
		[ 'label' => __( 'Veterans', 'heartland-k9s' ), 'path' => 'veterans' ],
		[ 'label' => __( 'BarKode', 'heartland-k9s' ), 'path' => 'barkode' ],
		[ 'label' => __( 'Stories', 'heartland-k9s' ), 'path' => 'stories' ],
		[ 'label' => __( 'Get Involved', 'heartland-k9s' ), 'path' => 'get-involved' ],
		[ 'label' => __( 'Contact', 'heartland-k9s' ), 'path' => 'contact' ],
	];
}

/**
 * Render the primary menu (desktop or mobile flavour).
 *
 * @param string $flavour `desktop` | `mobile`.
 */
function hk9_primary_menu( string $flavour = 'desktop' ): void {
	$mobile = 'mobile' === $flavour;

	wp_nav_menu(
		[
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => $mobile ? 'hk9-header__mobile-list' : 'hk9-header__list',
			'menu_id'        => '',
			'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
			'depth'          => 1,
			'fallback_cb'    => 'hk9_primary_menu_fallback',
			'hk9_flavour'    => $flavour,
			'hk9_last_id'    => 0, // Set by hk9_primary_menu_objects() once the items are loaded.
		]
	);
}

/**
 * Record the id of the last top-level item of the desktop primary menu on the
 * wp_nav_menu() args (it gets the divider group class) using the items the
 * menu call already loaded — no second menu query.
 *
 * @param array    $items Sorted menu items.
 * @param stdClass $args  wp_nav_menu args (object; shared with the walker filters).
 * @return array Unchanged items.
 */
function hk9_primary_menu_objects( array $items, $args ): array {
	if ( ! is_object( $args ) || 'primary' !== ( $args->theme_location ?? '' ) || 'mobile' === ( $args->hk9_flavour ?? 'desktop' ) ) {
		return $items;
	}
	if ( ! hk9_theme_option( 'header.divider_before_last' ) ) {
		return $items;
	}
	$top = array_values( array_filter( $items, static fn( $i ) => is_object( $i ) && 0 === (int) ( $i->menu_item_parent ?? 0 ) ) );
	if ( count( $top ) > 1 ) {
		$args->hk9_last_id = (int) end( $top )->ID;
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'hk9_primary_menu_objects', 10, 2 );

/**
 * Fallback for an unassigned primary menu: reference order, existing pages only.
 *
 * @param array $args wp_nav_menu args.
 */
function hk9_primary_menu_fallback( array $args ): void {
	$mobile     = ( $args['hk9_flavour'] ?? 'desktop' ) === 'mobile';
	$items      = [];
	$current    = get_queried_object_id();
	$reference  = hk9_reference_nav();
	$divider_ok = (bool) hk9_theme_option( 'header.divider_before_last' );

	foreach ( $reference as $entry ) {
		$page = get_page_by_path( $entry['path'] );
		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
			continue;
		}
		$items[] = [
			'label'  => $entry['label'],
			'url'    => get_permalink( $page ),
			'active' => (int) $page->ID === (int) $current,
		];
	}

	if ( empty( $items ) ) {
		return;
	}

	$count = count( $items );
	echo '<ul class="' . esc_attr( $mobile ? 'hk9-header__mobile-list' : 'hk9-header__list' ) . '">';
	foreach ( $items as $i => $item ) {
		$li_classes = [ $mobile ? 'hk9-header__mobile-item' : 'hk9-header__item' ];
		if ( ! $mobile && $divider_ok && $i === $count - 1 && $count > 1 ) {
			$li_classes[] = 'hk9-header__item--divider';
		}
		$a_classes = [ $mobile ? 'hk9-header__mobile-link' : 'hk9-header__nav-link' ];
		if ( $item['active'] ) {
			$a_classes[] = 'is-active';
		}
		printf(
			'<li class="%s"><a class="%s" href="%s"%s>%s</a></li>',
			esc_attr( implode( ' ', $li_classes ) ),
			esc_attr( implode( ' ', $a_classes ) ),
			esc_url( $item['url'] ),
			$item['active'] ? ' aria-current="page"' : '',
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * <li> classes for theme menus.
 *
 * @param string[] $classes Classes.
 * @param WP_Post  $item    Menu item.
 * @param stdClass $args    Args.
 * @param int      $depth   Depth.
 * @return string[]
 */
function hk9_menu_item_classes( array $classes, $item, $args, $depth ): array {
	$location = $args->theme_location ?? '';

	if ( 'primary' === $location ) {
		$mobile  = ( $args->hk9_flavour ?? 'desktop' ) === 'mobile';
		$classes = [ $mobile ? 'hk9-header__mobile-item' : 'hk9-header__item' ];

		if ( ! $mobile && ! empty( $args->hk9_last_id ) && (int) $item->ID === (int) $args->hk9_last_id ) {
			$classes[] = 'hk9-header__item--divider';
		}
		return $classes;
	}

	if ( in_array( $location, [ 'footer_quick', 'footer_involved', 'legal' ], true ) ) {
		return [ 'hk9-footer__item' ];
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'hk9_menu_item_classes', 10, 4 );

/**
 * <a> attributes for theme menus (classes + active state).
 *
 * @param array    $atts  Attributes.
 * @param WP_Post  $item  Menu item.
 * @param stdClass $args  Args.
 * @return array
 */
function hk9_menu_link_attributes( array $atts, $item, $args ): array {
	$location = $args->theme_location ?? '';
	$active   = ! empty( $item->current ) || ! empty( $item->current_item_ancestor ) || ! empty( $item->current_item_parent );

	if ( 'primary' === $location ) {
		$mobile        = ( $args->hk9_flavour ?? 'desktop' ) === 'mobile';
		$atts['class'] = $mobile ? 'hk9-header__mobile-link' : 'hk9-header__nav-link';
		if ( $active ) {
			$atts['class'] .= ' is-active';
		}
	} elseif ( in_array( $location, [ 'footer_quick', 'footer_involved', 'legal' ], true ) ) {
		$atts['class'] = 'hk9-footer__link';
	}

	// External targets always get a safe rel.
	if ( ! empty( $atts['target'] ) && '_blank' === $atts['target'] ) {
		$rel         = isset( $atts['rel'] ) ? explode( ' ', $atts['rel'] ) : [];
		$atts['rel'] = implode( ' ', array_unique( array_filter( array_merge( $rel, [ 'noopener', 'noreferrer' ] ) ) ) );
	}

	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'hk9_menu_link_attributes', 10, 3 );

/**
 * No `id="menu-item-N"` on theme menus (the primary menu is rendered twice).
 *
 * @param string   $id   Id.
 * @param WP_Post  $item Menu item.
 * @param stdClass $args Args.
 * @return string
 */
function hk9_menu_item_id( $id, $item, $args ): string {
	$location = $args->theme_location ?? '';
	return in_array( $location, [ 'primary', 'footer_quick', 'footer_involved', 'legal' ], true ) ? '' : (string) $id;
}
add_filter( 'nav_menu_item_id', 'hk9_menu_item_id', 10, 3 );

/**
 * Render a footer menu list (or the reference fallback links).
 *
 * @param string $location Menu location.
 * @param array  $fallback Fallback entries [ ['label','url','target'?] ].
 */
function hk9_footer_menu( string $location, array $fallback = [] ): void {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			[
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => 'hk9-footer__list',
				'menu_id'        => '',
				'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
				'depth'          => 1,
				'fallback_cb'    => false,
			]
		);
		return;
	}

	$items = [];
	foreach ( $fallback as $entry ) {
		if ( empty( $entry['url'] ) ) {
			continue;
		}
		$items[] = $entry;
	}
	if ( empty( $items ) ) {
		return;
	}

	echo '<ul class="hk9-footer__list">';
	foreach ( $items as $entry ) {
		$target = ! empty( $entry['target'] ) && '_blank' === $entry['target'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		printf( '<li class="hk9-footer__item"><a class="hk9-footer__link" href="%s"%s>%s</a></li>', esc_url( $entry['url'] ), $target, esc_html( $entry['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $target is static markup.
	}
	echo '</ul>';
}

/**
 * Reference footer fallbacks (existing pages only).
 *
 * @param string $column `quick` | `involved`.
 * @return array
 */
function hk9_footer_fallback_links( string $column ): array {
	$defs = 'quick' === $column
		? [
			[ 'label' => __( 'Our Mission', 'heartland-k9s' ), 'path' => 'about' ],
			[ 'label' => __( 'The Program', 'heartland-k9s' ), 'path' => 'program' ],
			[ 'label' => __( 'For Veterans', 'heartland-k9s' ), 'path' => 'veterans' ],
			[ 'label' => __( 'BarKode Program', 'heartland-k9s' ), 'path' => 'barkode' ],
			[ 'label' => __( 'Success Stories', 'heartland-k9s' ), 'path' => 'stories' ],
		]
		: [
			[ 'label' => __( 'Make a Donation', 'heartland-k9s' ), 'link' => 'links.donate' ],
			[ 'label' => __( 'Volunteer', 'heartland-k9s' ), 'link' => 'links.volunteer', 'path' => 'get-involved' ],
			[ 'label' => __( 'Campaigns & Events', 'heartland-k9s' ), 'link' => 'links.campaigns', 'path' => 'get-involved' ],
			[ 'label' => __( 'K9 Providers', 'heartland-k9s' ), 'link' => 'links.provider', 'path' => 'program' ],
		];

	$out = [];
	foreach ( $defs as $def ) {
		$url    = '';
		$target = '_self';

		if ( ! empty( $def['link'] ) ) {
			$link = hk9_theme_option( $def['link'] );
			if ( is_array( $link ) ) {
				$url    = hk9_theme_link_url( $link );
				$target = $link['target'] ?? '_self';
				// A default that points at a site path only counts when that page exists.
				if ( '' !== $url && empty( $link['post_id'] ) && str_starts_with( $url, home_url( '/' ) ) ) {
					$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
					$rel  = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
					$path = '' !== $rel ? trim( substr( $path, strlen( $rel ) ), '/' ) : $path;
					if ( '' === $path || ! get_page_by_path( $path ) ) {
						$url = '';
					}
				}
			}
		}

		if ( '' === $url && ! empty( $def['path'] ) ) {
			$page = get_page_by_path( $def['path'] );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				$url = get_permalink( $page );
			}
		}

		if ( '' !== $url ) {
			$out[] = [ 'label' => $def['label'], 'url' => $url, 'target' => $target ];
		}
	}

	return $out;
}
