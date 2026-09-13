<?php
/**
 * Breadcrumbs: Home › Section › Page (small, muted; the current page unlinked).
 * Rendered by hk9_breadcrumbs(); the structured data (BreadcrumbList) is printed
 * by the plugin from the same trail.
 *
 * @package heartland-k9s
 *
 * @var array $args { trail: array<int, array{name:string,url:string}>, class: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_trail = is_array( $args['trail'] ?? null ) ? array_values( $args['trail'] ) : [];
$hk9_class = trim( (string) ( $args['class'] ?? '' ) );
$hk9_last  = count( $hk9_trail ) - 1;

if ( $hk9_last < 1 ) {
	return;
}
?>
<nav class="hk9-breadcrumbs<?php echo '' !== $hk9_class ? ' ' . esc_attr( $hk9_class ) : ''; ?>" aria-label="<?php esc_attr_e( 'Breadcrumb', 'heartland-k9s' ); ?>">
	<ol class="hk9-breadcrumbs__list">
		<?php foreach ( $hk9_trail as $hk9_i => $hk9_item ) : ?>
			<?php
			$hk9_name = (string) ( $hk9_item['name'] ?? '' );
			$hk9_url  = (string) ( $hk9_item['url'] ?? '' );
			if ( '' === $hk9_name ) {
				continue;
			}
			?>
			<li class="hk9-breadcrumbs__item"<?php echo $hk9_i === $hk9_last ? ' aria-current="page"' : ''; ?>>
				<?php if ( $hk9_i > 0 ) : ?>
					<?php echo hk9_icon( 'chevron-right', [ 'class' => 'hk9-breadcrumbs__sep', 'size' => 14 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
				<?php if ( $hk9_i < $hk9_last && '' !== $hk9_url ) : ?>
					<a class="hk9-breadcrumbs__link" href="<?php echo esc_url( $hk9_url ); ?>"><?php echo esc_html( $hk9_name ); ?></a>
				<?php else : ?>
					<span class="hk9-breadcrumbs__current"><?php echo esc_html( $hk9_name ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
