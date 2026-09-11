<?php
/**
 * Heartland → Settings screen (tabbed, Settings API).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Settings;

use HK9\Core\Support\Helpers;

defined( 'ABSPATH' ) || exit;

final class Page {

	public const SLUG = 'hk9-settings';
	public const CAP  = 'hk9_manage_settings';

	/** @var string Hook suffix returned by add_submenu_page(). */
	private static string $hook = '';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ], 20 );
		add_action( 'admin_init', [ self::class, 'register_fields' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'option_page_capability_hk9_settings', static fn(): string => self::CAP );
	}

	public static function hook(): string {
		return self::$hook;
	}

	public static function add_menu(): void {
		$hook = add_submenu_page(
			'hk9',
			__( 'Heartland Settings', 'heartland-k9s-core' ),
			__( 'Settings', 'heartland-k9s-core' ),
			self::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
		self::$hook = is_string( $hook ) ? $hook : '';
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		wp_enqueue_media();
		$css = HK9_CORE_DIR . 'assets/css/admin.css';
		$js  = HK9_CORE_DIR . 'assets/js/settings.js';
		wp_enqueue_style( 'hk9-admin', HK9_CORE_URL . 'assets/css/admin.css', [], (string) ( file_exists( $css ) ? filemtime( $css ) : HK9_CORE_VERSION ) );
		wp_enqueue_script( 'hk9-settings', HK9_CORE_URL . 'assets/js/settings.js', [ 'media-editor' ], (string) ( file_exists( $js ) ? filemtime( $js ) : HK9_CORE_VERSION ), true );
		wp_add_inline_script(
			'hk9-settings',
			'window.HK9 = window.HK9 || {}; window.HK9.settings = ' . wp_json_encode(
				[
					'i18n' => [
						'selectImage' => __( 'Select image', 'heartland-k9s-core' ),
						'useImage'    => __( 'Use this image', 'heartland-k9s-core' ),
						'remove'      => __( 'Remove', 'heartland-k9s-core' ),
						'unsaved'     => __( 'You have unsaved changes.', 'heartland-k9s-core' ),
					],
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Current tab from the URL (validated).
	 */
	public static function current_tab(): string {
		$tabs = Schema::tab_groups();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Register one settings section per group and one field per key.
	 */
	public static function register_fields(): void {
		$fields = Schema::fields();
		foreach ( Schema::tab_groups() as $tab => $groups ) {
			$page = self::SLUG . '-' . $tab;
			foreach ( $groups as $group ) {
				if ( ! isset( $fields[ $group ] ) ) {
					continue;
				}
				$section = 'hk9_' . $group;
				add_settings_section(
					$section,
					count( $groups ) > 1 ? $fields[ $group ]['label'] : '',
					static function () use ( $fields, $group ): void {
						if ( '' !== $fields[ $group ]['description'] ) {
							echo '<p class="description hk9-section-description">' . wp_kses_post( $fields[ $group ]['description'] ) . '</p>';
						}
					},
					$page,
					[
						'before_section' => '<div class="hk9-settings-section" id="hk9-section-' . esc_attr( $group ) . '">',
						'after_section'  => '</div>',
					]
				);
				foreach ( $fields[ $group ]['fields'] as $key => $field ) {
					$id = 'hk9_' . $group . '_' . $key;
					add_settings_field(
						$id,
						esc_html( $field['label'] ),
						[ self::class, 'render_field' ],
						$page,
						$section,
						[
							'label_for' => in_array( $field['type'], [ 'link', 'image', 'repeater', 'note', 'toggle' ], true ) ? '' : $id,
							'group'     => $group,
							'key'       => $key,
							'field'     => $field,
							'id'        => $id,
							'class'     => 'hk9-field hk9-field--' . $field['type'],
						]
					);
				}
			}
		}
	}

	/**
	 * Screen output.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'heartland-k9s-core' ), 403 );
		}
		$tab    = self::current_tab();
		$labels = Schema::tab_labels();
		$groups = Schema::tab_groups()[ $tab ];
		?>
		<div class="wrap hk9-settings">
			<h1><?php esc_html_e( 'Heartland Settings', 'heartland-k9s-core' ); ?></h1>
			<?php settings_errors(); ?>
			<nav class="nav-tab-wrapper hk9-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'heartland-k9s-core' ); ?>">
				<?php foreach ( $labels as $slug => $label ) : ?>
					<a class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'page' => self::SLUG, 'tab' => $slug ], admin_url( 'admin.php' ) ) ); ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="options.php" class="hk9-settings-form" data-hk9-tab="<?php echo esc_attr( $tab ); ?>">
				<?php settings_fields( 'hk9_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( Schema::OPTION ); ?>[__groups]" value="<?php echo esc_attr( implode( ',', $groups ) ); ?>">
				<?php do_settings_sections( self::SLUG . '-' . $tab ); ?>
				<?php submit_button( __( 'Save changes', 'heartland-k9s-core' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Field callback.
	 *
	 * @param array $args {group, key, field, id}.
	 */
	public static function render_field( array $args ): void {
		$group = $args['group'];
		$key   = $args['key'];
		$field = $args['field'];
		$id    = $args['id'];
		$name  = Schema::OPTION . '[' . $group . '][' . $key . ']';
		$value = 'note' === $field['type'] ? null : ( Store::raw()[ $group ][ $key ] ?? $field['default'] );
		$help  = (string) ( $field['help'] ?? '' );
		$desc  = '' !== $help ? ' aria-describedby="' . esc_attr( $id . '-help' ) . '"' : '';

		switch ( $field['type'] ) {
			case 'note':
				break;

			case 'toggle':
				printf(
					'<label for="%1$s" class="hk9-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> <span>%5$s</span></label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					$desc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					esc_html__( 'Enabled', 'heartland-k9s-core' )
				);
				break;

			case 'textarea':
			case 'emails':
			case 'cidrs':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text%6$s"%4$s>%5$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					'textarea' === $field['type'] ? 4 : 3,
					$desc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_textarea( (string) $value ),
					'cidrs' === $field['type'] ? ' code' : ''
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text"%4$s%5$s%6$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					isset( $field['min'] ) ? ' min="' . esc_attr( (string) $field['min'] ) . '"' : '',
					isset( $field['max'] ) ? ' max="' . esc_attr( (string) $field['max'] ) . '"' : '',
					$desc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				break;

			case 'select':
				$options = $field['options'] ?? [];
				$options = array_is_list( $options ) ? array_combine( $options, $options ) : $options;
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $desc . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( $options as $opt_value => $opt_label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $opt_value ), selected( (string) $value, (string) $opt_value, false ), esc_html( (string) $opt_label ) );
				}
				echo '</select>';
				break;

			case 'color':
				printf(
					'<span class="hk9-color"><input type="color" class="hk9-color__swatch" value="%3$s" aria-label="%5$s" data-hk9-color-for="%1$s"><input type="text" id="%1$s" name="%2$s" value="%3$s" class="hk9-color__hex regular-text code" pattern="#?[0-9a-fA-F]{6}" maxlength="7" autocomplete="off"%4$s></span>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					$desc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					/* translators: %s: setting label */
					esc_attr( sprintf( __( '%s color picker', 'heartland-k9s-core' ), $field['label'] ) )
				);
				break;

			case 'image':
				self::render_image_field( $id, $name, (int) $value, $field );
				break;

			case 'link':
				self::render_link_field( $id, $name, Helpers::normalize_link( $value ), $field );
				break;

			case 'repeater':
				self::render_repeater_field( $id, $name, is_array( $value ) ? $value : [], $field );
				break;

			case 'email':
			case 'url':
			case 'code':
			case 'header':
			case 'slug':
			case 'text':
			default:
				$type = match ( $field['type'] ) {
					'email' => 'email',
					'url'   => 'url',
					default => 'text',
				};
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text%5$s"%6$s%7$s>',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					in_array( $field['type'], [ 'code', 'header' ], true ) ? ' code' : '',
					$desc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'url' === $field['type'] ? ' placeholder="https://"' : ''
				);
				break;
		}

		if ( '' !== $help ) {
			echo '<p class="description" id="' . esc_attr( $id . '-help' ) . '">' . wp_kses( $help, [ 'a' => [ 'href' => [], 'target' => [] ], 'code' => [], 'strong' => [], 'em' => [] ] ) . '</p>';
		}
	}

	private static function render_image_field( string $id, string $name, int $value, array $field ): void {
		$src = $value > 0 ? wp_get_attachment_image_url( $value, 'medium' ) : '';
		$alt = $value > 0 ? get_post_meta( $value, '_wp_attachment_image_alt', true ) : '';
		?>
		<div class="hk9-image-field<?php echo $value > 0 ? ' has-image' : ''; ?>" data-hk9-image>
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" data-hk9-image-id>
			<div class="hk9-image-field__preview" data-hk9-image-preview>
				<?php if ( $src ) : ?>
					<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( (string) $alt ); ?>">
				<?php endif; ?>
			</div>
			<div class="hk9-image-field__actions">
				<?php /* translators: %s: setting label (e.g. "Header logo") */ ?>
				<button type="button" class="button" data-hk9-image-select aria-label="<?php echo esc_attr( sprintf( __( '%s: select or replace image', 'heartland-k9s-core' ), $field['label'] ) ); ?>"<?php echo '' !== (string) ( $field['help'] ?? '' ) ? ' aria-describedby="' . esc_attr( $id . '-help' ) . '"' : ''; ?>>
					<span class="hk9-when-empty"><?php esc_html_e( 'Select image', 'heartland-k9s-core' ); ?></span>
					<span class="hk9-when-set"><?php esc_html_e( 'Replace', 'heartland-k9s-core' ); ?></span>
				</button>
				<?php /* translators: %s: setting label (e.g. "Header logo") */ ?>
				<button type="button" class="button-link button-link-delete hk9-when-set" data-hk9-image-remove aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'heartland-k9s-core' ), $field['label'] ) ); ?>"><?php esc_html_e( 'Remove', 'heartland-k9s-core' ); ?></button>
			</div>
		</div>
		<?php
	}

	private static function render_link_field( string $id, string $name, array $link, array $field ): void {
		$mode  = $link['post_id'] > 0 ? 'post' : 'url';
		$pages = get_pages(
			[
				'post_status' => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'sort_column' => 'post_title',
				'number'      => 500,
			]
		);
		?>
		<fieldset class="hk9-link-field" data-hk9-link data-mode="<?php echo esc_attr( $mode ); ?>">
			<legend class="screen-reader-text"><?php echo esc_html( $field['label'] ); ?></legend>
			<div class="hk9-link-field__modes" role="radiogroup" aria-label="<?php esc_attr_e( 'Destination type', 'heartland-k9s-core' ); ?>">
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="post" data-hk9-link-mode<?php checked( $mode, 'post' ); ?>> <?php esc_html_e( 'A page on this site', 'heartland-k9s-core' ); ?></label>
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="url" data-hk9-link-mode<?php checked( $mode, 'url' ); ?>> <?php esc_html_e( 'Web address', 'heartland-k9s-core' ); ?></label>
			</div>
			<div class="hk9-link-field__post" data-hk9-link-post>
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-post"><?php esc_html_e( 'Page', 'heartland-k9s-core' ); ?></label>
				<select id="<?php echo esc_attr( $id ); ?>-post" name="<?php echo esc_attr( $name ); ?>[post_id]">
					<option value="0"><?php esc_html_e( '— Select a page —', 'heartland-k9s-core' ); ?></option>
					<?php foreach ( $pages as $page ) : ?>
						<option value="<?php echo esc_attr( (string) $page->ID ); ?>"<?php selected( $link['post_id'], $page->ID ); ?>>
							<?php
							echo esc_html( str_repeat( '— ', count( get_post_ancestors( $page ) ) ) . ( '' !== $page->post_title ? $page->post_title : __( '(no title)', 'heartland-k9s-core' ) ) );
							if ( 'publish' !== $page->post_status ) {
								echo ' (' . esc_html( $page->post_status ) . ')';
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="hk9-link-field__url" data-hk9-link-url>
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-url"><?php esc_html_e( 'Web address', 'heartland-k9s-core' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $link['url'] ); ?>" class="regular-text code" placeholder="https://" inputmode="url">
			</div>
			<div class="hk9-link-field__label">
				<label for="<?php echo esc_attr( $id ); ?>-label"><?php esc_html_e( 'Label', 'heartland-k9s-core' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $link['label'] ); ?>" class="regular-text" data-hk9-link-label>
				<span class="description"><?php esc_html_e( 'Optional link text where the site prints this destination as a button or shortcut.', 'heartland-k9s-core' ); ?></span>
			</div>
			<label class="hk9-link-field__target"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[target]" value="_blank"<?php checked( $link['target'], '_blank' ); ?>> <?php esc_html_e( 'Open in a new tab', 'heartland-k9s-core' ); ?></label>
		</fieldset>
		<?php
	}

	private static function render_repeater_field( string $id, string $name, array $rows, array $field ): void {
		$subs = $field['fields'] ?? [];
		$labels = [
			'value' => __( 'Value', 'heartland-k9s-core' ),
			'label' => __( 'Label', 'heartland-k9s-core' ),
		];
		$render_row = static function ( string $index, array $row ) use ( $name, $subs, $labels ): void {
			echo '<tr class="hk9-repeater__row">';
			foreach ( $subs as $sub_key => $sub ) {
				printf(
					'<td><label class="screen-reader-text" for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" class="regular-text%5$s" data-hk9-repeater-input></td>',
					esc_attr( $name . '-' . $index . '-' . $sub_key ),
					esc_html( $labels[ $sub_key ] ?? $sub_key ),
					esc_attr( $name . '[' . $index . '][' . $sub_key . ']' ),
					esc_attr( (string) ( $row[ $sub_key ] ?? '' ) ),
					'slug' === ( $sub['type'] ?? '' ) ? ' code' : ''
				);
			}
			echo '<td class="hk9-repeater__actions">';
			echo '<button type="button" class="button-link" data-hk9-repeater-up aria-label="' . esc_attr__( 'Move up', 'heartland-k9s-core' ) . '">&uarr;</button> ';
			echo '<button type="button" class="button-link" data-hk9-repeater-down aria-label="' . esc_attr__( 'Move down', 'heartland-k9s-core' ) . '">&darr;</button> ';
			echo '<button type="button" class="button-link button-link-delete" data-hk9-repeater-remove>' . esc_html__( 'Remove', 'heartland-k9s-core' ) . '</button>';
			echo '</td></tr>';
		};
		?>
		<div class="hk9-repeater" data-hk9-repeater data-name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<table class="widefat striped hk9-repeater__table">
				<thead><tr>
					<?php foreach ( $subs as $sub_key => $sub ) : ?>
						<th scope="col"><?php echo esc_html( $labels[ $sub_key ] ?? $sub_key ); ?></th>
					<?php endforeach; ?>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'heartland-k9s-core' ); ?></span></th>
				</tr></thead>
				<tbody data-hk9-repeater-rows>
					<?php
					foreach ( array_values( $rows ) as $i => $row ) {
						$render_row( (string) $i, is_array( $row ) ? $row : [] );
					}
					?>
				</tbody>
			</table>
			<p class="hk9-repeater__empty" data-hk9-repeater-empty<?php echo $rows ? ' hidden' : ''; ?>><?php esc_html_e( 'No rows yet.', 'heartland-k9s-core' ); ?></p>
			<template data-hk9-repeater-template>
				<?php $render_row( '__INDEX__', [] ); ?>
			</template>
			<p><button type="button" class="button" data-hk9-repeater-add><?php esc_html_e( 'Add row', 'heartland-k9s-core' ); ?></button></p>
		</div>
		<?php
	}
}
