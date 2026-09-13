<?php
/**
 * Title: Stats row (3 numbers)
 * Slug: heartland-k9s/stats-row
 * Categories: heartland-k9s
 * Description: Three large numbers with a short label each, in a row (one column on phones). Use verified figures only.
 * Keywords: stats, numbers, statistics, impact
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_stats = [
	[ '$0', _x( 'Cost to the veteran', 'pattern placeholder', 'heartland-k9s' ) ],
	[ '100%', _x( 'Donor funded', 'pattern placeholder', 'heartland-k9s' ) ],
	[ '501(c)(3)', _x( 'IRS-recognized nonprofit', 'pattern placeholder', 'heartland-k9s' ) ],
];
?>
<!-- wp:columns -->
<div class="wp-block-columns"><?php foreach ( $hk9_stats as $hk9_stat ) : ?><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-hk9-stat","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-hk9-stat"><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php echo esc_html( $hk9_stat[0] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php echo esc_html( $hk9_stat[1] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<?php endforeach; ?></div>
<!-- /wp:columns -->
