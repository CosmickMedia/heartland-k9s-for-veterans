<?php
/**
 * Posts page (/news/): hero band from the Blog settings (title/text/image, or
 * the posts page's own title/excerpt/thumbnail), then the list or grid of
 * post cards (sticky posts first) with pagination.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_context = hk9_archive_context();

get_template_part( 'template-parts/blog/archive-hero', null, $hk9_context );

hk9_blog_listing();

get_footer();
