<?php
/**
 * Generic fallback template (anything without a more specific template):
 * listing hero + post cards, or the empty panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_context = hk9_archive_context();

get_template_part( 'template-parts/blog/archive-hero', null, $hk9_context );

hk9_blog_listing();

get_footer();
