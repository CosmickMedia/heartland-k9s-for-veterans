<?php
/**
 * Heartland → Redirects admin screen (list table + add/edit form + test).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Redirects;

use HK9\Core\PostTypes\Registrar;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const SLUG = 'hk9-redirects';
	public const CAP  = 'hk9_manage_redirects';

	private static string $hook = '';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ], 21 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function url( array $args = [] ): string {
		if ( isset( $args['key'] ) ) {
			$args['key'] = rawurlencode( (string) $args['key'] ); // add_query_arg() does not encode values ('&' in query rules).
		}
		return add_query_arg( array_merge( [ 'page' => self::SLUG ], $args ), admin_url( 'admin.php' ) );
	}

	public static function add_menu(): void {
		$hook = add_submenu_page(
			'hk9',
			__( 'Redirects', 'heartland-k9s-core' ),
			__( 'Redirects', 'heartland-k9s-core' ),
			self::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
		self::$hook = is_string( $hook ) ? $hook : '';
		if ( '' !== self::$hook ) {
			add_action( 'load-' . self::$hook, [ self::class, 'handle_actions' ] );
		}
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		$css = HK9_CORE_DIR . 'assets/css/admin.css';
		wp_enqueue_style( 'hk9-admin', HK9_CORE_URL . 'assets/css/admin.css', [], (string) ( file_exists( $css ) ? filemtime( $css ) : HK9_CORE_VERSION ) );
		wp_add_inline_script(
			'wp-a11y',
			"document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-hk9-target-mode]').forEach(function(w){var r=w.querySelectorAll('input[name=\"target_type\"]');function a(){r.forEach(function(x){if(x.checked){w.setAttribute('data-mode',x.value);}});}r.forEach(function(x){x.addEventListener('change',a);});a();});});"
		);
		wp_enqueue_script( 'wp-a11y' );
	}

	/**
	 * Process actions before any output (load-{hook}).
	 */
	public static function handle_actions(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$action = '';
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified per action below.
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' === $action ) {
			return;
		}
		$key = isset( $_REQUEST['key'] ) ? Store::normalize_key( (string) wp_unslash( $_REQUEST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $action ) {
			case 'save':
				check_admin_referer( 'hk9_redirect_save' );
				self::handle_save();
				break;

			case 'enable':
			case 'disable':
				check_admin_referer( 'hk9_redirect_' . $action . '_' . $key );
				if ( '' !== $key && Store::set_enabled( $key, 'enable' === $action ) ) {
					self::redirect_with( 'enable' === $action ? 'enabled' : 'disabled' );
				}
				self::redirect_with( 'missing' );
				break;

			case 'delete':
				check_admin_referer( 'hk9_redirect_delete_' . $key );
				$rule = Store::get( $key );
				if ( ! $rule ) {
					self::redirect_with( 'missing' );
				}
				if ( $rule['seed'] ) {
					self::redirect_with( 'seed_locked' );
				}
				Store::delete( $key );
				self::redirect_with( 'deleted' );
				break;

			case 'test':
				check_admin_referer( 'hk9_redirect_test_' . $key );
				$result = Resolver::test( $key, true );
				set_transient( 'hk9_redirect_test_' . get_current_user_id(), $result, 120 );
				self::redirect_with( 'tested', [ 'key' => $key ] );
				break;

			case 'seed':
				check_admin_referer( 'hk9_redirect_seed' );
				$count = Store::seed( false );
				self::redirect_with( 'seeded', [ 'count' => $count ] );
				break;

			case 'bulk-enable':
			case 'bulk-disable':
			case 'bulk-delete':
				check_admin_referer( 'bulk-hk9_redirects' );
				$keys = isset( $_REQUEST['keys'] ) ? array_map( 'strval', (array) wp_unslash( $_REQUEST['keys'] ) ) : [];
				$done = 0;
				foreach ( $keys as $k ) {
					$k = Store::normalize_key( $k );
					if ( 'bulk-delete' === $action ) {
						$rule = Store::get( $k );
						if ( $rule && ! $rule['seed'] && Store::delete( $k ) ) {
							++$done;
						}
					} elseif ( Store::set_enabled( $k, 'bulk-enable' === $action ) ) {
						++$done;
					}
				}
				self::redirect_with( 'bulk', [ 'count' => $done ] );
				break;
		}
	}

	/**
	 * Add/edit form submission.
	 */
	private static function handle_save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() in the caller.
		$source      = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$replace_key = isset( $_POST['replace_key'] ) ? Store::normalize_key( sanitize_text_field( wp_unslash( $_POST['replace_key'] ) ) ) : '';
		$type        = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : 'path';
		$status      = isset( $_POST['status'] ) ? (int) $_POST['status'] : 301;
		$note        = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$enabled     = ! empty( $_POST['enabled'] );
		$force       = ! empty( $_POST['force'] );
		$to          = match ( $type ) {
			'post'   => [
				'type' => 'post',
				'id'   => isset( $_POST['target_post'] ) ? absint( $_POST['target_post'] ) : 0,
			],
			'record' => [
				'type' => 'record',
				'slug' => isset( $_POST['target_record'] ) ? sanitize_text_field( wp_unslash( $_POST['target_record'] ) ) : '',
			],
			default  => [
				'type' => 'path',
				'path' => isset( $_POST['target_path'] ) ? sanitize_text_field( wp_unslash( $_POST['target_path'] ) ) : '',
			],
		};
		// phpcs:enable

		$result = Store::upsert( $source, $to, $status, $note, $enabled, $force, '' !== $replace_key ? $replace_key : null );
		if ( is_wp_error( $result ) ) {
			set_transient( 'hk9_redirect_error_' . get_current_user_id(), [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ], 120 );
			$args = [ 'action' => '' !== $replace_key ? 'edit' : 'add' ];
			if ( '' !== $replace_key ) {
				$args['key'] = $replace_key;
			}
			wp_safe_redirect( self::url( $args + [ 'hk9_msg' => 'error' ] ) );
			exit;
		}
		if ( $result['warnings'] ) {
			set_transient( 'hk9_redirect_warn_' . get_current_user_id(), $result['warnings'], 120 );
		}
		self::redirect_with( 'saved', [ 'key' => $result['key'] ] );
	}

	private static function redirect_with( string $msg, array $args = [] ): never {
		wp_safe_redirect( self::url( array_merge( $args, [ 'hk9_msg' => $msg ] ) ) );
		exit;
	}

	/**
	 * Notices from the last action.
	 */
	private static function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- messages only.
		$msg   = isset( $_GET['hk9_msg'] ) ? sanitize_key( wp_unslash( $_GET['hk9_msg'] ) ) : '';
		$count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable
		$uid = get_current_user_id();
		$out = [];
		switch ( $msg ) {
			case 'saved':
				$out[] = [ 'success', __( 'Redirect saved.', 'heartland-k9s-core' ) ];
				$warn  = get_transient( 'hk9_redirect_warn_' . $uid );
				if ( is_array( $warn ) ) {
					delete_transient( 'hk9_redirect_warn_' . $uid );
					foreach ( $warn as $w ) {
						$out[] = [ 'warning', (string) $w ];
					}
				}
				break;
			case 'error':
				$err = get_transient( 'hk9_redirect_error_' . $uid );
				delete_transient( 'hk9_redirect_error_' . $uid );
				$out[] = [ 'error', is_array( $err ) && isset( $err['message'] ) ? (string) $err['message'] : __( 'The redirect could not be saved.', 'heartland-k9s-core' ) ];
				break;
			case 'enabled':
				$out[] = [ 'success', __( 'Redirect enabled.', 'heartland-k9s-core' ) ];
				break;
			case 'disabled':
				$out[] = [ 'success', __( 'Redirect disabled.', 'heartland-k9s-core' ) ];
				break;
			case 'deleted':
				$out[] = [ 'success', __( 'Redirect deleted.', 'heartland-k9s-core' ) ];
				break;
			case 'missing':
				$out[] = [ 'error', __( 'That redirect no longer exists.', 'heartland-k9s-core' ) ];
				break;
			case 'seed_locked':
				$out[] = [ 'warning', __( 'Seeded migration redirects cannot be deleted — disable them instead.', 'heartland-k9s-core' ) ];
				break;
			case 'seeded':
				/* translators: %d: number of rules */
				$out[] = [ 'success', sprintf( __( '%d missing seed redirect(s) restored.', 'heartland-k9s-core' ), $count ) ];
				break;
			case 'bulk':
				/* translators: %d: number of rules */
				$out[] = [ 'success', sprintf( __( '%d redirect(s) updated.', 'heartland-k9s-core' ), $count ) ];
				break;
			case 'tested':
				$test = get_transient( 'hk9_redirect_test_' . $uid );
				delete_transient( 'hk9_redirect_test_' . $uid );
				if ( is_array( $test ) ) {
					/* translators: 1: source key, 2: result message */
					$out[] = [ $test['ok'] ? 'success' : 'warning', sprintf( __( 'Test %1$s: %2$s', 'heartland-k9s-core' ), '<code>' . esc_html( $key ) . '</code>', esc_html( (string) $test['message'] ) ) ];
				}
				break;
		}
		foreach ( $out as [ $type, $text ] ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), wp_kses( $text, [ 'code' => [], 'a' => [ 'href' => [] ], 'strong' => [] ] ) );
		}
	}

	/**
	 * Screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'heartland-k9s-core' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$key    = isset( $_GET['key'] ) ? Store::normalize_key( (string) wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable
		$editing = 'edit' === $action && '' !== $key ? Store::get( $key ) : null;
		$show_form = 'add' === $action || null !== $editing;
		?>
		<div class="wrap hk9-redirects">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Redirects', 'heartland-k9s-core' ); ?></h1>
			<?php if ( ! $show_form ) : ?>
				<a href="<?php echo esc_url( self::url( [ 'action' => 'add' ] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add redirect', 'heartland-k9s-core' ); ?></a>
				<a href="<?php echo esc_url( wp_nonce_url( self::url( [ 'action' => 'seed' ] ), 'hk9_redirect_seed' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Restore missing seeds', 'heartland-k9s-core' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<p class="description"><?php esc_html_e( 'Legacy URLs from the previous site are redirected here before WordPress looks for content. Seeded rules cover the printed BarKode QR paths and old gallery/slider URLs.', 'heartland-k9s-core' ); ?></p>
			<?php self::render_notices(); ?>
			<?php
			if ( $show_form ) {
				self::render_form( $editing ? $key : '', $editing );
			}
			$table = new ListTable();
			$table->prepare_items();
			?>
			<form method="get" id="hk9-redirects-filter">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php $table->search_box( __( 'Search redirects', 'heartland-k9s-core' ), 'hk9-redirect' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add/edit form.
	 */
	private static function render_form( string $key, ?array $rule ): void {
		$to     = $rule['to'] ?? [ 'type' => 'path', 'path' => '' ];
		$status = $rule['status'] ?? 301;
		$mode   = $to['type'] ?? 'path';
		$can_records = post_type_exists( 'hk9_barkode' ) && current_user_can( get_post_type_object( 'hk9_barkode' )->cap->edit_posts );
		?>
		<div class="hk9-redirect-form">
			<h2><?php echo '' !== $key ? esc_html__( 'Edit redirect', 'heartland-k9s-core' ) : esc_html__( 'Add redirect', 'heartland-k9s-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( self::url() ); ?>">
				<?php wp_nonce_field( 'hk9_redirect_save' ); ?>
				<input type="hidden" name="action" value="save">
				<input type="hidden" name="replace_key" value="<?php echo esc_attr( $key ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hk9-redirect-source"><?php esc_html_e( 'Source path', 'heartland-k9s-core' ); ?></label></th>
						<td>
							<input type="text" id="hk9-redirect-source" name="source" value="<?php echo esc_attr( $key ); ?>" class="regular-text code" required placeholder="/old-page/" aria-describedby="hk9-redirect-source-help"<?php echo $rule && $rule['seed'] ? ' readonly' : ''; ?>>
							<p class="description" id="hk9-redirect-source-help"><?php esc_html_e( 'Site-relative path, e.g. /old-page/ or /?foogallery=2468. Matching is case-insensitive.', 'heartland-k9s-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Destination', 'heartland-k9s-core' ); ?></th>
						<td>
							<fieldset class="hk9-target-mode" data-hk9-target-mode data-mode="<?php echo esc_attr( $mode ); ?>">
								<legend class="screen-reader-text"><?php esc_html_e( 'Destination type', 'heartland-k9s-core' ); ?></legend>
								<label><input type="radio" name="target_type" value="post"<?php checked( $mode, 'post' ); ?>> <?php esc_html_e( 'A page or item on this site', 'heartland-k9s-core' ); ?></label>
								<?php if ( $can_records ) : ?>
									&nbsp; <label><input type="radio" name="target_type" value="record"<?php checked( $mode, 'record' ); ?>> <?php esc_html_e( 'A BarKode record', 'heartland-k9s-core' ); ?></label>
								<?php endif; ?>
								&nbsp; <label><input type="radio" name="target_type" value="path"<?php checked( $mode, 'path' ); ?>> <?php esc_html_e( 'A path or web address', 'heartland-k9s-core' ); ?></label>

								<div class="hk9-target-field hk9-target-field--post">
									<label class="screen-reader-text" for="hk9-target-post"><?php esc_html_e( 'Page or item', 'heartland-k9s-core' ); ?></label>
									<select name="target_post" id="hk9-target-post">
										<option value="0"><?php esc_html_e( '— Select —', 'heartland-k9s-core' ); ?></option>
										<?php self::post_options( (int) ( $to['id'] ?? 0 ) ); ?>
									</select>
								</div>
								<?php if ( $can_records ) : ?>
									<div class="hk9-target-field hk9-target-field--record">
										<label class="screen-reader-text" for="hk9-target-record"><?php esc_html_e( 'BarKode record', 'heartland-k9s-core' ); ?></label>
										<?php $records = get_posts( [ 'post_type' => 'hk9_barkode', 'post_status' => [ 'publish', 'draft', 'pending', 'private' ], 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ] ); ?>
										<?php if ( $records ) : ?>
											<select name="target_record" id="hk9-target-record">
												<option value=""><?php esc_html_e( '— Select a record —', 'heartland-k9s-core' ); ?></option>
												<?php foreach ( $records as $record ) : ?>
													<option value="<?php echo esc_attr( $record->post_name ); ?>"<?php selected( $to['slug'] ?? '', $record->post_name ); ?>><?php echo esc_html( get_the_title( $record ) . ' (' . $record->post_name . ')' . ( 'publish' !== $record->post_status ? ' — ' . $record->post_status : '' ) ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<input type="text" name="target_record" id="hk9-target-record" value="<?php echo esc_attr( $to['slug'] ?? '' ); ?>" class="regular-text code" placeholder="hk923-005">
											<p class="description"><?php esc_html_e( 'No records exist yet; enter the record slug. Visitors land on the BarKode page until the record is published.', 'heartland-k9s-core' ); ?></p>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<div class="hk9-target-field hk9-target-field--path">
									<label class="screen-reader-text" for="hk9-target-path"><?php esc_html_e( 'Path or web address', 'heartland-k9s-core' ); ?></label>
									<input type="text" name="target_path" id="hk9-target-path" value="<?php echo esc_attr( $to['path'] ?? '' ); ?>" class="regular-text code" placeholder="/stories/">
								</div>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hk9-redirect-status"><?php esc_html_e( 'Type', 'heartland-k9s-core' ); ?></label></th>
						<td>
							<select name="status" id="hk9-redirect-status">
								<option value="301"<?php selected( $status, 301 ); ?>><?php esc_html_e( '301 — Permanent (search engines update their index)', 'heartland-k9s-core' ); ?></option>
								<option value="302"<?php selected( $status, 302 ); ?>><?php esc_html_e( '302 — Temporary', 'heartland-k9s-core' ); ?></option>
								<option value="410"<?php selected( $status, 410 ); ?>><?php esc_html_e( '410 — Gone (no destination)', 'heartland-k9s-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hk9-redirect-note"><?php esc_html_e( 'Note', 'heartland-k9s-core' ); ?></label></th>
						<td><input type="text" name="note" id="hk9-redirect-note" value="<?php echo esc_attr( $rule['note'] ?? '' ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'heartland-k9s-core' ); ?></th>
						<td>
							<label><input type="checkbox" name="enabled" value="1"<?php checked( $rule['enabled'] ?? true ); ?>> <?php esc_html_e( 'Enabled', 'heartland-k9s-core' ); ?></label><br>
							<label><input type="checkbox" name="force" value="1"> <?php esc_html_e( 'Override: keep the redirect even if the source currently shows published content', 'heartland-k9s-core' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<?php submit_button( '' !== $key ? __( 'Update redirect', 'heartland-k9s-core' ) : __( 'Add redirect', 'heartland-k9s-core' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( self::url() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'heartland-k9s-core' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * <optgroup> lists of pages and viewable Heartland items.
	 */
	private static function post_options( int $selected ): void {
		$groups = [ 'page' => __( 'Pages', 'heartland-k9s-core' ) ];
		foreach ( Registrar::VIEWABLE_TYPES as $type ) {
			if ( 'hk9_barkode' === $type ) {
				continue;
			}
			$obj = get_post_type_object( $type );
			if ( $obj ) {
				$groups[ $type ] = (string) $obj->labels->name;
			}
		}
		$groups['post'] = __( 'Posts', 'heartland-k9s-core' );
		foreach ( $groups as $type => $label ) {
			$posts = get_posts(
				[
					'post_type'   => $type,
					'post_status' => 'publish',
					'numberposts' => 300,
					'orderby'     => 'title',
					'order'       => 'ASC',
				]
			);
			if ( ! $posts ) {
				continue;
			}
			echo '<optgroup label="' . esc_attr( $label ) . '">';
			foreach ( $posts as $post ) {
				printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $post->ID, selected( $selected, (int) $post->ID, false ), esc_html( get_the_title( $post ) ) );
			}
			echo '</optgroup>';
		}
	}
}
