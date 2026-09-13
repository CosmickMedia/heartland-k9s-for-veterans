<?php
/**
 * JSON-LD structured data: one `@graph` per page.
 *
 * Full mode (no SEO plugin): Organization (NGO), WebSite (+SearchAction),
 * WebPage / CollectionPage / AboutPage / ContactPage, BreadcrumbList,
 * BlogPosting on posts, Event on event singles and in the events listing,
 * FAQPage for a rendered FAQ section, the team's Person ItemList,
 * DonateAction on the Donate page. Nothing on 404s, feeds, embeds, previews
 * or noindexed singular views (registry records, the per-post toggle, local
 * fixtures — Context::noindex()).
 *
 * Plugin-managed mode (Slim SEO, Yoast, …): the SEO plugin prints WebSite /
 * WebPage / Breadcrumb / Article / Organization; this class adds only what it
 * cannot know — the NGO details (nonprofit status, EIN, address, phone,
 * sameAs, DonateAction), Events, FAQPage, the team list. Every node carries
 * an `@id` built the way those plugins build theirs (`{home}#organization`,
 * `{url}#webpage`), so a consumer merging the JSON-LD by identifier sees one
 * enriched Organization rather than two. With Slim SEO the nodes are merged
 * directly into its graph through the `slim_seo_schema_graph` filter, which
 * yields a single `<script>` on the page; other plugins get a second script
 * whose nodes share the identifiers.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

use HK9\Core\Events\Dates;

defined( 'ABSPATH' ) || exit;

final class Schema {

	private const ENCODE = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

	/** @var bool Whether the nodes were merged into an SEO plugin's graph this request. */
	private static bool $merged = false;

	private static bool $printed = false;

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'print_full' ], 20 );
		add_filter( 'slim_seo_schema_graph', [ self::class, 'merge_slim_seo' ], 20 );
		add_action( 'wp_footer', [ self::class, 'print_extras' ], 99 );
	}

	/* ------------------------------------------------------------------ */
	/* Output                                                               */
	/* ------------------------------------------------------------------ */

	/** Full mode: the complete graph in the head. */
	public static function print_full(): void {
		if ( 'full' !== Detector::mode() || ! self::printable() ) {
			return;
		}
		self::output( self::graph() );
	}

	/**
	 * Plugin-managed mode with Slim SEO: merge our nodes into its graph
	 * (`{home}#organization`, `{url}#webpage` share identifiers).
	 *
	 * @param array $graph Slim SEO nodes.
	 * @return array
	 */
	public static function merge_slim_seo( $graph ): array {
		$graph = is_array( $graph ) ? array_values( $graph ) : [];
		if ( 'plugin' !== Detector::mode() || ! self::printable() ) {
			return $graph;
		}
		$extras = self::graph();
		if ( empty( $extras ) ) {
			return $graph;
		}
		$logo_id = home_url( '/' ) . '#logo';
		if ( self::has_node( $graph, $logo_id ) ) {
			$extras = array_values( array_filter( $extras, static fn( array $n ): bool => ( $n['@id'] ?? '' ) !== $logo_id ) );
		}
		self::$merged = true;
		return self::merge_into( $graph, $extras );
	}

	/**
	 * Plugin-managed mode with another SEO plugin (or Slim SEO with its schema
	 * feature switched off): a separate graph whose nodes share identifiers.
	 */
	public static function print_extras(): void {
		if ( 'plugin' !== Detector::mode() || self::$merged || ! self::printable() ) {
			return;
		}
		$graph = self::graph();
		foreach ( $graph as &$node ) {
			// The Donate page patch is a bare {@id, potentialAction} meant to merge into the SEO plugin's
			// WebPage node; printed on its own it must be a valid WebPage.
			if ( 'WebPage' === ( $node['@type'] ?? '' ) && ! isset( $node['url'] ) ) {
				$node['url']  = self::url();
				$node['name'] = Context::title();
			}
		}
		unset( $node );
		self::output( $graph );
	}

	private static function printable(): bool {
		if ( self::$printed || is_admin() || is_feed() || is_embed() || is_preview() || is_customize_preview() ) {
			return false;
		}
		if ( is_404() ) {
			return false;
		}
		if ( is_singular() && Context::noindex() ) {
			return false; // Registry records, per-post noindex and local fixtures get no structured data.
		}
		return Detector::prints_schema();
	}

	private static function output( array $graph ): void {
		$graph = array_values( array_filter( $graph, 'is_array' ) );
		if ( empty( $graph ) ) {
			return;
		}
		self::$printed = true;
		$json = wp_json_encode(
			[
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			],
			self::ENCODE
		);
		if ( ! is_string( $json ) ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" id="hk9-schema">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
	}

	/** JSON for a URL-less consumer (CLI/tests): the graph of the current view. */
	public static function json(): string {
		$json = wp_json_encode(
			[
				'@context' => 'https://schema.org',
				'@graph'   => array_values( self::graph() ),
			],
			self::ENCODE
		);
		return is_string( $json ) ? $json : '';
	}

	/* ------------------------------------------------------------------ */
	/* Graph                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Nodes for the current view. Full mode: everything; plugin mode: the
	 * site-specific extras only.
	 *
	 * @return array<int, array>
	 */
	public static function graph(): array {
		$mode  = Detector::mode();
		$full  = 'full' === $mode;
		$nodes = [];

		$org = self::organization( $full );
		if ( $org ) {
			$nodes[] = $org;
		}
		$logo = self::logo();
		if ( $logo ) {
			$nodes[] = $logo;
		}

		if ( $full ) {
			$nodes[] = self::website();
			$webpage = self::webpage();
			$crumbs  = self::breadcrumb_list();
			if ( $crumbs ) {
				$webpage['breadcrumb'] = [ '@id' => $crumbs['@id'] ];
			}
			$image = self::primary_image();
			if ( $image ) {
				$webpage['primaryImageOfPage'] = [ '@id' => $image['@id'] ];
				$webpage['image']              = [ '@id' => $image['@id'] ];
			}
			$donate = self::donate_action();
			if ( $donate ) {
				$webpage['potentialAction'] = $donate;
			}
			$nodes[] = $webpage;
			if ( $crumbs ) {
				$nodes[] = $crumbs;
			}
			if ( $image ) {
				$nodes[] = $image;
			}
			$article = self::article( $webpage['@id'], $image ? $image['@id'] : '' );
			if ( $article ) {
				$nodes[] = $article;
			}
		} else {
			$donate = self::donate_action();
			if ( $donate ) {
				$nodes[] = [
					'@type'           => 'WebPage',
					'@id'             => self::url() . '#webpage',
					'potentialAction' => $donate,
				];
			}
		}

		foreach ( self::events() as $event ) {
			$nodes[] = $event;
		}
		$faq = self::faq();
		if ( $faq ) {
			$nodes[] = $faq;
		}
		$people = self::people();
		if ( $people ) {
			$nodes[] = $people;
		}

		/**
		 * Filters the JSON-LD nodes for the current view.
		 *
		 * @param array  $nodes Nodes (each with @type/@id).
		 * @param string $mode  full | plugin.
		 */
		return array_values( array_filter( (array) apply_filters( 'hk9/seo/schema_graph', $nodes, $mode ), 'is_array' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Identifiers                                                          */
	/* ------------------------------------------------------------------ */

	public static function home(): string {
		return home_url( '/' );
	}

	public static function org_id(): string {
		return self::home() . '#organization';
	}

	public static function website_id(): string {
		return self::home() . '#website';
	}

	/** Canonical URL of the view (the search URL for search results, the request URL otherwise). */
	public static function url(): string {
		$url = Context::canonical();
		if ( '' === $url && is_search() ) {
			$url = add_query_arg( 's', get_search_query( false ), home_url( '/' ) );
		}
		if ( '' === $url ) {
			global $wp;
			$url = home_url( '/' . ltrim( $wp instanceof \WP ? (string) $wp->request : '', '/' ) );
		}
		return $url;
	}

	/* ------------------------------------------------------------------ */
	/* Organization                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * The NGO node. Full: everything; plugin mode: the same (name/url merge
	 * with the SEO plugin's node, the rest enriches it).
	 */
	public static function organization( bool $full = true ): array {
		$opt = static fn( string $key, mixed $default = '' ): mixed => function_exists( 'hk9_option' ) ? hk9_option( $key, $default ) : $default;
		$str = static fn( string $key ): string => Description::clean( (string) ( is_scalar( $opt( $key, '' ) ) ? $opt( $key, '' ) : '' ) );

		$name = (string) get_bloginfo( 'name' );
		$node = [
			'@type' => 'NGO',
			'@id'   => self::org_id(),
			'name'  => Description::clean( $name ),
			'url'   => self::home(),
		];
		$legal = $str( 'contact.legal_name' );
		if ( '' !== $legal && $legal !== $node['name'] ) {
			$node['legalName'] = $legal;
		}
		$alt = $str( 'seo.org_alternate_name' );
		if ( '' !== $alt && $alt !== $node['name'] ) {
			$node['alternateName'] = $alt;
		}
		$logo = self::logo();
		if ( $logo ) {
			$node['logo']  = [ '@id' => $logo['@id'] ];
			$node['image'] = [ '@id' => $logo['@id'] ];
		}
		$description = $str( 'seo.org_description' );
		if ( '' === $description ) {
			$description = $str( 'footer.description' );
		}
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		$email = $str( 'contact.email' );
		if ( '' !== $email && is_email( $email ) ) {
			$node['email'] = $email;
		}
		$phone = self::phone( $str( 'contact.phone_main' ) );
		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}
		$address = self::org_address();
		if ( $address ) {
			$node['address'] = $address;
		}
		$node['nonprofitStatus'] = 'https://schema.org/Nonprofit501c3';
		$ein                     = $str( 'contact.ein' );
		if ( '' !== $ein ) {
			$node['taxID'] = $ein;
		}
		$same_as = self::same_as();
		if ( $same_as ) {
			$node['sameAs'] = $same_as;
		}
		if ( '' !== $phone || '' !== $email ) {
			$contact = [
				'@type'       => 'ContactPoint',
				'contactType' => 'customer service',
			];
			if ( '' !== $phone ) {
				$contact['telephone'] = $phone;
			}
			if ( '' !== $email && is_email( $email ) ) {
				$contact['email'] = $email;
			}
			$contact['areaServed']        = 'US';
			$contact['availableLanguage'] = 'English';
			$node['contactPoint']         = [ $contact ];
		}
		$donate = self::donate_action( false );
		if ( $donate ) {
			$node['potentialAction'] = $donate;
		}
		/**
		 * Filters the Organization (NGO) node.
		 *
		 * @param array $node Node.
		 * @param bool  $full Full mode.
		 */
		return (array) apply_filters( 'hk9/seo/schema_organization', $node, $full );
	}

	/** Organization logo ImageObject (`{home}#logo`), null when no logo is configured. */
	public static function logo(): ?array {
		static $node = false;
		if ( false !== $node ) {
			return $node;
		}
		$id = function_exists( 'hk9_option' ) ? (int) hk9_option( 'seo.org_logo', 0 ) : 0;
		if ( $id <= 0 && function_exists( 'hk9_option' ) ) {
			$id = (int) hk9_option( 'branding.header_logo', 0 );
		}
		if ( $id <= 0 ) {
			$id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		$node = $id > 0 ? Image::object( $id, 'full', self::home() . '#logo' ) : null;
		return $node;
	}

	/** PostalAddress from Settings → Contact (null when no street/city). */
	public static function org_address(): ?array {
		if ( ! function_exists( 'hk9_option' ) ) {
			return null;
		}
		$get     = static fn( string $key ): string => Description::clean( (string) hk9_option( 'contact.' . $key, '' ) );
		$street  = trim( $get( 'address_line1' ) . ( '' !== $get( 'address_line2' ) ? ', ' . $get( 'address_line2' ) : '' ) );
		$city    = $get( 'city' );
		$region  = $get( 'state' );
		$zip     = $get( 'zip' );
		if ( '' === $street && '' === $city ) {
			return null;
		}
		$address = [ '@type' => 'PostalAddress' ];
		if ( '' !== $street ) {
			$address['streetAddress'] = $street;
		}
		if ( '' !== $city ) {
			$address['addressLocality'] = $city;
		}
		if ( '' !== $region ) {
			$address['addressRegion'] = $region;
		}
		if ( '' !== $zip ) {
			$address['postalCode'] = $zip;
		}
		$address['addressCountry'] = 'US';
		return $address;
	}

	/** Organization sameAs: social profiles + Candid + Settings → SEO extras (deduped). */
	public static function same_as(): array {
		if ( ! function_exists( 'hk9_option' ) ) {
			return [];
		}
		$urls = [];
		foreach ( [ 'facebook', 'instagram', 'youtube', 'linkedin', 'x', 'tiktok', 'candid_url' ] as $key ) {
			$urls[] = (string) hk9_option( 'contact.' . $key, '' );
		}
		$extra = hk9_option( 'seo.same_as', '' );
		foreach ( preg_split( '/[\r\n,]+/', is_scalar( $extra ) ? (string) $extra : '' ) ?: [] as $line ) {
			$urls[] = trim( $line );
		}
		$out = [];
		foreach ( $urls as $url ) {
			$url = esc_url_raw( trim( $url ), [ 'http', 'https' ] );
			if ( '' !== $url && ! in_array( $url, $out, true ) ) {
				$out[] = $url;
			}
		}
		/**
		 * Filters the Organization sameAs URLs.
		 *
		 * @param string[] $out URLs.
		 */
		return array_values( array_filter( (array) apply_filters( 'hk9/seo/same_as', $out ), 'is_string' ) );
	}

	/** E.164-ish phone for US numbers ("800-913-6189" → "+1-800-913-6189"); other formats as written. */
	public static function phone( string $phone ): string {
		$phone = trim( $phone );
		if ( '' === $phone ) {
			return '';
		}
		$digits = preg_replace( '/\D+/', '', $phone ) ?? '';
		if ( 10 === strlen( $digits ) ) {
			return sprintf( '+1-%s-%s-%s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6 ) );
		}
		if ( 11 === strlen( $digits ) && str_starts_with( $digits, '1' ) ) {
			return sprintf( '+1-%s-%s-%s', substr( $digits, 1, 3 ), substr( $digits, 4, 3 ), substr( $digits, 7 ) );
		}
		return $phone;
	}

	/**
	 * DonateAction targeting the external donation form (Settings → Destinations).
	 *
	 * @param bool $page_only Only when the Donate page is being viewed.
	 */
	public static function donate_action( bool $page_only = true ): ?array {
		if ( $page_only && 'donate' !== Context::template() ) {
			return null;
		}
		if ( ! function_exists( 'hk9_option' ) ) {
			return null;
		}
		$target = self::link_url( hk9_option( 'links.donate_external' ) );
		if ( '' === $target ) {
			$target = self::link_url( hk9_option( 'links.donate' ) );
		}
		if ( '' === $target ) {
			return null;
		}
		return [
			'@type'     => 'DonateAction',
			'name'      => __( 'Donate', 'heartland-k9s-core' ),
			'target'    => $target,
			'recipient' => [ '@id' => self::org_id() ],
		];
	}

	/** Absolute URL of a link value ('' when unset). */
	private static function link_url( mixed $link ): string {
		if ( ! is_array( $link ) ) {
			return '';
		}
		$post_id = (int) ( $link['post_id'] ?? 0 );
		if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
			return (string) get_permalink( $post_id );
		}
		$url = trim( (string) ( $link['url'] ?? '' ) );
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) ) {
			return home_url( $url );
		}
		return esc_url_raw( $url, [ 'http', 'https' ] );
	}

	/* ------------------------------------------------------------------ */
	/* WebSite / WebPage / Breadcrumbs                                      */
	/* ------------------------------------------------------------------ */

	public static function website(): array {
		$node = [
			'@type'      => 'WebSite',
			'@id'        => self::website_id(),
			'url'        => self::home(),
			'name'       => Description::clean( (string) get_bloginfo( 'name' ) ),
			'inLanguage' => Context::language(),
			'publisher'  => [ '@id' => self::org_id() ],
		];
		$tagline = Description::clean( (string) get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$node['description'] = $tagline;
		}
		$node['potentialAction'] = [
			'@type'       => 'SearchAction',
			'target'      => [
				'@type'       => 'EntryPoint',
				'urlTemplate' => self::home() . '?s={search_term_string}',
			],
			'query-input' => 'required name=search_term_string',
		];
		return $node;
	}

	/** WebPage subtype for the view. */
	public static function webpage_type(): string {
		$kind = Context::kind();
		if ( 'search' === $kind ) {
			return 'SearchResultsPage';
		}
		if ( 'home' === $kind || 'archive' === $kind ) {
			return 'CollectionPage';
		}
		$template = Context::template();
		return match ( $template ) {
			'about'   => 'AboutPage',
			'contact' => 'ContactPage',
			'stories', 'events', 'campaigns', 'partners', 'people', 'teams', 'gallery' => 'CollectionPage',
			default   => 'WebPage',
		};
	}

	public static function webpage(): array {
		$url  = self::url();
		$node = [
			'@type'      => self::webpage_type(),
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => Context::title(),
			'inLanguage' => Context::language(),
			'isPartOf'   => [ '@id' => self::website_id() ],
		];
		$description = Context::description();
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		$post = Context::post();
		if ( $post && ( is_singular() || is_front_page() ) ) {
			$node['datePublished'] = (string) get_the_date( 'c', $post );
			$node['dateModified']  = (string) get_the_modified_date( 'c', $post );
		}
		if ( is_front_page() ) {
			$node['about'] = [ '@id' => self::org_id() ];
		}
		return $node;
	}

	public static function breadcrumb_list(): ?array {
		$trail = Breadcrumbs::trail();
		if ( count( $trail ) < 2 ) {
			return null;
		}
		$items = [];
		$last  = count( $trail ) - 1;
		foreach ( array_values( $trail ) as $i => $item ) {
			$entry = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
			];
			if ( $i < $last && '' !== $item['url'] ) {
				$entry['item'] = $item['url'];
			}
			$items[] = $entry;
		}
		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => self::url() . '#breadcrumblist',
			'name'            => __( 'Breadcrumbs', 'heartland-k9s-core' ),
			'itemListElement' => $items,
		];
	}

	/** The view's primary image as an ImageObject (`{url}#primaryimage`). */
	public static function primary_image(): ?array {
		$image = Context::image();
		if ( ! $image ) {
			return null;
		}
		return Image::object( (int) $image['id'], Image::SIZE, self::url() . '#primaryimage' );
	}

	/* ------------------------------------------------------------------ */
	/* Article                                                              */
	/* ------------------------------------------------------------------ */

	public static function article( string $webpage_id, string $image_id ): ?array {
		$post = Context::post();
		if ( ! $post || ! is_singular( 'post' ) ) {
			return null;
		}
		$url  = self::url();
		$node = [
			'@type'            => 'BlogPosting',
			'@id'              => $url . '#article',
			'headline'         => Description::trim( Description::clean( (string) get_the_title( $post ) ), 110 ),
			'url'              => $url,
			'isPartOf'         => [ '@id' => $webpage_id ],
			'mainEntityOfPage' => [ '@id' => $webpage_id ],
			'datePublished'    => (string) get_the_date( 'c', $post ),
			'dateModified'     => (string) get_the_modified_date( 'c', $post ),
			'inLanguage'       => Context::language(),
			'publisher'        => [ '@id' => self::org_id() ],
		];
		$description = Context::description();
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		if ( '' !== $image_id ) {
			$node['image'] = [ '@id' => $image_id ];
		}
		$show_author = function_exists( 'hk9_option' ) ? (bool) hk9_option( 'blog.show_author', false ) : false;
		$author_name = Description::clean( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
		if ( $show_author && '' !== $author_name ) {
			$node['author'] = [
				'@type' => 'Person',
				'name'  => $author_name,
			];
		} else {
			$node['author'] = [ '@id' => self::org_id() ];
		}
		$sections = [];
		foreach ( (array) get_the_category( (int) $post->ID ) as $category ) {
			if ( $category instanceof \WP_Term ) {
				$sections[] = $category->name;
			}
		}
		if ( $sections ) {
			$node['articleSection'] = $sections;
		}
		$tags = wp_get_post_tags( (int) $post->ID, [ 'fields' => 'names' ] );
		if ( is_array( $tags ) && $tags ) {
			$node['keywords'] = implode( ', ', array_map( 'strval', $tags ) );
		}
		$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
		if ( $words > 0 ) {
			$node['wordCount'] = $words;
		}
		return $node;
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Event nodes: the single event being viewed, or the upcoming events shown
	 * on the events listing page.
	 *
	 * @return array<int, array>
	 */
	public static function events(): array {
		if ( ! class_exists( Dates::class ) ) {
			return [];
		}
		$post = Context::post();
		if ( $post && is_singular( 'hk9_event' ) ) {
			$node = self::event( $post );
			return $node ? [ $node ] : [];
		}
		if ( ! $post || 'events' !== Context::template() || ! function_exists( 'hk9_sections_layout' ) || ! function_exists( 'hk9_section' ) ) {
			return [];
		}
		$layout = hk9_sections_layout( (int) $post->ID, 'events' );
		if ( ! in_array( 'upcoming', $layout, true ) ) {
			return [];
		}
		$section = hk9_section( (int) $post->ID, 'upcoming' );
		$count   = max( 1, min( 50, (int) ( $section['count'] ?? 10 ) ) );
		$nodes   = [];
		foreach ( Dates::query( [ 'scope' => 'upcoming', 'count' => $count ] ) as $event ) {
			if ( $event instanceof \WP_Post ) {
				$node = self::event( $event );
				if ( $node ) {
					$nodes[] = $node;
				}
			}
		}
		return $nodes;
	}

	/** One Event node (null when the event has no start date). */
	public static function event( \WP_Post $post ): ?array {
		$data = Dates::get( $post );
		if ( ! $data['start'] instanceof \DateTimeImmutable ) {
			return null;
		}
		$tz       = $data['timezone'];
		$has_time = (bool) $data['has_time'];
		$url      = (string) get_permalink( $post );
		$node     = [
			'@type'     => 'Event',
			'@id'       => $url . '#event',
			'name'      => Description::clean( (string) get_the_title( $post ) ),
			'url'       => $url,
			'startDate' => $has_time ? $data['start']->format( 'c' ) : $data['start']->format( 'Y-m-d' ),
		];
		// endDate only when an end was entered (Dates::get() synthesises end-of-day otherwise).
		[ $end, $end_has_time ] = Dates::parse( get_post_meta( (int) $post->ID, 'hk9_end', true ), $tz );
		if ( $end instanceof \DateTimeImmutable ) {
			$node['endDate'] = ( $end_has_time && $has_time ) ? $end->format( 'c' ) : $end->format( 'Y-m-d' );
		}
		$node['eventStatus'] = match ( $data['status'] ) {
			'cancelled' => 'https://schema.org/EventCancelled',
			'postponed' => 'https://schema.org/EventPostponed',
			default     => 'https://schema.org/EventScheduled',
		};
		$node['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';

		$description = Description::for_post( $post, false );
		if ( '' !== $description ) {
			$node['description'] = Description::trim( Description::clean( $description ), 300 );
		}

		$meta      = static fn( string $key, mixed $default = '' ): mixed => function_exists( 'hk9_cpt_meta' ) ? hk9_cpt_meta( (int) $post->ID, $key, $default ) : get_post_meta( (int) $post->ID, 'hk9_' . $key, true );
		$venue     = Description::clean( (string) $meta( 'venue', '' ) );
		$address   = (string) $meta( 'address', '' );
		$location  = [ '@type' => 'Place' ];
		$location['name'] = '' !== $venue ? $venue : Description::clean( (string) get_bloginfo( 'name' ) );
		$postal = self::parse_address( $address );
		if ( ! $postal && '' === $venue ) {
			$postal = self::org_address();
		}
		if ( $postal ) {
			$location['address'] = $postal;
		} elseif ( '' !== $venue ) {
			$location['address'] = $venue; // Place.address accepts Text when no postal address is known.
		}
		$node['location'] = $location;

		$image_id = Image::id_for_post( $post );
		$image    = $image_id > 0 ? Image::data( $image_id ) : Image::default_image();
		if ( $image ) {
			$node['image'] = [ $image['url'] ];
		}

		$registration = $meta( 'registration', [] );
		$reg_url      = is_array( $registration ) ? self::link_url( $registration ) : '';
		if ( '' !== $reg_url && 'cancelled' !== $data['status'] ) {
			$node['offers'] = [
				'@type'        => 'Offer',
				'url'          => $reg_url,
				'availability' => 'https://schema.org/InStock',
			];
		}

		// The organizer is always the NGO: the "Organizer name" field is a contact person shown on the page, not a legal organizer.
		$node['organizer'] = [ '@id' => self::org_id() ];
		/**
		 * Filters an Event node.
		 *
		 * @param array    $node Node.
		 * @param \WP_Post $post Event.
		 */
		return (array) apply_filters( 'hk9/seo/schema_event', $node, $post );
	}

	/**
	 * PostalAddress from a free-text address ("12651 Gateway Dr\nNeosho, MO 64850").
	 * Null when the text is empty.
	 */
	public static function parse_address( string $text ): ?array {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', wp_strip_all_tags( $text ) ) ?: [] ), static fn( string $l ): bool => '' !== $l ) );
		if ( empty( $lines ) ) {
			return null;
		}
		$address = [ '@type' => 'PostalAddress' ];
		$last    = end( $lines );
		if ( preg_match( '/^(?<city>.+?),\s*(?<state>[A-Z]{2})(?:\s+(?<zip>\d{5}(?:-\d{4})?))?$/', (string) $last, $m ) ) {
			array_pop( $lines );
			$address['addressLocality'] = trim( $m['city'] );
			$address['addressRegion']   = $m['state'];
			if ( ! empty( $m['zip'] ) ) {
				$address['postalCode'] = $m['zip'];
			}
			if ( $lines ) {
				$address['streetAddress'] = implode( ', ', $lines );
			}
		} else {
			$address['streetAddress'] = implode( ', ', $lines );
		}
		$address['addressCountry'] = 'US';
		return $address;
	}

	/* ------------------------------------------------------------------ */
	/* FAQ                                                                  */
	/* ------------------------------------------------------------------ */

	/** FAQPage for the FAQ section(s) visible on the queried page. */
	public static function faq(): ?array {
		$post     = Context::post();
		$template = Context::template();
		if ( ! $post || '' === $template || ! function_exists( 'hk9_sections_layout' ) || ! function_exists( 'hk9_section' ) || ! class_exists( 'HK9\\Core\\Sections\\Registry' ) ) {
			return null;
		}
		$questions = [];
		foreach ( hk9_sections_layout( (int) $post->ID, $template ) as $id ) {
			$def = \HK9\Core\Sections\Registry::definition( $template, (string) $id );
			if ( ! $def || 'faq' !== $def->type ) {
				continue;
			}
			$data  = hk9_section( (int) $post->ID, (string) $id );
			$items = is_array( $data['items'] ?? null ) ? $data['items'] : [];
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$question = Description::clean( (string) ( $item['question'] ?? '' ) );
				$answer   = self::answer_html( (string) ( $item['answer'] ?? '' ) );
				if ( '' === $question || '' === trim( wp_strip_all_tags( $answer ) ) ) {
					continue;
				}
				$questions[] = [
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => $answer,
					],
				];
			}
		}
		if ( empty( $questions ) ) {
			return null;
		}
		return [
			'@type'      => 'FAQPage',
			'@id'        => self::url() . '#faq',
			'url'        => self::url(),
			'name'       => Context::title(),
			'inLanguage' => Context::language(),
			'mainEntity' => $questions,
		];
	}

	/** Answer HTML limited to the elements Google accepts in FAQ answers. */
	private static function answer_html( string $html ): string {
		$html = wpautop( $html );
		$html = wp_kses(
			$html,
			[
				'a'      => [ 'href' => [] ],
				'p'      => [],
				'br'     => [],
				'strong' => [],
				'b'      => [],
				'em'     => [],
				'i'      => [],
				'ul'     => [],
				'ol'     => [],
				'li'     => [],
				'h2'     => [],
				'h3'     => [],
				'h4'     => [],
			]
		);
		$html = preg_replace( '/\s+/u', ' ', $html ) ?? $html;
		return trim( $html );
	}

	/* ------------------------------------------------------------------ */
	/* People                                                               */
	/* ------------------------------------------------------------------ */

	/** ItemList of Person nodes for the Meet the Team page (public roles only). */
	public static function people(): ?array {
		$post = Context::post();
		if ( ! $post || 'people' !== Context::template() || ! function_exists( 'hk9_sections_layout' ) || ! function_exists( 'hk9_section' ) ) {
			return null;
		}
		if ( ! in_array( 'grid', hk9_sections_layout( (int) $post->ID, 'people' ), true ) ) {
			return null;
		}
		$data  = hk9_section( (int) $post->ID, 'grid' );
		$posts = [];
		if ( 'manual' === ( $data['mode'] ?? 'auto' ) ) {
			foreach ( is_array( $data['people'] ?? null ) ? $data['people'] : [] as $id ) {
				$person = get_post( (int) $id );
				if ( $person instanceof \WP_Post && 'hk9_person' === $person->post_type && 'publish' === $person->post_status ) {
					$posts[] = $person;
				}
			}
		}
		if ( empty( $posts ) ) {
			$posts = get_posts(
				[
					'post_type'              => 'hk9_person',
					'post_status'            => 'publish',
					'numberposts'            => 100,
					'orderby'                => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
					'update_post_term_cache' => false,
				]
			);
		}
		if ( empty( $posts ) ) {
			return null;
		}
		$url   = self::url();
		$items = [];
		foreach ( array_values( $posts ) as $i => $person ) {
			if ( ! $person instanceof \WP_Post ) {
				continue;
			}
			$node = [
				'@type'    => 'Person',
				'@id'      => $url . '#person-' . sanitize_title( $person->post_name ?: (string) $person->ID ),
				'name'     => Description::clean( (string) get_the_title( $person ) ),
				'memberOf' => [ '@id' => self::org_id() ],
			];
			$role = function_exists( 'hk9_cpt_meta' ) ? Description::clean( (string) hk9_cpt_meta( (int) $person->ID, 'role', '' ) ) : '';
			if ( '' !== $role ) {
				$node['jobTitle'] = $role;
			}
			if ( has_post_thumbnail( $person ) ) {
				$image = Image::data( (int) get_post_thumbnail_id( $person ), 'hk9-portrait' );
				if ( $image ) {
					$node['image'] = $image['url'];
				}
			}
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'item'     => $node,
			];
		}
		if ( empty( $items ) ) {
			return null;
		}
		return [
			'@type'           => 'ItemList',
			'@id'             => $url . '#team',
			'name'            => Context::title(),
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		];
	}

	/* ------------------------------------------------------------------ */
	/* Merge helpers                                                        */
	/* ------------------------------------------------------------------ */

	private static function has_node( array $graph, string $id ): bool {
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && ( $node['@id'] ?? '' ) === $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Merges our nodes into another graph: same `@id` → one node (ours enriches
	 * theirs), otherwise appended.
	 */
	public static function merge_into( array $graph, array $extras ): array {
		$index = [];
		foreach ( $graph as $i => $node ) {
			if ( is_array( $node ) && isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				$index[ $node['@id'] ] = $i;
			}
		}
		foreach ( $extras as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$id = isset( $node['@id'] ) && is_string( $node['@id'] ) ? $node['@id'] : '';
			if ( '' !== $id && isset( $index[ $id ] ) ) {
				$graph[ $index[ $id ] ] = self::merge_node( (array) $graph[ $index[ $id ] ], $node );
			} else {
				$graph[] = $node;
				if ( '' !== $id ) {
					$index[ $id ] = array_key_last( $graph );
				}
			}
		}
		return array_values( $graph );
	}

	/** Property-level merge: ours wins on scalars/@type, distinct objects become a list. */
	public static function merge_node( array $theirs, array $ours ): array {
		foreach ( $ours as $key => $value ) {
			if ( ! array_key_exists( $key, $theirs ) || $theirs[ $key ] === $value ) {
				$theirs[ $key ] = $value;
				continue;
			}
			if ( '@type' === $key || '@id' === $key ) {
				$theirs[ $key ] = $value; // NGO refines Organization.
				continue;
			}
			$mine   = $theirs[ $key ];
			$a_obj  = is_array( $mine ) && ! array_is_list( $mine );
			$b_obj  = is_array( $value ) && ! array_is_list( $value );
			if ( $a_obj && $b_obj && self::same_entity( $mine, $value ) ) {
				$theirs[ $key ] = self::merge_node( $mine, $value );
				continue;
			}
			if ( ! is_array( $mine ) && ! is_array( $value ) ) {
				$theirs[ $key ] = $value; // Scalar: ours is the site-specific value.
				continue;
			}
			$list = is_array( $mine ) && array_is_list( $mine ) ? $mine : [ $mine ];
			foreach ( ( is_array( $value ) && array_is_list( $value ) ) ? $value : [ $value ] as $item ) {
				if ( ! in_array( $item, $list, true ) ) {
					$list[] = $item;
				}
			}
			$theirs[ $key ] = $list;
		}
		return $theirs;
	}

	private static function same_entity( array $a, array $b ): bool {
		$a_id = $a['@id'] ?? null;
		$b_id = $b['@id'] ?? null;
		if ( null !== $a_id || null !== $b_id ) {
			return $a_id === $b_id;
		}
		return ( $a['@type'] ?? null ) === ( $b['@type'] ?? null );
	}
}
