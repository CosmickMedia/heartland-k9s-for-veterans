<?php
/**
 * SEO template tags: breadcrumbs.
 *
 * The trail comes from the plugin (`hk9_breadcrumb_trail()`, HK9\Core\Seo\Breadcrumbs —
 * the same data behind the BreadcrumbList structured data); without the plugin a
 * minimal trail (Home › page ancestors › page, Home › News › post) is built here.
 * Rendering: template-parts/breadcrumbs.php; styles: assets/src/scss/_pages-shared.scss.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hk9_breadcrumb_trail' ) ) {
	/**
	 * Plugin-less breadcrumb trail: [ {name, url}, … ] (first = Home, last = current).
	 * Empty on the front page and 404s.
	 *
	 * @return array<int, array{name:string,url:string}>
	 */
	function hk9_breadcrumb_trail(): array {
		if ( is_front_page() || is_404() ) {
			return [];
		}
		$trail = [
			[
				'name' => __( 'Home', 'heartland-k9s' ),
				'url'  => home_url( '/' ),
			],
		];
		$news_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
		$news    = $news_id > 0 ? [ 'name' => (string) get_the_title( $news_id ), 'url' => (string) get_permalink( $news_id ) ] : null;

		if ( is_search() ) {
			/* translators: %s: search query */
			$trail[] = [ 'name' => sprintf( __( 'Search results for “%s”', 'heartland-k9s' ), get_search_query() ), 'url' => '' ];
			return $trail;
		}
		if ( is_home() ) {
			$trail[] = $news ?? [ 'name' => (string) hk9_theme_option( 'blog.hero_title', __( 'News', 'heartland-k9s' ) ), 'url' => '' ];
			return $trail;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return $trail;
			}
			if ( 'page' === $post->post_type ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
					$trail[] = [ 'name' => (string) get_the_title( $ancestor ), 'url' => (string) get_permalink( $ancestor ) ];
				}
			} elseif ( 'post' === $post->post_type && $news ) {
				$trail[] = $news;
			} else {
				$listing = [
					'hk9_story'    => [ 'links.stories', '/stories/' ],
					'hk9_team'     => [ 'links.teams', '/hk9-current-teams-in-training/' ],
					'hk9_campaign' => [ 'links.campaigns', '/campaigns/' ],
					'hk9_event'    => [ 'links.events', '/events/' ],
					'hk9_barkode'  => [ 'links.barkode', '/barkode/' ],
				];
				if ( isset( $listing[ $post->post_type ] ) ) {
					[ $setting, $fallback ] = $listing[ $post->post_type ];
					$link = hk9_theme_option( $setting );
					$page = null;
					if ( is_array( $link ) && (int) ( $link['post_id'] ?? 0 ) > 0 ) {
						$page = get_post( (int) $link['post_id'] );
					}
					if ( ! $page instanceof WP_Post ) {
						$path = is_array( $link ) && '' !== trim( (string) ( $link['url'] ?? '' ) ) ? (string) $link['url'] : $fallback;
						$page = str_starts_with( $path, '/' ) ? get_page_by_path( trim( $path, '/' ) ) : null;
					}
					if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
						$trail[] = [ 'name' => (string) get_the_title( $page ), 'url' => (string) get_permalink( $page ) ];
					}
				}
			}
			$trail[] = [ 'name' => (string) get_the_title( $post ), 'url' => (string) get_permalink( $post ) ];
			return $trail;
		}
		if ( is_archive() ) {
			if ( $news && ! is_post_type_archive() ) {
				$trail[] = $news;
			}
			$trail[] = [ 'name' => wp_strip_all_tags( (string) get_the_archive_title() ), 'url' => '' ];
		}
		return $trail;
	}
}

/**
 * Whether breadcrumbs are enabled (Settings → SEO → "Show breadcrumbs").
 *
 * @return bool
 */
function hk9_breadcrumbs_enabled(): bool {
	$enabled = hk9_theme_option( 'seo.breadcrumbs', true );
	/**
	 * Filter whether the visible breadcrumbs render.
	 *
	 * @param bool $enabled Setting value.
	 */
	return (bool) apply_filters( 'hk9/theme/breadcrumbs', null === $enabled ? true : (bool) $enabled );
}

/**
 * Breadcrumbs markup for the current view ('' when disabled, on the front page,
 * on 404s or when the trail has a single item).
 *
 * @param array $args { class: string (extra classes) }.
 * @return string
 */
function hk9_breadcrumbs( array $args = [] ): string {
	if ( ! hk9_breadcrumbs_enabled() ) {
		return '';
	}
	$trail = hk9_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return '';
	}
	ob_start();
	get_template_part(
		'template-parts/breadcrumbs',
		null,
		[
			'trail' => $trail,
			'class' => (string) ( $args['class'] ?? '' ),
		]
	);
	return (string) ob_get_clean();
}

/**
 * Print the breadcrumbs.
 *
 * @param array $args See hk9_breadcrumbs().
 */
function hk9_the_breadcrumbs( array $args = [] ): void {
	echo hk9_breadcrumbs( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
}
