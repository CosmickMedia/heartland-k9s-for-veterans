<?php
/**
 * Redirects list table. Only ever loaded from Redirects\Admin::render() after
 * class-wp-list-table.php is required (never reachable from REST/front end).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Redirects;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			[
				'singular' => 'hk9_redirect',
				'plural'   => 'hk9_redirects',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'cb'      => '<input type="checkbox">',
			'source'  => __( 'Source', 'heartland-k9s-core' ),
			'target'  => __( 'Destination', 'heartland-k9s-core' ),
			'status'  => __( 'Status', 'heartland-k9s-core' ),
			'enabled' => __( 'Enabled', 'heartland-k9s-core' ),
			'note'    => __( 'Note', 'heartland-k9s-core' ),
			'updated' => __( 'Updated', 'heartland-k9s-core' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'source'  => [ 'source', true ],
			'status'  => [ 'status', false ],
			'updated' => [ 'updated', false ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'bulk-enable'  => __( 'Enable', 'heartland-k9s-core' ),
			'bulk-disable' => __( 'Disable', 'heartland-k9s-core' ),
			'bulk-delete'  => __( 'Delete (non-seed)', 'heartland-k9s-core' ),
		];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'source' ];
		$rules = Store::all();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$filter = isset( $_REQUEST['hk9_state'] ) ? sanitize_key( wp_unslash( $_REQUEST['hk9_state'] ) ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'source';
		$order   = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'desc' : 'asc';
		// phpcs:enable

		$items = [];
		foreach ( $rules as $key => $rule ) {
			if ( '' !== $search && ! str_contains( strtolower( $key . ' ' . Store::describe_target( $rule['to'] ) . ' ' . $rule['note'] ), strtolower( $search ) ) ) {
				continue;
			}
			if ( 'enabled' === $filter && ! $rule['enabled'] ) {
				continue;
			}
			if ( 'disabled' === $filter && $rule['enabled'] ) {
				continue;
			}
			if ( 'seed' === $filter && ! $rule['seed'] ) {
				continue;
			}
			$items[] = [ 'key' => $key ] + $rule;
		}
		usort(
			$items,
			static function ( array $a, array $b ) use ( $orderby, $order ): int {
				$cmp = match ( $orderby ) {
					'status'  => $a['status'] <=> $b['status'],
					'updated' => $a['updated'] <=> $b['updated'],
					default   => strcmp( $a['key'], $b['key'] ),
				};
				return 'desc' === $order ? -$cmp : $cmp;
			}
		);

		$per_page = 50;
		$page     = $this->get_pagenum();
		$total    = count( $items );
		$this->items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$filter = isset( $_REQUEST['hk9_state'] ) ? sanitize_key( wp_unslash( $_REQUEST['hk9_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="hk9-state"><?php esc_html_e( 'Filter by state', 'heartland-k9s-core' ); ?></label>
			<select name="hk9_state" id="hk9-state">
				<option value=""><?php esc_html_e( 'All rules', 'heartland-k9s-core' ); ?></option>
				<option value="enabled"<?php selected( $filter, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'heartland-k9s-core' ); ?></option>
				<option value="disabled"<?php selected( $filter, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'heartland-k9s-core' ); ?></option>
				<option value="seed"<?php selected( $filter, 'seed' ); ?>><?php esc_html_e( 'Seeded (migration)', 'heartland-k9s-core' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'heartland-k9s-core' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function no_items(): void {
		esc_html_e( 'No redirects yet.', 'heartland-k9s-core' );
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="keys[]" value="%s" aria-label="%s">', esc_attr( $item['key'] ), esc_attr( $item['key'] ) );
	}

	protected function column_source( array $item ): string {
		$base    = Admin::url();
		$actions = [
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( [ 'action' => 'edit', 'key' => $item['key'] ], $base ) ), esc_html__( 'Edit', 'heartland-k9s-core' ) ),
			'test' => sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'test', 'key' => $item['key'] ], $base ), 'hk9_redirect_test_' . $item['key'] ) ), esc_html__( 'Test', 'heartland-k9s-core' ) ),
		];
		if ( $item['enabled'] ) {
			$actions['disable'] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'disable', 'key' => $item['key'] ], $base ), 'hk9_redirect_disable_' . $item['key'] ) ), esc_html__( 'Disable', 'heartland-k9s-core' ) );
		} else {
			$actions['enable'] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'enable', 'key' => $item['key'] ], $base ), 'hk9_redirect_enable_' . $item['key'] ) ), esc_html__( 'Enable', 'heartland-k9s-core' ) );
		}
		if ( ! $item['seed'] ) {
			$actions['delete'] = sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'key' => $item['key'] ], $base ), 'hk9_redirect_delete_' . $item['key'] ) ), esc_html__( 'Delete', 'heartland-k9s-core' ) );
		}
		$label = '<strong><a href="' . esc_url( add_query_arg( [ 'action' => 'edit', 'key' => $item['key'] ], $base ) ) . '"><code>' . esc_html( $item['key'] ) . '</code></a></strong>';
		if ( $item['seed'] ) {
			$label .= ' <span class="hk9-pill hk9-pill--info">' . esc_html__( 'seed', 'heartland-k9s-core' ) . '</span>';
		}
		return $label . $this->row_actions( $actions );
	}

	protected function column_target( array $item ): string {
		if ( 410 === $item['status'] ) {
			return '<span class="hk9-muted">' . esc_html__( '(gone)', 'heartland-k9s-core' ) . '</span>';
		}
		$resolved = Store::resolve_target( $item['to'] );
		$desc     = Store::describe_target( $item['to'] );
		$out      = esc_html( $desc );
		if ( null !== $resolved ) {
			$out .= '<br><a href="' . esc_url( $resolved ) . '" target="_blank" rel="noopener"><code>' . esc_html( $resolved ) . '</code></a>';
		} else {
			$out .= '<br><span class="hk9-pill hk9-pill--bad">' . esc_html__( 'does not resolve', 'heartland-k9s-core' ) . '</span>';
		}
		return $out;
	}

	protected function column_status( array $item ): string {
		return '<code>' . esc_html( (string) $item['status'] ) . '</code>';
	}

	protected function column_enabled( array $item ): string {
		return $item['enabled']
			? '<span class="hk9-pill hk9-pill--good">' . esc_html__( 'On', 'heartland-k9s-core' ) . '</span>'
			: '<span class="hk9-pill">' . esc_html__( 'Off', 'heartland-k9s-core' ) . '</span>';
	}

	protected function column_note( array $item ): string {
		return esc_html( $item['note'] );
	}

	protected function column_updated( array $item ): string {
		if ( $item['updated'] <= 0 ) {
			return '<span class="hk9-muted">—</span>';
		}
		$out = esc_html( wp_date( (string) get_option( 'date_format' ), $item['updated'] ) );
		if ( $item['by'] > 0 ) {
			$user = get_userdata( $item['by'] );
			if ( $user ) {
				$out .= '<br><span class="hk9-muted">' . esc_html( $user->display_name ) . '</span>';
			}
		}
		return $out;
	}

	protected function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) && is_scalar( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}
}
