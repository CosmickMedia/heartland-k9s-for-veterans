<?php
/**
 * Blog + WordPress-state helpers: archive context (eyebrow/title/description/image),
 * listing renderer, post cards/meta/term chips, related + adjacent posts, search
 * highlighting, helpful links (404/search), comment list callback + comment form
 * styling, password form, initials avatars (no Gravatar requests), and the
 * comment-reply script.
 *
 * Templates: home.php, index.php, archive.php, search.php, single.php, 404.php,
 * attachment.php, comments.php, searchform.php and template-parts/blog/*.php.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blog setting shortcut (`blog.<key>` via hk9_theme_option()).
 *
 * @param string $key     Key inside the `blog` group.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function hk9_blog_option( string $key, $default = null ) {
	return hk9_theme_option( 'blog.' . $key, $default );
}

/**
 * Listing layout: `list` | `grid`.
 *
 * @return string
 */
function hk9_blog_layout(): string {
	return 'grid' === hk9_blog_option( 'layout' ) ? 'grid' : 'list';
}

/**
 * Permalink of the posts page (or the home URL when posts show on the front page).
 *
 * @return string
 */
function hk9_blog_url(): string {
	$page_for_posts = (int) get_option( 'page_for_posts' );
	if ( 'page' === get_option( 'show_on_front' ) && $page_for_posts > 0 ) {
		$url = get_permalink( $page_for_posts );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}

/**
 * Drop core's "Category:" / "Tag:" prefixes from get_the_archive_title(); the
 * archive type is shown as a separate eyebrow by hk9_archive_context().
 */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

/**
 * Eyebrow + title + description + hero image for the current listing (home,
 * archives, search, 404).
 *
 * @return array{eyebrow:string,title:string,title_html:string,text:string,image:int,type:string}
 */
function hk9_archive_context(): array {
	$context = [
		'eyebrow'    => '',
		'title'      => '',
		'title_html' => '',
		'text'       => '',
		'image'      => 0,
		'type'       => 'index',
	];

	if ( is_404() ) {
		$context['type']    = '404';
		$context['eyebrow'] = __( 'Error 404', 'heartland-k9s' );
		$context['title']   = __( 'Page not found', 'heartland-k9s' );
		$context['text']    = __( 'We could not find the page you were looking for. It may have moved, or the link may be out of date.', 'heartland-k9s' );
	} elseif ( is_search() ) {
		$query              = get_search_query( false );
		$context['type']    = 'search';
		$context['eyebrow'] = __( 'Search', 'heartland-k9s' );
		if ( '' === trim( $query ) ) {
			$context['title'] = __( 'Search', 'heartland-k9s' );
			$context['text']  = __( 'Enter a word or phrase to search the site.', 'heartland-k9s' );
		} else {
			/* translators: %s: search query */
			$context['title']      = sprintf( __( 'Results for “%s”', 'heartland-k9s' ), $query );
			/* translators: %s: search query (highlighted) */
			$context['title_html'] = sprintf( esc_html__( 'Results for “%s”', 'heartland-k9s' ), '<mark class="hk9-mark">' . esc_html( $query ) . '</mark>' );

			$found = (int) $GLOBALS['wp_query']->found_posts;
			if ( $found > 0 ) {
				/* translators: %s: number of results */
				$context['text'] = sprintf( _n( '%s result', '%s results', $found, 'heartland-k9s' ), number_format_i18n( $found ) );
			} else {
				$context['text'] = __( 'Nothing matched your search.', 'heartland-k9s' );
			}
		}
	} elseif ( is_home() ) {
		$context['type'] = 'home';
		$page_id         = (int) get_option( 'page_for_posts' );
		$title           = trim( (string) hk9_blog_option( 'hero_title' ) );
		if ( '' === $title && $page_id > 0 ) {
			$title = get_the_title( $page_id );
		}
		$context['title'] = '' !== $title ? $title : __( 'News', 'heartland-k9s' );

		$text = trim( (string) hk9_blog_option( 'hero_text' ) );
		if ( '' === $text && $page_id > 0 && has_excerpt( $page_id ) ) {
			$text = wp_strip_all_tags( get_the_excerpt( $page_id ) );
		}
		$context['text'] = $text;

		$image = (int) hk9_blog_option( 'hero_image', 0 );
		if ( $image <= 0 && $page_id > 0 && has_post_thumbnail( $page_id ) ) {
			$image = (int) get_post_thumbnail_id( $page_id );
		}
		$context['image'] = $image;
	} elseif ( is_category() ) {
		$context['type']    = 'category';
		$context['eyebrow'] = __( 'Category', 'heartland-k9s' );
		$context['title']   = single_term_title( '', false );
		$context['text']    = get_the_archive_description();
	} elseif ( is_tag() ) {
		$context['type']    = 'tag';
		$context['eyebrow'] = __( 'Tag', 'heartland-k9s' );
		$context['title']   = single_term_title( '', false );
		$context['text']    = get_the_archive_description();
	} elseif ( is_author() ) {
		$context['type']    = 'author';
		$context['eyebrow'] = __( 'Author', 'heartland-k9s' );
		$context['title']   = get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) );
		$context['text']    = get_the_archive_description();
	} elseif ( is_date() ) {
		// Build the title from the query vars (never from the first post's own date).
		$year  = max( 1, (int) get_query_var( 'year' ) );
		$month = min( 12, max( 1, (int) get_query_var( 'monthnum' ) ) );
		$day   = min( 31, max( 1, (int) get_query_var( 'day' ) ) );
		$stamp = (int) mktime( 0, 0, 0, $month, $day, $year );

		$context['type'] = 'date';
		if ( is_day() ) {
			$context['eyebrow'] = __( 'Daily archive', 'heartland-k9s' );
			$context['title']   = wp_date( _x( 'F j, Y', 'daily archives date format', 'heartland-k9s' ), $stamp );
		} elseif ( is_month() ) {
			$context['eyebrow'] = __( 'Monthly archive', 'heartland-k9s' );
			$context['title']   = wp_date( _x( 'F Y', 'monthly archives date format', 'heartland-k9s' ), $stamp );
		} else {
			$context['eyebrow'] = __( 'Yearly archive', 'heartland-k9s' );
			$context['title']   = wp_date( _x( 'Y', 'yearly archives date format', 'heartland-k9s' ), $stamp );
		}
		$context['title'] = (string) $context['title'];
	} elseif ( is_tax() ) {
		$term               = get_queried_object();
		$taxonomy           = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
		$context['type']    = 'tax';
		$context['eyebrow'] = $taxonomy ? (string) $taxonomy->labels->singular_name : __( 'Archive', 'heartland-k9s' );
		$context['title']   = single_term_title( '', false );
		$context['text']    = get_the_archive_description();
	} elseif ( is_post_type_archive() ) {
		$context['type']    = 'post_type';
		$context['eyebrow'] = __( 'Archive', 'heartland-k9s' );
		$context['title']   = post_type_archive_title( '', false );
		$context['text']    = get_the_archive_description();
	} elseif ( is_archive() ) {
		$context['type']    = 'archive';
		$context['eyebrow'] = __( 'Archive', 'heartland-k9s' );
		$context['title']   = wp_strip_all_tags( get_the_archive_title() );
		$context['text']    = get_the_archive_description();
	} else {
		$context['title'] = get_bloginfo( 'name' );
	}

	if ( '' === $context['title'] ) {
		$context['title'] = get_bloginfo( 'name' );
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 && '404' !== $context['type'] ) {
		/* translators: %s: page number */
		$page_label         = sprintf( __( 'Page %s', 'heartland-k9s' ), number_format_i18n( $paged ) );
		$context['eyebrow'] = '' === $context['eyebrow'] ? $page_label : $context['eyebrow'] . ' · ' . $page_label;
	}

	/**
	 * Filter the archive hero context.
	 *
	 * @param array $context Context.
	 */
	return apply_filters( 'hk9/theme/archive_context', $context );
}

/**
 * Empty-state copy for the current listing.
 *
 * @return array{title:string,text:string}
 */
function hk9_blog_empty_copy(): array {
	if ( is_search() ) {
		return [
			'title' => __( 'No results found', 'heartland-k9s' ),
			'text'  => __( 'Check the spelling, try a more general word, or browse the pages below.', 'heartland-k9s' ),
		];
	}
	if ( is_category() ) {
		return [
			'title' => __( 'No posts yet in this category', 'heartland-k9s' ),
			'text'  => __( 'Nothing has been published here yet. Check back soon or browse all of our news.', 'heartland-k9s' ),
		];
	}
	if ( is_tag() ) {
		return [
			'title' => __( 'No posts yet with this tag', 'heartland-k9s' ),
			'text'  => __( 'Nothing has been tagged here yet. Check back soon or browse all of our news.', 'heartland-k9s' ),
		];
	}
	if ( is_author() ) {
		return [
			'title' => __( 'No posts yet from this author', 'heartland-k9s' ),
			'text'  => __( 'This author has not published anything yet. Browse all of our news instead.', 'heartland-k9s' ),
		];
	}
	if ( is_date() ) {
		return [
			'title' => __( 'No posts from this period', 'heartland-k9s' ),
			'text'  => __( 'Nothing was published in this period. Browse all of our news instead.', 'heartland-k9s' ),
		];
	}
	return [
		'title' => __( 'No news yet', 'heartland-k9s' ),
		'text'  => __( 'Check back soon — new updates are on the way.', 'heartland-k9s' ),
	];
}

/**
 * Helpful destinations for the 404 page and empty search results
 * (Home, About, Program, Contact, Donate, News) — only links that resolve.
 *
 * @return array<int, array{label:string,url:string,attrs:string}>
 */
function hk9_blog_helpful_links(): array {
	$links = [
		[ 'label' => __( 'Home', 'heartland-k9s' ), 'url' => home_url( '/' ), 'attrs' => '' ],
	];

	$about = get_page_by_path( 'about' );
	if ( $about instanceof WP_Post && 'publish' === $about->post_status ) {
		$links[] = [ 'label' => __( 'About', 'heartland-k9s' ), 'url' => (string) get_permalink( $about ), 'attrs' => '' ];
	}

	foreach (
		[
			'links.provider' => __( 'Program', 'heartland-k9s' ),
			'links.contact'  => __( 'Contact', 'heartland-k9s' ),
			'links.donate'   => __( 'Donate', 'heartland-k9s' ),
		] as $setting => $label
	) {
		$link = hk9_theme_option( $setting );
		if ( is_array( $link ) && hk9_link_is_set( $link ) ) {
			$links[] = [ 'label' => $label, 'url' => hk9_theme_link_url( $link ), 'attrs' => hk9_theme_link_attrs( $link ) ];
		}
	}

	if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_for_posts' ) > 0 ) {
		$links[] = [ 'label' => __( 'News', 'heartland-k9s' ), 'url' => hk9_blog_url(), 'attrs' => '' ];
	}

	/**
	 * Filter the helpful links shown on the 404 page and empty search results.
	 *
	 * @param array $links Links.
	 */
	return apply_filters( 'hk9/theme/helpful_links', $links );
}

/**
 * Render the listing (cards + pagination) or the empty panel for the main query.
 *
 * @param array $args Optional: `layout` (list|grid), `highlight` (search query).
 */
function hk9_blog_listing( array $args = [] ): void {
	$layout    = $args['layout'] ?? hk9_blog_layout();
	$highlight = (string) ( $args['highlight'] ?? '' );

	echo '<div class="hk9-overlap hk9-blog__overlap">';

	if ( have_posts() ) {
		printf( '<div class="hk9-blog__inner%s">', 'grid' === $layout ? ' hk9-blog__inner--grid' : '' );
		printf( '<div class="hk9-blog__list%s">', 'grid' === $layout ? ' hk9-blog__list--grid' : '' );
		while ( have_posts() ) {
			the_post();
			hk9_blog_post_card( get_post(), [ 'layout' => $layout, 'highlight' => $highlight ] );
		}
		echo '</div>';
		get_template_part( 'template-parts/blog/pagination' );
		echo '</div>';
	} else {
		get_template_part( 'template-parts/blog/empty', null, [ 'search' => false, 'links' => is_search() ] );
	}

	echo '</div>';
}

/**
 * Post card (template-parts/blog/post-card.php).
 *
 * @param WP_Post|int|null $post Post.
 * @param array            $args `layout` (list|grid|compact), `highlight` (search query), `heading` (h2|h3).
 */
function hk9_blog_post_card( $post = null, array $args = [] ): void {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	get_template_part( 'template-parts/blog/post-card', null, array_merge( [ 'post' => $post ], $args ) );
}

/**
 * Meta row (template-parts/blog/post-meta.php).
 *
 * @param WP_Post|int|null $post Post.
 * @param array            $args `light` (bool), `comments` (bool), `class` (string).
 */
function hk9_blog_post_meta( $post = null, array $args = [] ): void {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	get_template_part( 'template-parts/blog/post-meta', null, array_merge( [ 'post' => $post ], $args ) );
}

/**
 * Term chips markup (`<ul class="hk9-blog__terms">`), or '' when there are none.
 *
 * @param WP_Post|int|null $post     Post.
 * @param string           $taxonomy `category` | `post_tag`.
 * @param array            $args     `class` (extra classes), `label` (aria-label), `limit` (int).
 * @return string
 */
function hk9_blog_post_terms( $post, string $taxonomy = 'category', array $args = [] ): string {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$terms = get_the_terms( $post, $taxonomy );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}

	if ( 'category' === $taxonomy && count( $terms ) > 1 ) {
		// Hide "Uncategorized" when a real category is also set.
		$default_cat = (int) get_option( 'default_category' );
		$filtered    = array_filter( $terms, static fn( WP_Term $t ) => $t->term_id !== $default_cat );
		if ( ! empty( $filtered ) ) {
			$terms = $filtered;
		}
	}

	$limit = (int) ( $args['limit'] ?? 0 );
	if ( $limit > 0 ) {
		$terms = array_slice( array_values( $terms ), 0, $limit );
	}

	$classes = trim( 'hk9-blog__terms ' . (string) ( $args['class'] ?? '' ) );
	$label   = (string) ( $args['label'] ?? ( 'post_tag' === $taxonomy ? __( 'Tags', 'heartland-k9s' ) : __( 'Categories', 'heartland-k9s' ) ) );

	$html = '<ul class="' . esc_attr( $classes ) . '" aria-label="' . esc_attr( $label ) . '">';
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a></li>';
	}
	return $html . '</ul>';
}

/**
 * Related posts: same categories, newest first, excluding the post; falls back
 * to the latest posts when the categories yield nothing.
 *
 * @param WP_Post $post  Post.
 * @param int     $count Number of posts.
 * @return WP_Post[]
 */
function hk9_blog_related_posts( WP_Post $post, int $count = 3 ): array {
	$count = max( 1, min( 12, $count ) );
	$cats  = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
	$found = [];

	if ( ! empty( $cats ) ) {
		$found = get_posts(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $count,
				'post__not_in'        => [ $post->ID ],
				'category__in'        => array_map( 'intval', $cats ),
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);
	}

	if ( empty( $found ) ) {
		$found = get_posts(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $count,
				'post__not_in'        => [ $post->ID ],
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);
	}

	/**
	 * Filter the related posts for a post.
	 *
	 * @param WP_Post[] $found Posts.
	 * @param WP_Post   $post  Current post.
	 * @param int       $count Requested count.
	 */
	return (array) apply_filters( 'hk9/theme/related_posts', $found, $post, $count );
}

/**
 * Wrap the search terms in <mark> inside an already-escaped, tag-free string.
 *
 * @param string $escaped Escaped text (esc_html output).
 * @param string $query   Search query (raw; defaults to the current one).
 * @return string
 */
function hk9_search_highlight( string $escaped, string $query = '' ): string {
	if ( '' === $query ) {
		$query = get_search_query( false );
	}
	$terms = preg_split( '/\s+/u', trim( $query ), -1, PREG_SPLIT_NO_EMPTY );
	if ( empty( $terms ) ) {
		return $escaped;
	}
	// Quotes/punctuation around a word are search syntax, not part of the match.
	$terms = array_map( static fn( string $t ): string => trim( $t, "\"'“”‘’.,;:!?()[]{}" ), $terms );
	$terms = array_filter( array_unique( $terms ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );
	if ( empty( $terms ) ) {
		return $escaped;
	}
	usort( $terms, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );
	$pattern = '/(' . implode( '|', array_map( static fn( string $t ): string => preg_quote( esc_html( $t ), '/' ), $terms ) ) . ')/iu';
	$result  = preg_replace( $pattern, '<mark class="hk9-mark">$1</mark>', $escaped );

	return is_string( $result ) ? $result : $escaped;
}

/**
 * Initials for a display name (max two letters, uppercased).
 *
 * @param string $name Name.
 * @return string
 */
function hk9_blog_initials( string $name ): string {
	$words = preg_split( '/[\s\-_.]+/u', trim( wp_strip_all_tags( $name ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( empty( $words ) ) {
		return '?';
	}
	$initials = mb_strtoupper( mb_substr( $words[0], 0, 1 ) );
	if ( count( $words ) > 1 ) {
		$initials .= mb_strtoupper( mb_substr( $words[ count( $words ) - 1 ], 0, 1 ) );
	}
	return $initials;
}

/**
 * Local initials avatar (no Gravatar / third-party request).
 *
 * @param string $name  Display name.
 * @param int    $size  Pixel size.
 * @param string $class Extra class.
 * @return string
 */
function hk9_blog_avatar( string $name, int $size = 48, string $class = '' ): string {
	$html = sprintf(
		'<span class="hk9-avatar %s" style="--hk9-avatar-size:%dpx" aria-hidden="true">%s</span>',
		esc_attr( trim( $class ) ),
		max( 24, $size ),
		esc_html( hk9_blog_initials( $name ) )
	);

	/**
	 * Filter the avatar markup (swap in get_avatar() to use Gravatar, for instance).
	 *
	 * @param string $html Markup.
	 * @param string $name Display name.
	 * @param int    $size Size.
	 */
	return (string) apply_filters( 'hk9/theme/avatar', $html, $name, $size );
}

/**
 * Paginated post navigation (<!--nextpage-->), styled like the list pagination.
 */
function hk9_blog_post_pages(): void {
	wp_link_pages(
		[
			'before'           => '<nav class="hk9-post-pages" aria-label="' . esc_attr__( 'Article pages', 'heartland-k9s' ) . '"><span class="hk9-post-pages__label">' . esc_html__( 'Pages:', 'heartland-k9s' ) . '</span><span class="hk9-post-pages__links">',
			'after'            => '</span></nav>',
			'next_or_number'   => 'number',
			'separator'        => '',
			'link_before'      => '<span class="screen-reader-text">' . esc_html__( 'Page', 'heartland-k9s' ) . ' </span>',
			'aria_current'     => 'page',
		]
	);
}

/**
 * Excerpt text for a card: hand-written excerpt → trimmed content; protected posts
 * get a neutral note instead of core's "There is no excerpt…" sentence.
 *
 * @param WP_Post $post Post.
 * @return string Plain text.
 */
function hk9_blog_card_excerpt( WP_Post $post ): string {
	if ( post_password_required( $post ) ) {
		return __( 'This article is password protected. Enter the password on the article page to read it.', 'heartland-k9s' );
	}
	return trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
}

/**
 * Comment list item (wp_list_comments callback; the walker closes the <li>).
 *
 * @param WP_Comment $comment Comment.
 * @param array      $args    List args.
 * @param int        $depth   Depth.
 */
function hk9_comment_callback( WP_Comment $comment, array $args, int $depth ): void {
	$tag         = 'div' === ( $args['style'] ?? 'ol' ) ? 'div' : 'li';
	$is_pingback = in_array( $comment->comment_type, [ 'pingback', 'trackback' ], true );
	$author      = get_comment_author( $comment );
	$post        = get_post( $comment->comment_post_ID );
	$by_author   = $post instanceof WP_Post && (int) $comment->user_id > 0 && (int) $comment->user_id === (int) $post->post_author;
	?>
	<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag name. ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $is_pingback ? 'hk9-comment hk9-comment--ping' : 'hk9-comment', $comment ); ?>>
		<article id="div-comment-<?php comment_ID(); ?>" class="comment-body hk9-comment__body">
			<header class="comment-meta hk9-comment__meta">
				<?php if ( ! $is_pingback ) : ?>
					<?php echo hk9_blog_avatar( $author, 40, 'hk9-comment__avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
				<div class="hk9-comment__who">
					<span class="comment-author vcard">
						<?php if ( $is_pingback ) : ?>
							<span class="hk9-comment__type"><?php echo 'pingback' === $comment->comment_type ? esc_html__( 'Pingback:', 'heartland-k9s' ) : esc_html__( 'Trackback:', 'heartland-k9s' ); ?></span>
						<?php endif; ?>
						<b class="fn"><?php echo esc_html( $author ); ?></b>
						<?php if ( $by_author ) : ?>
							<span class="hk9-comment__badge"><?php esc_html_e( 'Post author', 'heartland-k9s' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="comment-metadata hk9-comment__when">
						<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
							<time datetime="<?php comment_time( 'c' ); ?>">
								<?php
								/* translators: 1: comment date, 2: comment time */
								echo esc_html( sprintf( __( '%1$s at %2$s', 'heartland-k9s' ), get_comment_date( '', $comment ), get_comment_time() ) );
								?>
							</time>
						</a>
					</span>
				</div>
			</header>

			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p class="comment-awaiting-moderation hk9-comment__moderation" role="status"><?php esc_html_e( 'Your comment is awaiting moderation.', 'heartland-k9s' ); ?></p>
			<?php endif; ?>

			<div class="comment-content hk9-comment__content">
				<?php comment_text( $comment ); ?>
			</div>

			<?php
			if ( ! $is_pingback ) {
				comment_reply_link(
					array_merge(
						$args,
						[
							'depth'     => $depth,
							'max_depth' => (int) ( $args['max_depth'] ?? get_option( 'thread_comments_depth', 5 ) ),
							'before'    => '<div class="reply hk9-comment__reply">',
							'after'     => '</div>',
						]
					),
					$comment
				);
			}
			?>
		</article>
	<?php
}

/**
 * Comment form: .hk9-form markup + copy.
 *
 * @param array $defaults comment_form() defaults.
 * @return array
 */
function hk9_comment_form_defaults( array $defaults ): array {
	$required = (bool) get_option( 'require_name_email' );
	$req_mark = ' <span class="hk9-form__required" aria-hidden="true">*</span>';

	$defaults['class_container']      = 'comment-respond hk9-comments__respond';
	$defaults['class_form']           = 'comment-form hk9-form hk9-form--comments';
	$defaults['class_submit']         = 'submit hk9-btn hk9-btn--navy hk9-btn--lg';
	$defaults['title_reply']          = __( 'Leave a comment', 'heartland-k9s' );
	/* translators: %s: comment author */
	$defaults['title_reply_to']       = __( 'Reply to %s', 'heartland-k9s' );
	$defaults['title_reply_before']   = '<h2 id="reply-title" class="comment-reply-title hk9-comments__reply-title">';
	$defaults['title_reply_after']    = '</h2>';
	$defaults['cancel_reply_before']  = ' <small class="hk9-comments__cancel">';
	$defaults['cancel_reply_after']   = '</small>';
	$defaults['cancel_reply_link']    = __( 'Cancel reply', 'heartland-k9s' );
	$defaults['label_submit']         = __( 'Post comment', 'heartland-k9s' );
	$defaults['submit_field']         = '<div class="form-submit hk9-form__actions">%1$s %2$s</div>';
	$defaults['submit_button']        = '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>';
	$defaults['format']               = 'html5';
	$defaults['comment_notes_before'] = '<p class="comment-notes hk9-form__help">' . esc_html__( 'Your email address will not be published.', 'heartland-k9s' ) . ( $required ? ' ' . esc_html__( 'Required fields are marked', 'heartland-k9s' ) . $req_mark : '' ) . '</p>';
	$defaults['comment_field']        = '<div class="comment-form-comment hk9-form__field hk9-form__field--textarea">'
		. '<label for="comment" class="hk9-form__label">' . esc_html__( 'Comment', 'heartland-k9s' ) . $req_mark . '</label>'
		. '<textarea id="comment" name="comment" class="hk9-form__input hk9-form__textarea" rows="6" maxlength="65525" required></textarea>'
		. '</div>';

	return $defaults;
}
add_filter( 'comment_form_defaults', 'hk9_comment_form_defaults' );

/**
 * Comment form fields (name/email row, website, cookie consent) in .hk9-form markup.
 *
 * @param array $fields Default fields.
 * @return array
 */
function hk9_comment_form_fields( array $fields ): array {
	$commenter = wp_get_current_commenter();
	$required  = (bool) get_option( 'require_name_email' );
	$req_mark  = ' <span class="hk9-form__required" aria-hidden="true">*</span>';

	$fields = [
		'author'  => '<div class="hk9-form__row"><div class="comment-form-author hk9-form__field hk9-form__field--text">'
			. '<label for="author" class="hk9-form__label">' . esc_html__( 'Name', 'heartland-k9s' ) . ( $required ? $req_mark : '' ) . '</label>'
			. '<input id="author" name="author" type="text" class="hk9-form__input" value="' . esc_attr( $commenter['comment_author'] ) . '" maxlength="245" autocomplete="name"' . ( $required ? ' required' : '' ) . '>'
			. '</div>',
		'email'   => '<div class="comment-form-email hk9-form__field hk9-form__field--email">'
			. '<label for="email" class="hk9-form__label">' . esc_html__( 'Email', 'heartland-k9s' ) . ( $required ? $req_mark : '' ) . '</label>'
			. '<input id="email" name="email" type="email" class="hk9-form__input" value="' . esc_attr( $commenter['comment_author_email'] ) . '" maxlength="100" autocomplete="email"' . ( $required ? ' required' : '' ) . '>'
			. '</div></div>',
		'url'     => '<div class="comment-form-url hk9-form__field hk9-form__field--text">'
			. '<label for="url" class="hk9-form__label">' . esc_html__( 'Website', 'heartland-k9s' ) . '</label>'
			. '<input id="url" name="url" type="url" class="hk9-form__input" value="' . esc_attr( $commenter['comment_author_url'] ) . '" maxlength="200" autocomplete="url">'
			. '</div>',
	];

	if ( get_option( 'show_comments_cookies_opt_in' ) ) {
		$consent           = empty( $commenter['comment_author_email'] ) ? '' : ' checked';
		$fields['cookies'] = '<div class="comment-form-cookies-consent hk9-form__check">'
			. '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . '>'
			. '<label for="wp-comment-cookies-consent">' . esc_html__( 'Save my name and email in this browser for the next time I comment.', 'heartland-k9s' ) . '</label>'
			. '</div>';
	}

	return $fields;
}
add_filter( 'comment_form_default_fields', 'hk9_comment_form_fields' );

/**
 * Password form for protected posts (template-parts/content/page-password.php).
 *
 * @param string       $output Core markup (unused).
 * @param WP_Post|null $post   Post.
 * @return string
 */
function hk9_password_form( string $output, $post = null ): string {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return $output;
	}
	ob_start();
	get_template_part( 'template-parts/content/page-password', null, [ 'post' => $post ] );
	$html = ob_get_clean();
	return is_string( $html ) && '' !== trim( $html ) ? $html : $output;
}
add_filter( 'the_password_form', 'hk9_password_form', 10, 2 );

/**
 * Comment-reply script for threaded comments (core, local file).
 */
function hk9_blog_enqueue(): void {
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'hk9_blog_enqueue' );

/**
 * Body classes for listings (layout modifier) and single posts.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function hk9_blog_body_classes( array $classes ): array {
	if ( is_home() || is_archive() || is_search() ) {
		$classes[] = 'hk9-blog';
		$classes[] = 'hk9-blog--' . hk9_blog_layout();
	}
	if ( is_singular( 'post' ) ) {
		$classes[] = 'hk9-single-post';
	}
	return $classes;
}
add_filter( 'body_class', 'hk9_blog_body_classes' );
