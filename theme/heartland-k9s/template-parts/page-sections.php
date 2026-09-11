<?php
/**
 * Generic body for a sections template: hero + sections + (landing) block content.
 * Used by front-page.php when a non-home sections template is assigned to the front page.
 *
 * @package heartland-k9s
 *
 * @var array $args { template: string, post_id: int }
 */

defined( 'ABSPATH' ) || exit;

$hk9_template = (string) ( $args['template'] ?? 'default' );
$hk9_page_id  = (int) ( $args['post_id'] ?? get_the_ID() );

hk9_render_sections( $hk9_page_id, $hk9_template );

if ( 'landing' === $hk9_template ) {
	hk9_the_content_card( $hk9_page_id );
}
