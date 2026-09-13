<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * Reference route "/contact": hero band followed by ONE overlapping card that
 * holds the `info` column (2/5, muted) and the `form` column (3/5). Because the
 * two columns are separate sections in the "Page sections" panel (each can be
 * hidden/reordered) the template renders the hero through the section pipeline
 * and wraps every remaining visible section inside the shared card.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id  = get_the_ID();
	$hk9_template = 'contact';
	$hk9_sections = [];

	foreach ( hk9_sections_layout( $hk9_page_id, $hk9_template ) as $hk9_section_id ) {
		$hk9_type = hk9_section_type( $hk9_template, $hk9_section_id );
		if ( ! locate_template( 'template-parts/sections/' . $hk9_type . '.php' ) ) {
			continue;
		}
		$hk9_sections[] = [
			'id'   => $hk9_section_id,
			'type' => $hk9_type,
			'data' => hk9_pages_section( $hk9_page_id, $hk9_template, $hk9_section_id ),
		];
	}

	/** This filter is documented in inc/sections.php */
	$hk9_sections = apply_filters( 'hk9/theme/render_sections', $hk9_sections, $hk9_page_id, $hk9_template );

	$hk9_heroes  = array_values( array_filter( $hk9_sections, static fn( array $s ): bool => in_array( $s['type'], [ 'hero_band', 'hero_image' ], true ) ) );
	$hk9_columns = array_values( array_filter( $hk9_sections, static fn( array $s ): bool => ! in_array( $s['type'], [ 'hero_band', 'hero_image' ], true ) ) );

	// Editor content (block canvas): 'before' = overlap card right after the hero (the contact card then follows), 'after' = after the card.
	$hk9_content_position = hk9_editor_content_position( $hk9_page_id, $hk9_template );

	foreach ( $hk9_heroes as $hk9_section ) {
		get_template_part(
			'template-parts/sections/' . $hk9_section['type'],
			null,
			[
				'data'     => $hk9_section['data'],
				'post_id'  => $hk9_page_id,
				'id'       => $hk9_section['id'],
				'template' => $hk9_template,
			]
		);
	}

	if ( 'before' === $hk9_content_position ) {
		hk9_the_editor_content( $hk9_page_id, 'before' );
	}

	if ( ! empty( $hk9_columns ) ) :
		hk9_section_open( 'contact-card', 'hk9-section--plain hk9-overlap' );
		?>
		<div class="hk9-overlap__card hk9-overlap__card--split hk9-contact-card<?php echo 1 === count( $hk9_columns ) ? ' hk9-contact-card--single' : ''; ?>">
			<?php
			foreach ( $hk9_columns as $hk9_section ) {
				get_template_part(
					'template-parts/sections/' . $hk9_section['type'],
					null,
					[
						'data'     => $hk9_section['data'],
						'post_id'  => $hk9_page_id,
						'id'       => $hk9_section['id'],
						'template' => $hk9_template,
					]
				);
			}
			?>
		</div>
		<?php
		hk9_section_close();
	endif;

	if ( 'after' === $hk9_content_position ) {
		hk9_the_editor_content( $hk9_page_id, 'after' );
	}
endwhile;

get_footer();
