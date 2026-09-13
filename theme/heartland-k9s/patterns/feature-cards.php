<?php
/**
 * Title: Three feature cards
 * Slug: heartland-k9s/feature-cards
 * Categories: heartland-k9s
 * Description: Three white cards side by side (title, text, link), like the Home page feature cards; one column on phones.
 * Keywords: cards, columns, features, three
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_cards = [
	[
		'title' => _x( 'Zero cost to veterans', 'pattern placeholder', 'heartland-k9s' ),
		'text'  => _x( 'Describe the first benefit or service in a sentence or two. Keep each card about the same length so the row lines up.', 'pattern placeholder', 'heartland-k9s' ),
	],
	[
		'title' => _x( 'An unbreakable bond', 'pattern placeholder', 'heartland-k9s' ),
		'text'  => _x( 'Describe the second benefit or service in a sentence or two. Keep each card about the same length so the row lines up.', 'pattern placeholder', 'heartland-k9s' ),
	],
	[
		'title' => _x( 'Nationwide reach', 'pattern placeholder', 'heartland-k9s' ),
		'text'  => _x( 'Describe the third benefit or service in a sentence or two. Keep each card about the same length so the row lines up.', 'pattern placeholder', 'heartland-k9s' ),
	],
];
?>
<!-- wp:columns -->
<div class="wp-block-columns"><?php foreach ( $hk9_cards as $hk9_card ) : ?><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"is-style-hk9-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-hk9-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $hk9_card['title'] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html( $hk9_card['text'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#"><?php echo esc_html_x( 'Learn more', 'pattern placeholder', 'heartland-k9s' ); ?> →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<?php endforeach; ?></div>
<!-- /wp:columns -->
