<?php
/**
 * Archives: category, tag, author, date (and any custom taxonomy / post type
 * archive without its own template). Hero band with an eyebrow naming the
 * archive type, the term/author/date as the title (no "Category:" prefix),
 * the description, then the listing; existing empty terms render the styled
 * empty panel with a 200 status.
 *
 * category.php / tag.php / author.php / date.php are intentionally not
 * provided — this file covers all four with hk9_archive_context().
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_context = hk9_archive_context();

get_template_part( 'template-parts/blog/archive-hero', null, $hk9_context );

hk9_blog_listing();

get_footer();
