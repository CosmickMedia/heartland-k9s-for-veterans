<?php
/**
 * Accessible search form (get_search_form()).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_search_id          = wp_unique_id( 'hk9-search-' );
$hk9_search_placeholder = trim( (string) hk9_theme_option( 'blog.search_placeholder' ) );
if ( '' === $hk9_search_placeholder ) {
	$hk9_search_placeholder = __( 'Search…', 'heartland-k9s' );
}
?>
<form role="search" method="get" class="hk9-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $hk9_search_id ); ?>" class="hk9-search__label screen-reader-text"><?php esc_html_e( 'Search this site', 'heartland-k9s' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $hk9_search_id ); ?>" class="hk9-search__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( $hk9_search_placeholder ); ?>" autocomplete="off" required>
	<button type="submit" class="hk9-btn hk9-btn--primary hk9-search__submit">
		<?php echo hk9_icon( 'search', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<span class="hk9-search__submit-label"><?php esc_html_e( 'Search', 'heartland-k9s' ); ?></span>
	</button>
</form>
