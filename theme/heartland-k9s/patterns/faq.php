<?php
/**
 * Title: FAQ (details)
 * Slug: heartland-k9s/faq
 * Categories: heartland-k9s
 * Description: A heading and three expandable question/answer panels (core Details blocks). Duplicate a panel for more questions.
 * Keywords: faq, questions, accordion, details
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_items = [
	[ _x( 'How much does a service dog cost a veteran?', 'pattern placeholder', 'heartland-k9s' ), _x( 'Nothing. Write the answer here — a short paragraph is ideal; you can add lists, links or images inside the panel.', 'pattern placeholder', 'heartland-k9s' ) ],
	[ _x( 'Who is eligible?', 'pattern placeholder', 'heartland-k9s' ), _x( 'Write the answer here. Each panel opens when the question is clicked.', 'pattern placeholder', 'heartland-k9s' ) ],
	[ _x( 'How long does the process take?', 'pattern placeholder', 'heartland-k9s' ), _x( 'Write the answer here. To add another question, select a panel and choose Duplicate from its toolbar.', 'pattern placeholder', 'heartland-k9s' ) ],
];
?>
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Frequently asked questions', 'pattern placeholder', 'heartland-k9s' ); ?></h2>
<!-- /wp:heading -->

<?php foreach ( $hk9_items as $hk9_item ) : ?>
<!-- wp:details -->
<details class="wp-block-details"><summary><?php echo esc_html( $hk9_item[0] ); ?></summary><!-- wp:paragraph -->
<p><?php echo esc_html( $hk9_item[1] ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<?php endforeach; ?>
