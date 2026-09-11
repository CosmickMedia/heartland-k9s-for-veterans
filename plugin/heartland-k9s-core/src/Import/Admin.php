<?php
/**
 * Heartland -> Setup & Import admin screen (menu slug hk9-import, manage_options).
 *
 * Upload a payload ZIP (unpacked into a protected uploads/hk9-payload-<random>/
 * directory) or point at a bind-mounted server path in local development, then
 * dry-run / import / pause / resume / retry / roll back with progress polled
 * over REST (hk9/v1/import/*). Logs download through admin-post + nonce.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const SLUG        = 'hk9-import';
	public const PARENT      = 'hk9';
	public const CAP         = 'manage_options';
	public const NONCE_UP    = 'hk9_import_upload';
	public const NONCE_LOG   = 'hk9_import_log';
	public const ACTION_UP   = 'hk9_import_upload';
	public const ACTION_LOG  = 'hk9_import_log';

	private static string $hook = '';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_post_' . self::ACTION_UP, [ self::class, 'handle_upload' ] );
		add_action( 'admin_post_' . self::ACTION_LOG, [ self::class, 'handle_log' ] );
	}

	public static function menu(): void {
		global $admin_page_hooks;

		$parent = isset( $admin_page_hooks[ self::PARENT ] ) ? self::PARENT : 'tools.php';
		$hook   = add_submenu_page(
			$parent,
			__( 'Setup & Import', 'heartland-k9s-core' ),
			__( 'Setup & Import', 'heartland-k9s-core' ),
			self::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
		self::$hook = (string) $hook;
		add_action( 'load-' . self::$hook, [ self::class, 'load' ] );
	}

	public static function load(): void {
		Map::ensure();
		Log::ensure_dir();
	}

	public static function assets( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		$css = HK9_CORE_DIR . 'assets/css/import.css';
		$js  = HK9_CORE_DIR . 'assets/js/import.js';
		wp_enqueue_style( 'hk9-import', HK9_CORE_URL . 'assets/css/import.css', [], is_file( $css ) ? (string) filemtime( $css ) : HK9_CORE_VERSION );
		wp_enqueue_script( 'hk9-import', HK9_CORE_URL . 'assets/js/import.js', [ 'wp-a11y' ], is_file( $js ) ? (string) filemtime( $js ) : HK9_CORE_VERSION, true );

		$config = [
			'root'    => esc_url_raw( rest_url( Rest::NS . '/import/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'status'  => Runner::status(),
			'logBase' => esc_url_raw( self::log_url() ),
			'steps'   => State::STEPS,
			'i18n'    => [
				'idle'         => __( 'Idle', 'heartland-k9s-core' ),
				'running'      => __( 'Running', 'heartland-k9s-core' ),
				'paused'       => __( 'Paused', 'heartland-k9s-core' ),
				'failed'       => __( 'Failed', 'heartland-k9s-core' ),
				'done'         => __( 'Complete', 'heartland-k9s-core' ),
				'dryRun'       => __( 'Dry run', 'heartland-k9s-core' ),
				'import'       => __( 'Import', 'heartland-k9s-core' ),
				'stepOf'       => /* translators: 1: step number, 2: total steps, 3: step name */ __( 'Step %1$d of %2$d — %3$s', 'heartland-k9s-core' ),
				'records'      => /* translators: 1: done, 2: total */ __( '%1$d / %2$d items', 'heartland-k9s-core' ),
				'locked'       => __( 'Another process is running a tick; waiting…', 'heartland-k9s-core' ),
				'confirmReset' => __( 'Reset the import state? The map table and run history are kept, so rollback stays possible.', 'heartland-k9s-core' ),
				'typeRollback' => __( 'Type ROLLBACK to confirm.', 'heartland-k9s-core' ),
				'noErrors'     => __( 'No errors.', 'heartland-k9s-core' ),
				'noRuns'       => __( 'No runs yet.', 'heartland-k9s-core' ),
				'rollbackDone' => /* translators: 1: deleted, 2: restored, 3: skipped */ __( 'Rollback finished: %1$d deleted, %2$d restored, %3$d skipped.', 'heartland-k9s-core' ),
				'error'        => __( 'Error', 'heartland-k9s-core' ),
				'rolledBack'   => __( 'rolled back', 'heartland-k9s-core' ),
				'rollback'     => __( 'Rollback…', 'heartland-k9s-core' ),
				'conflicts'    => __( 'conflicts', 'heartland-k9s-core' ),
				'warnings'     => __( 'Warnings', 'heartland-k9s-core' ),
				'retry'        => __( 'Retry failed', 'heartland-k9s-core' ),
			],
		];
		wp_add_inline_script( 'hk9-import', 'window.HK9Import = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/* -------------------------------------------------------------- render */

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'heartland-k9s-core' ) );
		}
		$notice  = isset( $_GET['hk9_notice'] ) ? sanitize_key( wp_unslash( $_GET['hk9_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['hk9_message'] ) ? sanitize_text_field( wp_unslash( $_GET['hk9_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = Runner::status();
		$payload = $status['payload'];
		$dev     = $status['dev_paths'];
		$kses_ok = current_user_can( 'unfiltered_html' );
		?>
		<div class="wrap hk9-import" id="hk9-import">
			<h1><?php esc_html_e( 'Setup & Import', 'heartland-k9s-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Import the site payload (pages, records, media, menus, settings and redirects). Runs are idempotent: re-running skips what is already in place and preserves edits made on this site unless you choose to overwrite.', 'heartland-k9s-core' ); ?></p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice <?php echo 'error' === $notice ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $kses_ok ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Your account lacks the unfiltered_html capability; imports are refused so block markup is never mangled by kses. Use an administrator account or the WP-CLI command with --user.', 'heartland-k9s-core' ); ?></p></div>
			<?php endif; ?>
			<div id="hk9-import-notice" class="notice" hidden><p></p></div>

			<div class="hk9-import__grid">
				<section class="hk9-import__card" aria-labelledby="hk9-import-payload-h">
					<h2 id="hk9-import-payload-h"><?php esc_html_e( '1. Payload', 'heartland-k9s-core' ); ?></h2>
					<div id="hk9-import-payload" class="hk9-import__payload">
						<?php self::render_payload( $payload ); ?>
					</div>

					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hk9-import__upload">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UP ); ?>" />
						<?php wp_nonce_field( self::NONCE_UP ); ?>
						<label for="hk9-import-file"><?php esc_html_e( 'Upload a payload ZIP', 'heartland-k9s-core' ); ?></label>
						<input type="file" id="hk9-import-file" name="payload" accept=".zip,application/zip" required />
						<p class="description">
							<?php
							printf(
								/* translators: %s: max upload size */
								esc_html__( 'Max upload size on this server: %s. Larger payloads: use WP-CLI (wp hk9 import <dir>).', 'heartland-k9s-core' ),
								esc_html( size_format( wp_max_upload_size() ) )
							);
							?>
						</p>
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Upload & unpack', 'heartland-k9s-core' ); ?></button>
					</form>

					<?php if ( $dev ) : ?>
						<div class="hk9-import__dev">
							<h3><?php esc_html_e( 'Local development: use a server path', 'heartland-k9s-core' ); ?></h3>
							<p class="description"><?php esc_html_e( 'HK9_LOCAL_DEV is defined, so the bind-mounted payload directories below can be imported directly.', 'heartland-k9s-core' ); ?></p>
							<ul>
								<?php foreach ( $dev as $p ) : ?>
									<li><code><?php echo esc_html( $p ); ?></code> <button type="button" class="button button-small hk9-import__use-path" data-path="<?php echo esc_attr( $p ); ?>"><?php esc_html_e( 'Use this path', 'heartland-k9s-core' ); ?></button></li>
								<?php endforeach; ?>
							</ul>
							<input type="hidden" id="hk9-import-path" value="<?php echo esc_attr( $payload['dir'] ?? '' ); ?>" />
						</div>
					<?php else : ?>
						<input type="hidden" id="hk9-import-path" value="" />
					<?php endif; ?>
				</section>

				<section class="hk9-import__card" aria-labelledby="hk9-import-run-h">
					<h2 id="hk9-import-run-h"><?php esc_html_e( '2. Run', 'heartland-k9s-core' ); ?></h2>
					<div class="hk9-import__controls">
						<label><input type="checkbox" id="hk9-import-overwrite" /> <?php esc_html_e( 'Overwrite conflicts (revert edits made on this site to the payload values)', 'heartland-k9s-core' ); ?></label>
						<div class="hk9-import__buttons">
							<button type="button" class="button" id="hk9-import-dry" <?php disabled( ! $kses_ok ); ?>><?php esc_html_e( 'Dry run', 'heartland-k9s-core' ); ?></button>
							<button type="button" class="button button-primary" id="hk9-import-start" <?php disabled( ! $kses_ok ); ?>><?php esc_html_e( 'Import', 'heartland-k9s-core' ); ?></button>
							<button type="button" class="button" id="hk9-import-pause"><?php esc_html_e( 'Pause', 'heartland-k9s-core' ); ?></button>
							<button type="button" class="button" id="hk9-import-resume"><?php esc_html_e( 'Resume', 'heartland-k9s-core' ); ?></button>
							<button type="button" class="button" id="hk9-import-retry"><?php esc_html_e( 'Retry failed', 'heartland-k9s-core' ); ?></button>
							<button type="button" class="button button-link-delete" id="hk9-import-reset"><?php esc_html_e( 'Reset state', 'heartland-k9s-core' ); ?></button>
						</div>
					</div>

					<div class="hk9-import__status" id="hk9-import-status" aria-live="polite">
						<p class="hk9-import__statusline"><span class="hk9-import__badge" data-status="idle"></span> <span class="hk9-import__stepline"></span></p>
						<div class="hk9-import__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="hk9-import__bar"></div></div>
						<p class="hk9-import__meta"></p>
						<p class="hk9-import__log"><a href="<?php echo esc_url( self::log_url() ); ?>" hidden class="button button-small"><?php esc_html_e( 'Download log', 'heartland-k9s-core' ); ?></a></p>
					</div>

					<table class="widefat striped hk9-import__counts" id="hk9-import-counts">
						<caption class="screen-reader-text"><?php esc_html_e( 'Per-step counts', 'heartland-k9s-core' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Step', 'heartland-k9s-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Create', 'heartland-k9s-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Update', 'heartland-k9s-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Skip', 'heartland-k9s-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Conflict', 'heartland-k9s-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Fail', 'heartland-k9s-core' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>

					<h3><?php esc_html_e( 'Errors', 'heartland-k9s-core' ); ?> <span class="hk9-import__count" id="hk9-import-error-count"></span></h3>
					<div id="hk9-import-errors" class="hk9-import__errors"></div>
					<details id="hk9-import-warnings" class="hk9-import__warnings">
						<summary><?php esc_html_e( 'Warnings', 'heartland-k9s-core' ); ?> <span class="hk9-import__count"></span></summary>
						<ul></ul>
					</details>
				</section>

				<section class="hk9-import__card hk9-import__card--wide" aria-labelledby="hk9-import-runs-h">
					<h2 id="hk9-import-runs-h"><?php esc_html_e( '3. Runs & rollback', 'heartland-k9s-core' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Rollback removes only objects created by that run (and restores settings it changed). Records edited since the import are skipped unless you force it; pages, uploads and menus that existed before the run are never deleted.', 'heartland-k9s-core' ); ?></p>
					<div id="hk9-import-runs"></div>
					<h3><?php esc_html_e( 'Import map', 'heartland-k9s-core' ); ?></h3>
					<p id="hk9-import-map" class="description"></p>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_payload( ?array $payload ): void {
		if ( ! $payload ) {
			echo '<p class="hk9-import__empty">' . esc_html__( 'No payload selected yet. Upload a ZIP below or use a server path.', 'heartland-k9s-core' ) . '</p>';
			return;
		}
		if ( empty( $payload['ok'] ) ) {
			echo '<p class="hk9-import__empty">' . esc_html( (string) ( $payload['error'] ?? '' ) ) . '</p>';
			return;
		}
		?>
		<dl class="hk9-import__dl">
			<dt><?php esc_html_e( 'Directory', 'heartland-k9s-core' ); ?></dt><dd><code><?php echo esc_html( (string) $payload['dir'] ); ?></code></dd>
			<dt><?php esc_html_e( 'Source', 'heartland-k9s-core' ); ?></dt><dd><?php echo esc_html( ! empty( $payload['uploaded'] ) ? __( 'Uploaded ZIP (deleted automatically after a clean import)', 'heartland-k9s-core' ) : __( 'Server path', 'heartland-k9s-core' ) ); ?></dd>
			<dt><?php esc_html_e( 'Generated', 'heartland-k9s-core' ); ?></dt><dd><?php echo esc_html( (string) $payload['generated_at'] ); ?></dd>
			<dt><?php esc_html_e( 'Records', 'heartland-k9s-core' ); ?></dt><dd><?php echo esc_html( (string) $payload['records'] ); ?> (<?php echo esc_html( (string) $payload['posts'] ); ?> <?php esc_html_e( 'posts/pages', 'heartland-k9s-core' ); ?>, <?php echo esc_html( (string) $payload['attachments'] ); ?> <?php esc_html_e( 'media', 'heartland-k9s-core' ); ?>, <?php echo esc_html( size_format( (int) $payload['bytes'] ) ); ?>)</dd>
		</dl>
		<?php
	}

	/* ------------------------------------------------------------- upload */

	public static function handle_upload(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'heartland-k9s-core' ), 403 );
		}
		check_admin_referer( self::NONCE_UP );

		// PHP discards $_POST and $_FILES silently when post_max_size is exceeded.
		if ( empty( $_FILES ) && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 0 ) {
			self::back( 'error', sprintf( /* translators: %s: size */ __( 'That upload is larger than this server accepts (%s). Use WP-CLI for large payloads.', 'heartland-k9s-core' ), size_format( wp_max_upload_size() ) ) );
		}
		$file = isset( $_FILES['payload'] ) ? array_map( 'wp_unslash', (array) $_FILES['payload'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$dir  = Payload::unpack_upload( $file );
		if ( is_wp_error( $dir ) ) {
			self::back( 'error', $dir->get_error_message() );
		}
		$info = Payload::describe( $dir );
		if ( empty( $info['ok'] ) ) {
			Payload::remove_uploaded( $dir );
			self::back( 'error', (string) ( $info['error'] ?? __( 'Invalid payload.', 'heartland-k9s-core' ) ) );
		}
		Payload::set_current( $dir, 'upload' );
		self::back( 'success', sprintf( /* translators: 1: records, 2: media count */ __( 'Payload unpacked: %1$d records, %2$d media files. Run a dry run first.', 'heartland-k9s-core' ), (int) $info['records'], (int) $info['attachments'] ) );
	}

	private static function back( string $kind, string $message ): never {
		$url = add_query_arg(
			[
				'page'        => self::SLUG,
				'hk9_notice'  => $kind,
				'hk9_message' => rawurlencode( $message ),
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/* ----------------------------------------------------------------- log */

	/**
	 * Raw (un-entitized) nonce URL for log downloads; JS appends &run=<id>.
	 */
	private static function log_url(): string {
		return add_query_arg(
			[
				'action'   => self::ACTION_LOG,
				'_wpnonce' => wp_create_nonce( self::NONCE_LOG ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public static function handle_log(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'heartland-k9s-core' ), 403 );
		}
		check_admin_referer( self::NONCE_LOG );
		$run  = isset( $_GET['run'] ) ? Log::sanitize_run_id( sanitize_text_field( wp_unslash( $_GET['run'] ) ) ) : '';
		$path = '' !== $run ? Payload::resolve_within( Log::dir(), $run . '.log' ) : null;
		if ( null === $path || ! is_file( $path ) ) {
			wp_die( esc_html__( 'Log not found.', 'heartland-k9s-core' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: attachment; filename="hk9-import-' . rawurlencode( $run ) . '.log"' );
		header( 'X-Content-Type-Options: nosniff' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
