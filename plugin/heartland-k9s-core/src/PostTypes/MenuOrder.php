<?php
/**
 * Default menu_order for new records.
 *
 * Listings (people, partners, teams, campaigns, stories) sort by menu_order
 * ASC, then title. WordPress starts every new post at menu_order 0, which put a
 * freshly created record in front of everything already ordered 1…n. When the
 * editor creates a record (the auto-draft made by post-new.php / the block
 * editor) its menu_order is preset to max(menu_order) + 1 for that type, so new
 * records append at the end until staff move them.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class MenuOrder {

	/** Post types whose new records append at the end of the ordered list. */
	public const TYPES = [ 'hk9_person', 'hk9_partner', 'hk9_story', 'hk9_team', 'hk9_campaign' ];

	public static function register(): void {
		add_filter( 'wp_insert_post_data', [ self::class, 'default_for_new' ], 10, 4 );
		add_action( 'add_meta_boxes', [ self::class, 'classic_order_help' ], 20, 2 );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'block_editor_order_help' ] );
	}

	/** Help text shown with the Order field (both editors). */
	public static function help_text(): string {
		return __( 'Position in site listings — lower numbers come first. New records start at the end of the list (highest existing Order + 1); change the number to move this one.', 'heartland-k9s-core' );
	}

	/** Whether a type shows an Order field that this help applies to. */
	private static function has_order_field( string $type ): bool {
		return in_array( $type, self::types(), true ) && post_type_supports( $type, 'page-attributes' );
	}

	/**
	 * Classic editor: wrap core's "Post Attributes" box so the help sits right under the Order input.
	 *
	 * @param string        $post_type Post type.
	 * @param \WP_Post|null $post      Post.
	 */
	public static function classic_order_help( string $post_type, $post ): void {
		if ( ! self::has_order_field( $post_type ) || ! function_exists( 'page_attributes_meta_box' ) ) {
			return;
		}
		if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
			return; // The block editor renders Order in its Summary panel (see block_editor_order_help()).
		}
		global $wp_meta_boxes;
		$box = $wp_meta_boxes[ $post_type ]['side']['core']['pageparentdiv'] ?? null;
		if ( ! is_array( $box ) || 'page_attributes_meta_box' !== ( $box['callback'] ?? null ) ) {
			return;
		}
		// Swap the callback in place (remove + re-add would refuse a 'core' box or move it).
		$wp_meta_boxes[ $post_type ]['side']['core']['pageparentdiv']['callback'] = static function ( $post ): void {
			page_attributes_meta_box( $post );
			echo '<p class="description hk9-order-help" id="hk9-order-help">' . esc_html( self::help_text() ) . '</p>';
		};
	}

	/**
	 * Block editor: WordPress 6.7+ no longer renders an Order control in the post sidebar
	 * (core's page-attributes panel only shows "Parent"), so an "Order" number field bound
	 * to menu_order — with the help text under it — is added to the Summary panel through
	 * the PluginPostStatusInfo slot.
	 */
	public static function block_editor_order_help(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::has_order_field( (string) $screen->post_type ) ) {
			return;
		}
		wp_register_script( 'hk9-order-help', false, [ 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ], HK9_CORE_VERSION, true );
		wp_enqueue_script( 'hk9-order-help' );
		$js = <<<'JS'
( function ( wp ) {
	var Slot = wp.editor && wp.editor.PluginPostStatusInfo;
	var NumberControl = wp.components && ( wp.components.__experimentalNumberControl || wp.components.NumberControl );
	if ( ! Slot || ! wp.plugins || ! NumberControl || ! wp.data.useSelect ) {
		return;
	}
	var el = wp.element.createElement;
	var cfg = window.HK9OrderHelp || {};
	function OrderField() {
		var order = wp.data.useSelect( function ( select ) {
			var v = select( 'core/editor' ).getEditedPostAttribute( 'menu_order' );
			return v === null || v === undefined ? 0 : v;
		}, [] );
		var editPost = wp.data.useDispatch( 'core/editor' ).editPost;
		var state = wp.element.useState( null );
		return el( 'div', { className: 'hk9-order-field', style: { width: '100%' } },
			el( NumberControl, {
				label: cfg.label,
				value: state[ 0 ] === null ? order : state[ 0 ],
				__next40pxDefaultSize: true,
				onChange: function ( v ) {
					state[ 1 ]( v );
					var n = Number( v );
					if ( Number.isInteger( n ) && String( v ).trim() !== '' ) {
						editPost( { menu_order: n } );
					}
				},
				onBlur: function () { state[ 1 ]( null ); }
			} ),
			el( 'p', { id: 'hk9-order-help', className: 'components-base-control__help', style: { marginTop: '8px' } }, cfg.help )
		);
	}
	wp.plugins.registerPlugin( 'hk9-order-help', {
		render: function () {
			return el( Slot, { className: 'hk9-order-help' }, el( OrderField ) );
		}
	} );
} )( window.wp );
JS;
		wp_add_inline_script(
			'hk9-order-help',
			'window.HK9OrderHelp = ' . wp_json_encode(
				[
					'label' => __( 'Order', 'heartland-k9s-core' ),
					'help'  => self::help_text(),
				]
			) . ';' . "\n" . $js
		);
	}

	/**
	 * Post types handled (filterable).
	 *
	 * @return string[]
	 */
	public static function types(): array {
		/**
		 * Filters the post types whose new records get menu_order = max + 1.
		 *
		 * @param string[] $types Post types.
		 */
		return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'hk9/menu_order/types', self::TYPES ) ) ) );
	}

	/**
	 * wp_insert_post_data: a brand-new auto-draft of a handled type gets the next order number.
	 *
	 * @param array $data                Slashed, sanitized post data.
	 * @param array $postarr             Slashed, sanitized post array.
	 * @param array $unsanitized_postarr Post array as passed to wp_insert_post().
	 * @param bool  $update              Whether this is an update.
	 */
	public static function default_for_new( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		if ( $update ) {
			return $data;
		}
		$type = (string) ( $data['post_type'] ?? '' );
		if ( ! in_array( $type, self::types(), true ) ) {
			return $data;
		}
		if ( 'auto-draft' !== (string) ( $data['post_status'] ?? '' ) ) {
			return $data;
		}
		// An explicit order passed to wp_insert_post() (importer, CLI) always wins.
		if ( isset( $unsanitized_postarr['menu_order'] ) && 0 !== (int) $unsanitized_postarr['menu_order'] ) {
			return $data;
		}
		$data['menu_order'] = self::next( $type );
		return $data;
	}

	/** max(menu_order) + 1 for a type (never below 1). Auto-drafts are ignored. */
	public static function next( string $type ): int {
		global $wpdb;
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'",
				$type
			)
		);
		return max( 0, (int) $max ) + 1;
	}
}
