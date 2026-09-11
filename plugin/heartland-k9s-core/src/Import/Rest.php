<?php
/**
 * REST endpoints for the admin import screen: hk9/v1/import/{status,start,step,pause,resume,retry,rollback,reset}.
 *
 * Every route requires manage_options + hk9_run_import (granted to admins) and
 * the wp_rest nonce (cookie auth). Also registers the WP-CLI commands when
 * running under WP-CLI so the importer is usable even if no central CLI
 * bootstrap exists (registration is idempotent).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public const NS = 'hk9/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( class_exists( '\HK9\Core\CLI\ImportCommand' ) ) {
				\HK9\Core\CLI\ImportCommand::register();
			}
			if ( class_exists( '\HK9\Core\CLI\StatusCommand' ) ) {
				\HK9\Core\CLI\StatusCommand::register();
			}
		}
	}

	public static function can(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'hk9_forbidden', __( 'You are not allowed to run imports.', 'heartland-k9s-core' ), [ 'status' => is_user_logged_in() ? 403 : 401 ] );
		}
		return true;
	}

	public static function routes(): void {
		$perm = [ self::class, 'can' ];

		register_rest_route(
			self::NS,
			'/import/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'status' ],
				'permission_callback' => $perm,
			]
		);

		register_rest_route(
			self::NS,
			'/import/start',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'start' ],
				'permission_callback' => $perm,
				'args'                => [
					'path'      => [
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => is_string( $v ) ? trim( $v ) : '',
					],
					'dry_run'   => [
						'type'    => 'boolean',
						'default' => false,
					],
					'overwrite' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'resume'    => [
						'type'    => 'boolean',
						'default' => false,
					],
					'batch'     => [
						'type'              => 'integer',
						'default'           => 25,
						'sanitize_callback' => 'absint',
					],
					'budget'    => [
						'type'              => 'integer',
						'default'           => 15,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		foreach ( [ 'step', 'pause', 'resume', 'retry', 'reset' ] as $action ) {
			register_rest_route(
				self::NS,
				'/import/' . $action,
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ self::class, $action ],
					'permission_callback' => $perm,
				]
			);
		}

		register_rest_route(
			self::NS,
			'/import/rollback',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'rollback' ],
				'permission_callback' => $perm,
				'args'                => [
					'run'     => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $v ) => Log::sanitize_run_id( (string) $v ),
					],
					'confirm' => [
						'type'    => 'string',
						'default' => '',
					],
					'force'   => [
						'type'    => 'boolean',
						'default' => false,
					],
					'dry_run' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);
	}

	/* ----------------------------------------------------------- callbacks */

	public static function status(): WP_REST_Response {
		return new WP_REST_Response( Runner::status() );
	}

	public static function start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$unfiltered = self::require_unfiltered_html();
		if ( is_wp_error( $unfiltered ) ) {
			return $unfiltered;
		}
		$mode = [
			'dry_run'   => (bool) $request['dry_run'],
			'overwrite' => (bool) $request['overwrite'],
			'batch'     => (int) $request['batch'],
			'budget'    => (int) $request['budget'],
		];
		if ( $request['resume'] ) {
			$state = Runner::resume( [ 'overwrite' => $mode['overwrite'], 'dry_run' => $mode['dry_run'] ] );
		} else {
			$path = (string) $request['path'];
			if ( '' === $path ) {
				$current = Payload::current();
				$path    = $current ? (string) $current['dir'] : '';
			}
			$dir = Payload::validate_admin_path( $path );
			if ( is_wp_error( $dir ) ) {
				return $dir;
			}
			$state = Runner::start( $dir, $mode, get_current_user_id(), Payload::is_uploaded_dir( $dir ) ? 'upload' : 'path' );
		}
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return new WP_REST_Response( Runner::status() );
	}

	public static function step(): WP_REST_Response|WP_Error {
		$unfiltered = self::require_unfiltered_html();
		if ( is_wp_error( $unfiltered ) ) {
			return $unfiltered;
		}
		// Run one tick; 423 when another tick holds the lock (the UI just polls again).
		$state = Runner::step();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return new WP_REST_Response( Runner::status() );
	}

	public static function pause(): WP_REST_Response {
		Runner::pause();
		return new WP_REST_Response( Runner::status() );
	}

	public static function resume(): WP_REST_Response|WP_Error {
		$state = Runner::resume();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return new WP_REST_Response( Runner::status() );
	}

	public static function retry(): WP_REST_Response|WP_Error {
		$state = Runner::retry();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return new WP_REST_Response( Runner::status() );
	}

	public static function reset(): WP_REST_Response {
		Runner::reset();
		return new WP_REST_Response( Runner::status() );
	}

	public static function rollback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $request['dry_run'] && 'ROLLBACK' !== (string) $request['confirm'] ) {
			return new WP_Error( 'hk9_rollback_confirm', __( 'Type ROLLBACK to confirm.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		$report = Rollback::run( (string) $request['run'], (bool) $request['force'], (bool) $request['dry_run'] );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		return new WP_REST_Response(
			[
				'report' => $report,
				'status' => Runner::status(),
			]
		);
	}

	/**
	 * Block content is written verbatim only for users with unfiltered_html;
	 * kses would otherwise strip block attributes. Filterable for hosts that
	 * define DISALLOW_UNFILTERED_HTML on purpose.
	 */
	public static function require_unfiltered_html(): true|WP_Error {
		/**
		 * Filters whether the importer insists on the unfiltered_html capability.
		 *
		 * @param bool $required Default true.
		 */
		if ( ! apply_filters( 'hk9/import/require_unfiltered_html', true ) ) {
			return true;
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'hk9_import_kses', __( 'The importing user needs the unfiltered_html capability (an administrator without DISALLOW_UNFILTERED_HTML) so block markup is stored verbatim.', 'heartland-k9s-core' ), [ 'status' => 403 ] );
		}
		return true;
	}
}
