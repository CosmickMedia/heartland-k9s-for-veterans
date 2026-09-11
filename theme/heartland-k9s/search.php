<?php
/**
 * Search results: hero band with the highlighted query, result count and the
 * search form; result cards with highlighted matches; empty state with
 * suggestions, helpful links and the form.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_context           = hk9_archive_context();
$hk9_context['search'] = true;

get_template_part( 'template-parts/blog/archive-hero', null, $hk9_context );

hk9_blog_listing( [ 'highlight' => get_search_query( false ) ] );

get_footer();
