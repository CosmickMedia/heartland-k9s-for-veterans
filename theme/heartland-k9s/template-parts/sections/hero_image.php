<?php
/**
 * Section: hero_image (home, program, barkode).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data = is_array( $args['data'] ?? null ) ? $args['data'] : [];

hk9_the_hero( $hk9_data, 'image' );
