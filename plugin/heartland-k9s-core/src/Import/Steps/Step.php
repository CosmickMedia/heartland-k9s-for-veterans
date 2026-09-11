<?php
/**
 * Base class for cursor-resumable import steps.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Manifest;

defined( 'ABSPATH' ) || exit;

abstract class Step {

	public const NAME = '';

	protected Context $ctx;

	public function __construct( Context $ctx ) {
		$this->ctx       = $ctx;
		$this->ctx->step = static::NAME;
	}

	/**
	 * Ordered work items (usually record keys). Must be stable across ticks.
	 *
	 * @return string[]
	 */
	abstract protected function items(): array;

	abstract protected function process( string $item ): void;

	public function total(): int {
		return count( $this->items() );
	}

	/**
	 * Process items from the saved cursor; returns true when the step completed.
	 */
	public function run(): bool {
		$items = $this->items();
		$n     = count( $items );

		$this->ctx->state['step_total'] = $n;
		$cursor                         = (int) $this->ctx->state['cursor'];

		while ( $cursor < $n ) {
			if ( $this->ctx->should_yield() ) {
				$this->ctx->state['cursor'] = $cursor;
				return false;
			}
			$this->process( $items[ $cursor ] );
			++$cursor;
			$this->ctx->tick();
			$this->ctx->state['cursor'] = $cursor;
		}

		$this->finish();
		return true;
	}

	/**
	 * Hook run once when the last item is done.
	 */
	protected function finish(): void {}

	protected function manifest(): Manifest {
		return $this->ctx->manifest;
	}

	protected function record( string $key ): ?array {
		return $this->ctx->manifest->get( $key );
	}

	protected function skip_failed( string $key, array $record ): bool {
		if ( $this->ctx->is_failed( $key ) ) {
			$this->ctx->info( $key, 'skipped: failed earlier in this pass', Context::is_sensitive( $record ) );
			return true;
		}
		return false;
	}
}
