<?php
/**
 * Outcome of processing a form submission (shared by admin-post and REST).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Result {

	/**
	 * @param bool                  $ok            Whether the submission was accepted.
	 * @param int                   $status        HTTP status for the REST response.
	 * @param string                $code          Machine-readable outcome code.
	 * @param string                $message       Human-readable summary (never contains submitted data).
	 * @param array<string,string>  $errors        Field key => error message.
	 * @param array<string,mixed>   $values        Sanitized submitted values (for re-rendering only; never sent to the client as JSON).
	 * @param string                $redirect      Where the browser should go next ('' = stay).
	 * @param string                $success_mode  'inline' | 'redirect'.
	 * @param string                $key           Random key for the result transient / query string (no PII).
	 * @param array{token:string,ts:string}|null $refresh Fresh anti-spam tokens for a JS retry, when appropriate.
	 * @param int                   $submission_id Stored submission id (0 = not stored).
	 */
	public function __construct(
		public readonly bool $ok,
		public readonly int $status,
		public readonly string $code,
		public readonly string $message,
		public readonly array $errors = [],
		public readonly array $values = [],
		public readonly string $redirect = '',
		public readonly string $success_mode = 'inline',
		public readonly string $key = '',
		public readonly ?array $refresh = null,
		public readonly int $submission_id = 0,
	) {}

	/** Client-safe JSON payload (`{ok, errors, message, redirect}` plus optional token refresh). */
	public function to_array(): array {
		$data = [
			'ok'       => $this->ok,
			'errors'   => (object) $this->errors,
			'message'  => $this->message,
			'redirect' => '' !== $this->redirect ? $this->redirect : null,
			'mode'     => $this->success_mode,
			'code'     => $this->code,
		];
		if ( null !== $this->refresh ) {
			$data['refresh'] = $this->refresh;
		}
		return $data;
	}
}
