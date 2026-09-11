<?php
/**
 * Listing pagination (the_posts_pagination via hk9_pagination()).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( $GLOBALS['wp_query']->max_num_pages <= 1 ) {
	return;
}

hk9_pagination();
