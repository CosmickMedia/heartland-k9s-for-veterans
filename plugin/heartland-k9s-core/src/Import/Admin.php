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

	/** Status snapshot for this request (the manifest is parsed once, not per hook). */
	private static ?array $status = null;

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

	private static function status(): array {
		if ( null === self::$status ) {
			self::$status = Runner::status();
		}
		return self::$status;
	}

	/** Pre-flight report for this request (computed once for the config + the server-side panel). */
	private static ?array $preflight = null;

	private static function preflight(): array {
		if ( null === self::$preflight ) {
			self::$preflight = Preflight::run();
		}
		return self::$preflight;
	}

	/** Per-user transient carrying the last upload result (never trusted from the URL). */
	private static function notice_key(): string {
		return 'hk9_import_notice_' . get_current_user_id();
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
			'root'      => esc_url_raw( rest_url( Rest::NS . '/import/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'status'    => self::status(),
			'preflight' => self::preflight(),
			'logBase'   => esc_url_raw( self::log_url() ),
			'steps'     => State::STEPS,
			'i18n'      => [
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
				'overwrite'    => __( 'overwrite', 'heartland-k9s-core' ),
				'adopt'        => __( 'adopt existing', 'heartland-k9s-core' ),
				'run'          => __( 'run', 'heartland-k9s-core' ),
				'preflightOk'  => __( 'All checks passed.', 'heartland-k9s-core' ),
				'preflightBad' => __( 'Fix the failed checks before importing.', 'heartland-k9s-core' ),
				'pass'         => __( 'OK', 'heartland-k9s-core' ),
				'warn'         => __( 'Note', 'heartland-k9s-core' ),
				'failWord'     => __( 'Failed', 'heartland-k9s-core' ),
				'errorsSuffix' => /* translators: %d: number of record errors */ __( '%d error(s)', 'heartland-k9s-core' ),
				'colKey'       => __( 'Key', 'heartland-k9s-core' ),
				'colStep'      => __( 'Step', 'heartland-k9s-core' ),
				'colMessage'   => __( 'Message', 'heartland-k9s-core' ),
				'colRun'       => __( 'Run', 'heartland-k9s-core' ),
				'colStarted'   => __( 'Started', 'heartland-k9s-core' ),
				'colMode'      => __( 'Mode', 'heartland-k9s-core' ),
				'colStatus'    => __( 'Status', 'heartland-k9s-core' ),
				'colErrors'    => __( 'Errors', 'heartland-k9s-core' ),
				'colActions'   => __( 'Actions', 'heartland-k9s-core' ),
				'log'          => __( 'Log', 'heartland-k9s-core' ),
				'directory'    => __( 'Directory', 'heartland-k9s-core' ),
				'source'       => __( 'Source', 'heartland-k9s-core' ),
				'uploadedZip'  => __( 'Uploaded ZIP', 'heartland-k9s-core' ),
				'serverPath'   => __( 'Server path', 'heartland-k9s-core' ),
				'generated'    => __( 'Generated', 'heartland-k9s-core' ),
				'recordsLabel' => __( 'Records', 'heartland-k9s-core' ),
				'postsPages'   => __( 'posts/pages', 'heartland-k9s-core' ),
				'media'        => __( 'media', 'heartland-k9s-core' ),
				'noPayload'    => __( 'No payload selected yet. Upload a ZIP below or use a server path.', 'heartland-k9s-core' ),
				'confirmation' => __( 'Confirmation', 'heartland-k9s-core' ),
				'force'        => __( 'Force (also remove records edited since the import)', 'heartland-k9s-core' ),
				'rollBackRun'  => /* translators: %s: run id */ __( 'Roll back %s', 'heartland-k9s-core' ),
				'cancel'       => __( 'Cancel', 'heartland-k9s-core' ),
				'deleted'      => __( 'deleted', 'heartland-k9s-core' ),
				'restored'     => __( 'restored', 'heartland-k9s-core' ),
				'unadopted'    => __( 'un-adopted (kept)', 'heartland-k9s-core' ),
				'skipped'      => __( 'skipped', 'heartland-k9s-core' ),
				'errorWord'    => __( 'error', 'heartland-k9s-core' ),
			],
		];
		wp_add_inline_script( 'hk9-import', 'window.HK9Import = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/* -------------------------------------------------------------- render */

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'heartland-k9s-core' ) );
		}
		// The upload result travels in a per-user transient (not the URL, so a crafted
		// link cannot place arbitrary text in a notice) and is shown once.
		$notice  = '';
		$message = '';
		$stored  = get_transient( self::notice_key() );
		if ( is_array( $stored ) ) {
			delete_transient( self::notice_key() );
			$notice  = 'error' === ( $stored['kind'] ?? '' ) ? 'error' : 'success';
			$message = (string) ( $stored['message'] ?? '' );
		}
		$status  = self::status();
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
						<input type="hidden" id="hk9-import-path" value="<?php echo esc_attr( $payload['dir'] ?? '' ); ?>" />
					<?php endif; ?>
					<div class="hk9-import__serverpath">
						<h3><?php esc_html_e( 'Large payloads: use a directory on the server', 'heartland-k9s-core' ); ?></h3>
						<p class="description">
							<?php
							printf(
								/* translators: %s: example directory */
								esc_html__( 'Upload the unpacked payload (manifest.json, content/, media/) with SFTP into a folder named hk9-payload-<anything> inside the uploads directory, e.g. %s, then enter that path here. The folder is protected and removed after a successful import.', 'heartland-k9s-core' ),
								'<code>' . esc_html( trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'hk9-payload-2026/' ) . '</code>'
							);
							?>
						</p>
						<p>
							<label for="hk9-import-path-manual" class="screen-reader-text"><?php esc_html_e( 'Payload directory on the server', 'heartland-k9s-core' ); ?></label>
							<input type="text" id="hk9-import-path-manual" class="regular-text code" placeholder="<?php echo esc_attr( trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'hk9-payload-2026' ); ?>" />
							<button type="button" class="button hk9-import__use-manual"><?php esc_html_e( 'Use this path', 'heartland-k9s-core' ); ?></button>
						</p>
					</div>
				</section>

				<section class="hk9-import__card" aria-labelledby="hk9-import-preflight-h">
					<h2 id="hk9-import-preflight-h"><?php esc_html_e( '2. Pre-flight', 'heartland-k9s-core' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Checked before every run. On a site that already holds the old heartlandk9s.org content, the last line tells you how many pages and media files the payload matches; adopt them so nothing is duplicated.', 'heartland-k9s-core' ); ?></p>
					<div id="hk9-import-preflight" class="hk9-import__preflight">
						<?php self::render_preflight( self::preflight() ); ?>
					</div>
				</section>

				<section class="hk9-import__card hk9-import__card--wide" aria-labelledby="hk9-import-run-h">
					<h2 id="hk9-import-run-h"><?php esc_html_e( '3. Run', 'heartland-k9s-core' ); ?></h2>
					<div class="hk9-import__controls">
						<?php $existing = (int) ( self::preflight()['existing']['total'] ?? 0 ); ?>
						<label><input type="checkbox" id="hk9-import-adopt" <?php checked( $existing > 0 ); ?> /> <?php esc_html_e( 'Existing site: adopt matching content', 'heartland-k9s-core' ); ?></label>
						<p class="description hk9-import__adopt-help" id="hk9-import-adopt-help">
							<?php esc_html_e( 'Pages that already exist with the same id or slug are converted in place (same id, same URL, the old builder content is replaced and kept for rollback); the legacy BarKode registry pages become BarKode records with the same slug (their printed QR paths redirect); attachments with the same id and file name are reused as they are (nothing is re-uploaded, only missing image sizes are generated); menus and terms with the same name/slug are reused. Every adoption is listed in the "Adopt" column and in the run log, and a rollback restores what was replaced. Leave it unticked on a fresh site.', 'heartland-k9s-core' ); ?>
						</p>
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
								<th scope="col"><?php esc_html_e( 'Adopt', 'heartland-k9s-core' ); ?></th>
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

					<div id="hk9-import-next" class="hk9-import__next" hidden>
						<h3><?php esc_html_e( 'Next steps', 'heartland-k9s-core' ); ?></h3>
						<ol>
							<li><?php esc_html_e( 'Open the site: check the front page, the primary and footer menus, the BarKode page and one migrated page.', 'heartland-k9s-core' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View site', 'heartland-k9s-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Menus', 'heartland-k9s-core' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Reading settings', 'heartland-k9s-core' ); ?></a></li>
							<li><?php esc_html_e( 'The old page-builder plugins (Avada Builder, Avada Core, FooGallery, FooBox) were deactivated before the import; if one is still active, deactivate it now. Delete them — and the Avada theme — once you are happy with the migrated site.', 'heartland-k9s-core' ); ?> <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugins', 'heartland-k9s-core' ); ?></a></li>
							<li><?php esc_html_e( 'Review the open business decisions listed in docs/unresolved.md (office days, imagery, PayPal, registry privacy scope, form recipients) and set them under Heartland → Settings.', 'heartland-k9s-core' ); ?></li>
							<li id="hk9-import-next-payload"><?php esc_html_e( 'The payload was supplied as a server path and was left in place: delete that folder now (it contains registry data and every original image).', 'heartland-k9s-core' ); ?></li>
						</ol>
					</div>
				</section>

				<section class="hk9-import__card hk9-import__card--wide" aria-labelledby="hk9-import-runs-h">
					<h2 id="hk9-import-runs-h"><?php esc_html_e( '4. Runs & rollback', 'heartland-k9s-core' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Rollback removes only objects created by that run (and restores settings it changed). Records edited since the import are skipped unless you force it; pages, uploads and menus that existed before the run are never deleted.', 'heartland-k9s-core' ); ?></p>
					<div id="hk9-import-runs"></div>
					<h3><?php esc_html_e( 'Import map', 'heartland-k9s-core' ); ?></h3>
					<p id="hk9-import-map" class="description"></p>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Server-side pre-flight panel (the JS re-renders it from the same report shape
	 * when the payload path changes or a run finishes).
	 */
	public static function render_preflight( array $report ): void {
		?>
		<ul class="hk9-import__checks">
			<?php foreach ( (array) $report['checks'] as $c ) : ?>
				<li class="hk9-import__check is-<?php echo esc_attr( (string) $c['status'] ); ?>">
					<span class="hk9-import__check-status"><?php echo esc_html( 'pass' === $c['status'] ? __( 'OK', 'heartland-k9s-core' ) : ( 'warn' === $c['status'] ? __( 'Note', 'heartland-k9s-core' ) : __( 'Failed', 'heartland-k9s-core' ) ) ); ?></span>
					<span class="hk9-import__check-body">
						<strong><?php echo esc_html( (string) $c['label'] ); ?></strong>
						<span class="hk9-import__check-detail"><?php echo esc_html( (string) $c['detail'] ); ?></span>
						<?php if ( ! empty( $c['action']['url'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( (string) $c['action']['url'] ); ?>"><?php echo esc_html( (string) $c['action']['label'] ); ?></a>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="hk9-import__preflight-summary <?php echo ! empty( $report['ok'] ) ? 'is-ok' : 'is-bad'; ?>"><?php echo esc_html( ! empty( $report['ok'] ) ? __( 'All checks passed.', 'heartland-k9s-core' ) : __( 'Fix the failed checks before importing.', 'heartland-k9s-core' ) ); ?></p>
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
		set_transient(
			self::notice_key(),
			[
				'kind'    => $kind,
				'message' => $message,
			],
			2 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG ], admin_url( 'admin.php' ) ) );
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
