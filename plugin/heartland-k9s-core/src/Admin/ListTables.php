<?php
/**
 * Admin list-table columns, sortables and filters for the Heartland post types.
 *
 * Reads CPT meta by the ARCHITECTURE §6 keys ('hk9_' + field key); never writes.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Admin;

use HK9\Core\Events\Dates;
use HK9\Core\PostTypes\Registrar;
use HK9\Core\Taxonomies\PartnerType;

defined( 'ABSPATH' ) || exit;

final class ListTables {

	/** Meta-backed select filters: type => [query arg => [meta key, options callback]]. */
	private const META_FILTERS = [
		'hk9_story'    => [ 'hk9_relationship' => 'hk9_relationship' ],
		'hk9_team'     => [ 'hk9_status' => 'hk9_status' ],
		'hk9_campaign' => [ 'hk9_status' => 'hk9_status' ],
		'hk9_event'    => [ 'hk9_status' => 'hk9_status' ],
		'hk9_barkode'  => [ 'hk9_program_type' => 'hk9_program_type' ],
	];

	public static function register(): void {
		foreach ( Registrar::TYPES as $type ) {
			add_filter( "manage_{$type}_posts_columns", [ self::class, 'columns' ] );
			add_action( "manage_{$type}_posts_custom_column", [ self::class, 'render_column' ], 10, 2 );
			add_filter( "manage_edit-{$type}_sortable_columns", [ self::class, 'sortable_columns' ] );
		}
		add_action( 'restrict_manage_posts', [ self::class, 'filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'apply_query' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Registrar::TYPES, true ) ) {
			return;
		}
		$css = HK9_CORE_DIR . 'assets/css/admin.css';
		wp_enqueue_style( 'hk9-admin', HK9_CORE_URL . 'assets/css/admin.css', [], (string) ( file_exists( $css ) ? filemtime( $css ) : HK9_CORE_VERSION ) );
	}

	/**
	 * Column definitions per type (inserted after the title column).
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function definitions(): array {
		return [
			'hk9_story'      => [
				'hk9_image'    => __( 'Image', 'heartland-k9s-core' ),
				'hk9_veteran'  => __( 'Veteran', 'heartland-k9s-core' ),
				'hk9_canine'   => __( 'Canine', 'heartland-k9s-core' ),
				'hk9_featured' => __( 'Featured', 'heartland-k9s-core' ),
				'hk9_order'    => __( 'Order', 'heartland-k9s-core' ),
			],
			'hk9_team'       => [
				'hk9_image'    => __( 'Image', 'heartland-k9s-core' ),
				'hk9_canine'   => __( 'Canine', 'heartland-k9s-core' ),
				'hk9_status'   => __( 'Status', 'heartland-k9s-core' ),
				'hk9_featured' => __( 'Featured', 'heartland-k9s-core' ),
				'hk9_order'    => __( 'Order', 'heartland-k9s-core' ),
			],
			'hk9_person'     => [
				'hk9_portrait' => __( 'Portrait', 'heartland-k9s-core' ),
				'hk9_role'     => __( 'Role', 'heartland-k9s-core' ),
				'hk9_order'    => __( 'Order', 'heartland-k9s-core' ),
			],
			'hk9_partner'    => [
				'hk9_logo'    => __( 'Logo', 'heartland-k9s-core' ),
				'hk9_type'    => __( 'Type', 'heartland-k9s-core' ),
				'hk9_website' => __( 'Website', 'heartland-k9s-core' ),
				'hk9_order'   => __( 'Order', 'heartland-k9s-core' ),
			],
			'hk9_campaign'   => [
				'hk9_image'    => __( 'Image', 'heartland-k9s-core' ),
				'hk9_status'   => __( 'Status', 'heartland-k9s-core' ),
				'hk9_order'    => __( 'Order', 'heartland-k9s-core' ),
				'hk9_sponsors' => __( 'Sponsors', 'heartland-k9s-core' ),
			],
			'hk9_event'      => [
				'hk9_start'  => __( 'Start', 'heartland-k9s-core' ),
				'hk9_end'    => __( 'End', 'heartland-k9s-core' ),
				'hk9_venue'  => __( 'Venue', 'heartland-k9s-core' ),
				'hk9_status' => __( 'Status', 'heartland-k9s-core' ),
				'hk9_scope'  => __( 'Upcoming / Past', 'heartland-k9s-core' ),
			],
			'hk9_barkode'    => [
				'hk9_photo'        => __( 'Photo', 'heartland-k9s-core' ),
				'hk9_registry_id'  => __( 'Registry ID', 'heartland-k9s-core' ),
				'hk9_program_type' => __( 'Program', 'heartland-k9s-core' ),
				'hk9_handler'      => __( 'Handler', 'heartland-k9s-core' ),
				'hk9_legacy_path'  => __( 'Legacy path', 'heartland-k9s-core' ),
			],
			'hk9_submission' => [],
		];
	}

	/**
	 * Human labels for select-type meta values.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function value_labels(): array {
		return [
			'hk9_status'       => [
				'in-training' => __( 'In training', 'heartland-k9s-core' ),
				'graduated'   => __( 'Graduated', 'heartland-k9s-core' ),
				'therapy'     => __( 'Therapy', 'heartland-k9s-core' ),
				'active'      => __( 'Active', 'heartland-k9s-core' ),
				'completed'   => __( 'Completed', 'heartland-k9s-core' ),
				'paused'      => __( 'Paused', 'heartland-k9s-core' ),
				'scheduled'   => __( 'Scheduled', 'heartland-k9s-core' ),
				'cancelled'   => __( 'Cancelled', 'heartland-k9s-core' ),
				'postponed'   => __( 'Postponed', 'heartland-k9s-core' ),
			],
			'hk9_program_type' => [
				'service'     => __( 'Service K9', 'heartland-k9s-core' ),
				'therapy'     => __( 'Therapy K9', 'heartland-k9s-core' ),
				'in-training' => __( 'In training', 'heartland-k9s-core' ),
			],
			'hk9_relationship' => [
				'service'     => __( 'Service K9', 'heartland-k9s-core' ),
				'therapy'     => __( 'Therapy K9', 'heartland-k9s-core' ),
				'in-training' => __( 'In training', 'heartland-k9s-core' ),
			],
		];
	}

	/** Filter options per type + meta key (subset of value_labels). */
	private static function filter_options( string $type, string $meta_key ): array {
		$labels = self::value_labels()[ $meta_key ] ?? [];
		$subset = match ( $type . ':' . $meta_key ) {
			'hk9_team:hk9_status'     => [ 'in-training', 'graduated', 'therapy' ],
			'hk9_campaign:hk9_status' => [ 'active', 'completed', 'paused' ],
			'hk9_event:hk9_status'    => [ 'scheduled', 'cancelled', 'postponed' ],
			default                   => array_keys( $labels ),
		};
		return array_intersect_key( $labels, array_flip( $subset ) );
	}

	/**
	 * Insert our columns after the title column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$screen = get_current_screen();
		$type   = $screen ? $screen->post_type : '';
		$ours   = self::definitions()[ $type ] ?? [];
		if ( ! $ours ) {
			return $columns;
		}
		$out = [];
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out += $ours;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['hk9_order'] = 'hk9_order';
		$columns['hk9_start'] = 'hk9_start';
		$columns['hk9_end']   = 'hk9_end';
		$columns['hk9_registry_id'] = 'hk9_registry_id';
		return $columns;
	}

	/**
	 * Render a cell.
	 */
	public static function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'hk9_image':
			case 'hk9_photo':
			case 'hk9_portrait':
				self::thumb( $post_id, false );
				break;
			case 'hk9_logo':
				self::thumb( $post_id, true );
				break;
			case 'hk9_veteran':
				self::text( get_post_meta( $post_id, 'hk9_veteran_name', true ) );
				break;
			case 'hk9_canine':
				self::text( get_post_meta( $post_id, 'hk9_canine_name', true ) );
				break;
			case 'hk9_role':
				self::text( get_post_meta( $post_id, 'hk9_role', true ) );
				break;
			case 'hk9_venue':
				self::text( get_post_meta( $post_id, 'hk9_venue', true ) );
				break;
			case 'hk9_registry_id':
				$value = get_post_meta( $post_id, 'hk9_registry_id', true );
				echo is_scalar( $value ) && '' !== (string) $value ? '<code>' . esc_html( (string) $value ) . '</code>' : '<span class="hk9-muted">—</span>';
				break;
			case 'hk9_legacy_path':
				$value = get_post_meta( $post_id, 'hk9_legacy_path', true );
				echo is_scalar( $value ) && '' !== (string) $value ? '<code>' . esc_html( (string) $value ) . '</code>' : '<span class="hk9-muted">—</span>';
				break;
			case 'hk9_featured':
				$on = filter_var( get_post_meta( $post_id, 'hk9_featured', true ), FILTER_VALIDATE_BOOLEAN );
				echo $on ? '<span class="hk9-yes" aria-hidden="true">★</span><span class="screen-reader-text">' . esc_html__( 'Featured', 'heartland-k9s-core' ) . '</span>' : '<span class="hk9-muted">—</span>';
				break;
			case 'hk9_handler':
				$handler = get_post_meta( $post_id, 'hk9_handler_name', true );
				$present = is_scalar( $handler ) && '' !== trim( (string) $handler );
				echo $present ? '<span class="hk9-pill hk9-pill--good">' . esc_html__( 'Yes', 'heartland-k9s-core' ) . '</span>' : '<span class="hk9-pill">' . esc_html__( 'No', 'heartland-k9s-core' ) . '</span>';
				break;
			case 'hk9_order':
				$post = get_post( $post_id );
				echo esc_html( (string) ( $post ? (int) $post->menu_order : 0 ) );
				break;
			case 'hk9_status':
			case 'hk9_program_type':
				$meta_key = 'hk9_status' === $column ? 'hk9_status' : 'hk9_program_type';
				$value    = get_post_meta( $post_id, $meta_key, true );
				$value    = is_scalar( $value ) ? (string) $value : '';
				$label    = self::value_labels()[ $meta_key ][ $value ] ?? $value;
				$tone     = match ( $value ) {
					'graduated', 'active', 'scheduled', 'service' => 'good',
					'paused', 'postponed', 'in-training' => 'warn',
					'cancelled' => 'bad',
					'therapy', 'completed' => 'info',
					default => '',
				};
				echo '' !== $value ? '<span class="hk9-pill' . ( $tone ? ' hk9-pill--' . esc_attr( $tone ) : '' ) . '">' . esc_html( $label ) . '</span>' : '<span class="hk9-muted">—</span>';
				break;
			case 'hk9_type':
				$terms = get_the_terms( $post_id, PartnerType::TAXONOMY );
				if ( is_array( $terms ) && $terms ) {
					$links = [];
					foreach ( $terms as $term ) {
						$links[] = '<a href="' . esc_url( add_query_arg( [ 'post_type' => 'hk9_partner', PartnerType::TAXONOMY => $term->slug ], admin_url( 'edit.php' ) ) ) . '">' . esc_html( $term->name ) . '</a>';
					}
					echo wp_kses( implode( ', ', $links ), [ 'a' => [ 'href' => [] ] ] );
				} else {
					echo '<span class="hk9-muted">—</span>';
				}
				break;
			case 'hk9_website':
				$link = get_post_meta( $post_id, 'hk9_website', true );
				$url  = is_array( $link ) && function_exists( 'hk9_link_url' ) ? hk9_link_url( $link ) : ( is_string( $link ) ? $link : '' );
				if ( '' !== $url ) {
					$host = wp_parse_url( $url, PHP_URL_HOST );
					echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( is_string( $host ) && '' !== $host ? $host : $url ) . '</a>';
				} else {
					echo '<span class="hk9-muted">—</span>';
				}
				break;
			case 'hk9_sponsors':
				$ids = get_post_meta( $post_id, 'hk9_sponsors', true );
				echo esc_html( (string) ( is_array( $ids ) ? count( array_filter( $ids ) ) : 0 ) );
				break;
			case 'hk9_start':
			case 'hk9_end':
				$data = class_exists( Dates::class ) ? Dates::get( $post_id ) : null;
				$key  = 'hk9_start' === $column ? 'start' : 'end';
				if ( $data && $data[ $key ] instanceof \DateTimeImmutable ) {
					$format = $data['all_day'] ? get_option( 'date_format', 'M j, Y' ) : get_option( 'date_format', 'M j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' );
					echo esc_html( wp_date( (string) $format, $data[ $key ]->getTimestamp(), $data['timezone'] ) );
					if ( $data['time_tbd'] && ! $data['all_day'] ) {
						echo ' <span class="hk9-muted">(' . esc_html__( 'time TBD', 'heartland-k9s-core' ) . ')</span>';
					}
				} else {
					echo '<span class="hk9-muted">—</span>';
				}
				break;
			case 'hk9_scope':
				if ( class_exists( Dates::class ) ) {
					$data = Dates::get( $post_id );
					if ( ! $data['start'] ) {
						echo '<span class="hk9-muted">—</span>';
					} elseif ( Dates::is_upcoming( $post_id ) ) {
						echo '<span class="hk9-pill hk9-pill--good">' . esc_html__( 'Upcoming', 'heartland-k9s-core' ) . '</span>';
					} else {
						echo '<span class="hk9-pill">' . esc_html__( 'Past', 'heartland-k9s-core' ) . '</span>';
					}
				}
				break;
		}
	}

	private static function text( mixed $value ): void {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		echo '' !== $value ? esc_html( $value ) : '<span class="hk9-muted">—</span>';
	}

	private static function thumb( int $post_id, bool $contain ): void {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id > 0 ) {
			echo wp_get_attachment_image( $thumb_id, [ 48, 48 ], false, [ 'class' => 'hk9-col-thumb' . ( $contain ? ' hk9-col-thumb--contain' : '' ), 'loading' => 'lazy' ] );
		} else {
			echo '<span class="hk9-col-thumb--empty" aria-hidden="true"></span>';
		}
	}

	/**
	 * Filter dropdowns above the list.
	 */
	public static function filters( string $post_type, string $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		foreach ( self::META_FILTERS[ $post_type ] ?? [] as $arg => $meta_key ) {
			$options = self::filter_options( $post_type, $meta_key );
			if ( ! $options ) {
				continue;
			}
			$current = isset( $_GET[ $arg ] ) ? sanitize_key( wp_unslash( $_GET[ $arg ] ) ) : '';
			$label   = 'hk9_program_type' === $meta_key ? __( 'All programs', 'heartland-k9s-core' ) : ( 'hk9_relationship' === $meta_key ? __( 'All relationships', 'heartland-k9s-core' ) : __( 'All statuses', 'heartland-k9s-core' ) );
			echo '<label class="screen-reader-text" for="hk9-filter-' . esc_attr( $arg ) . '">' . esc_html( $label ) . '</label>';
			echo '<select name="' . esc_attr( $arg ) . '" id="hk9-filter-' . esc_attr( $arg ) . '">';
			echo '<option value="">' . esc_html( $label ) . '</option>';
			foreach ( $options as $value => $text ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $value ), selected( $current, (string) $value, false ), esc_html( $text ) );
			}
			echo '</select>';
		}
		if ( 'hk9_partner' === $post_type && taxonomy_exists( PartnerType::TAXONOMY ) ) {
			$current = isset( $_GET[ PartnerType::TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ PartnerType::TAXONOMY ] ) ) : '';
			wp_dropdown_categories(
				[
					'taxonomy'        => PartnerType::TAXONOMY,
					'name'            => PartnerType::TAXONOMY,
					'id'              => 'hk9-filter-partner-type',
					'value_field'     => 'slug',
					'selected'        => $current,
					'show_option_all' => __( 'All partner types', 'heartland-k9s-core' ),
					'hide_empty'      => false,
					'hide_if_empty'   => true,
				]
			);
		}
		// phpcs:enable
	}

	/**
	 * Apply sorting/filtering to the admin list query.
	 */
	public static function apply_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$type = (string) $query->get( 'post_type' );
		if ( ! in_array( $type, Registrar::TYPES, true ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$orderby   = (string) $query->get( 'orderby' );
		$order     = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
		$sort_meta = '';
		if ( '' === $orderby && in_array( $type, [ 'hk9_person', 'hk9_partner', 'hk9_team', 'hk9_campaign' ], true ) ) {
			$query->set( 'orderby', [ 'menu_order' => 'ASC', 'title' => 'ASC' ] );
		} elseif ( 'hk9_order' === $orderby ) {
			$query->set( 'orderby', [ 'menu_order' => $order, 'title' => 'ASC' ] );
		} elseif ( in_array( $orderby, [ 'hk9_start', 'hk9_end', 'hk9_registry_id' ], true ) ) {
			$sort_meta = $orderby;
		} elseif ( '' === $orderby && 'hk9_event' === $type ) {
			$sort_meta = 'hk9_start';
		}

		// Filter clauses (each narrows the list).
		$filters = [];
		foreach ( self::META_FILTERS[ $type ] ?? [] as $arg => $meta_key ) {
			if ( empty( $_GET[ $arg ] ) ) {
				continue;
			}
			$value = sanitize_key( wp_unslash( $_GET[ $arg ] ) );
			if ( ! isset( self::filter_options( $type, $meta_key )[ $value ] ) ) {
				continue;
			}
			$filters[] = [
				'key'   => $meta_key,
				'value' => $value,
			];
		}

		$existing = $query->get( 'meta_query' );
		$existing = is_array( $existing ) && $existing ? [ $existing ] : []; // Nested so its own relation is kept.
		if ( '' !== $sort_meta ) {
			// Sort by a meta value WITHOUT dropping posts that lack the key: a plain
			// meta_key + orderby=meta_value is an INNER JOIN, so events created via
			// REST/CLI/import without hk9_start would vanish from the list. A named
			// EXISTS clause ordered explicitly, OR-ed with a NOT EXISTS branch, keeps
			// them (sorted after the dated ones).
			$clause   = $sort_meta . '_clause';
			$sortable = [
				'relation' => 'OR',
				$clause    => [
					'key'     => $sort_meta,
					'compare' => 'EXISTS',
				],
				[
					'key'     => $sort_meta,
					'compare' => 'NOT EXISTS',
				],
			];
			$meta_query = array_merge( [ 'relation' => 'AND', $sortable ], $existing, $filters );
			$query->set( 'meta_key', '' );
			$query->set( 'meta_query', $meta_query );
			$query->set( 'orderby', [ $clause => $order, 'date' => 'DESC' ] );
		} elseif ( $filters || $existing ) {
			$query->set( 'meta_query', array_merge( $existing, $filters ) );
		}
		if ( 'hk9_partner' === $type && ! empty( $_GET[ PartnerType::TAXONOMY ] ) ) {
			$slug = sanitize_title( wp_unslash( $_GET[ PartnerType::TAXONOMY ] ) );
			if ( '' !== $slug ) {
				$tax_query   = (array) $query->get( 'tax_query' );
				$tax_query[] = [
					'taxonomy' => PartnerType::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slug,
				];
				$query->set( 'tax_query', $tax_query );
			}
		}
		// phpcs:enable
	}
}
