<?php
/**
 * LOCAL-ONLY blog fixtures for testing the blog templates, archives, pagination,
 * block styles and empty/edge states. Never part of the payload. Created via
 * `tools/wp.sh hk9-dev fixtures create` and removed with `... fixtures delete`.
 *
 * Every created object carries the marker meta so deletion is exact.
 */

defined( 'ABSPATH' ) || exit;

function hk9_dev_create_blog_fixtures( string $marker ): string {
	$existing = get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => $marker ] );
	if ( $existing ) {
		return 'Fixtures already exist (run "fixtures delete" first).';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$cats = [];
	foreach ( [ 'Program Updates', 'Events', 'Community' ] as $name ) {
		$t = wp_insert_term( $name, 'category' );
		$id = is_wp_error( $t ) ? (int) ( $t->error_data['term_exists'] ?? 0 ) : (int) $t['term_id'];
		if ( $id ) { add_term_meta( $id, $marker, 1, true ); $cats[] = $id; }
	}
	$tags = [];
	foreach ( [ 'service dogs', 'training', 'fundraising', 'veterans day', 'poker run' ] as $name ) {
		$t = wp_insert_term( $name, 'post_tag' );
		$id = is_wp_error( $t ) ? (int) ( $t->error_data['term_exists'] ?? 0 ) : (int) $t['term_id'];
		if ( $id ) { add_term_meta( $id, $marker, 1, true ); $tags[] = $id; }
	}
	// An existing tag with no posts (empty archive state; the live site has such tags, e.g. poker-run).
	$empty_tag = wp_insert_term( 'fixture empty tag', 'post_tag' );
	$empty_tag = is_wp_error( $empty_tag ) ? (int) ( $empty_tag->error_data['term_exists'] ?? 0 ) : (int) $empty_tag['term_id'];
	if ( $empty_tag ) { add_term_meta( $empty_tag, $marker, 1, true ); }

	// Second author for author archives.
	$author2 = username_exists( 'hk9_fixture_author' ) ?: wp_insert_user( [ 'user_login' => 'hk9_fixture_author', 'user_pass' => wp_generate_password( 24 ), 'display_name' => 'Fixture Author', 'role' => 'author', 'user_email' => 'fixture-author@hk9.test' ] );
	if ( is_wp_error( $author2 ) ) { $author2 = 1; }
	if ( $author2 !== 1 ) { update_user_meta( $author2, $marker, 1 ); }

	// Generated images (GD) — no real photos, clearly synthetic.
	$images = [];
	for ( $i = 1; $i <= 4; $i++ ) {
		$w = 1600; $h = 1000;
		$im = imagecreatetruecolor( $w, $h );
		for ( $y = 0; $y < $h; $y += 4 ) {
			$c = imagecolorallocate( $im, 28 + (int) ( $y / $h * 60 ), 47 + $i * 20, 74 + (int) ( $y / $h * 100 ) );
			imagefilledrectangle( $im, 0, $y, $w, $y + 4, $c );
		}
		$white = imagecolorallocate( $im, 255, 255, 255 );
		imagestring( $im, 5, 40, 40, "HK9 LOCAL FIXTURE IMAGE $i", $white );
		$tmp = wp_tempnam( "hk9-fixture-$i.jpg" );
		imagejpeg( $im, $tmp, 82 );
		imagedestroy( $im );
		$aid = media_handle_sideload( [ 'name' => "hk9-fixture-$i.jpg", 'tmp_name' => $tmp, 'type' => 'image/jpeg', 'size' => filesize( $tmp ) ], 0, "Fixture image $i", [ 'meta_input' => [ $marker => 1 ] ] );
		if ( ! is_wp_error( $aid ) ) { update_post_meta( $aid, '_wp_attachment_image_alt', 'Synthetic fixture image ' . $i ); $images[] = $aid; }
	}
	$img = fn( int $n ) => $images[ $n % max( 1, count( $images ) ) ] ?? 0;

	$lorem = '<!-- wp:paragraph --><p>This is a <strong>local fixture</strong> article used only to test the blog templates. It is never part of the production import. Paragraphs should read comfortably at 18px with a 65–75 character measure, and links like <a href="/about/">this one</a> should be clearly distinguishable.</p><!-- /wp:paragraph -->';
	$rich = $lorem
		. '<!-- wp:heading --><h2 class="wp-block-heading">A second-level heading</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>Body copy after a heading. Quisque euismod, urna eu tincidunt consectetur, nisi nisl aliquam nunc, eget aliquam nisl nunc eu nisl.</p><!-- /wp:paragraph -->'
		. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">A third-level heading</h3><!-- /wp:heading -->'
		. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>First list item</li><!-- /wp:list-item --><!-- wp:list-item --><li>Second list item with a <a href="/program/">link</a></li><!-- /wp:list-item --><!-- wp:list-item --><li>Third item<ul class="wp-block-list"><li>Nested item</li></ul></li><!-- /wp:list-item --></ul><!-- /wp:list -->'
		. '<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>Ordered one</li><!-- /wp:list-item --><!-- wp:list-item --><li>Ordered two</li><!-- /wp:list-item --></ol><!-- /wp:list -->'
		. '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A pull quote inside the article, styled in Fraunces like the reference testimonials.</p><!-- /wp:paragraph --><cite>Fixture citation</cite></blockquote><!-- /wp:quote -->'
		. '<!-- wp:image {"id":' . $img( 1 ) . ',"sizeSlug":"large","align":"wide"} --><figure class="wp-block-image alignwide size-large"><img src="' . esc_url( wp_get_attachment_image_url( $img( 1 ), 'large' ) ) . '" alt="Synthetic fixture image" class="wp-image-' . $img( 1 ) . '"/><figcaption class="wp-element-caption">A wide-aligned image with a caption.</figcaption></figure><!-- /wp:image -->'
		. '<!-- wp:pullquote --><figure class="wp-block-pullquote"><blockquote><p>A pullquote block: short, centred, ruled above and below.</p><cite>Pullquote citation</cite></blockquote></figure><!-- /wp:pullquote -->'
		. '<!-- wp:image {"id":' . $img( 2 ) . ',"sizeSlug":"full","align":"full"} --><figure class="wp-block-image alignfull size-full"><img src="' . esc_url( wp_get_attachment_image_url( $img( 2 ), 'full' ) ) . '" alt="Synthetic fixture image" class="wp-image-' . $img( 2 ) . '"/><figcaption class="wp-element-caption">A full-width image with a caption.</figcaption></figure><!-- /wp:image -->'
		. '<!-- wp:table --><figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Column A</th><th>Column B</th><th>Column C</th></tr></thead><tbody><tr><td>Row 1</td><td>Value</td><td>Value</td></tr><tr><td>Row 2</td><td>Value</td><td>Value</td></tr></tbody></table><figcaption class="wp-element-caption">A table caption.</figcaption></figure><!-- /wp:table -->'
		. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/donate/">Primary button</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/contact/">Outline button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '<!-- wp:code --><pre class="wp-block-code"><code>echo "code block";</code></pre><!-- /wp:code -->'
		. '<!-- wp:gallery {"columns":3,"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped">'
		. implode( '', array_map( fn( $a ) => '<!-- wp:image {"id":' . $a . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . esc_url( wp_get_attachment_image_url( $a, 'large' ) ) . '" alt="Synthetic fixture image" class="wp-image-' . $a . '"/></figure><!-- /wp:image -->', $images ) )
		. '</figure><!-- /wp:gallery -->'
		. '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">' . "\n" . 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' . "\n" . '</div><figcaption class="wp-element-caption">An embed (local fixture only).</figcaption></figure><!-- /wp:embed -->'
		. '<!-- wp:group {"align":"full","backgroundColor":"muted","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull has-muted-background-color has-background"><!-- wp:paragraph --><p>A full-width group block with a muted background.</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
		. '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->'
		. '<!-- wp:paragraph --><p>Closing paragraph.</p><!-- /wp:paragraph -->';

	$titles = [
		'Fixture: a short title', 'Fixture: an extraordinarily long headline that keeps going to test wrapping across two or even three lines on narrow screens without breaking the card layout', 'Fixture: no featured image', 'Fixture: rich blocks', 'Fixture: password protected', 'Fixture: paginated article', 'Fixture: with comments', 'Fixture: category two', 'Fixture: category three', 'Fixture: tags galore', 'Fixture: second author', 'Fixture: eleven', 'Fixture: twelve', 'Fixture: thirteen',
	];
	$ids = [];
	foreach ( $titles as $i => $title ) {
		$content = 3 === $i % 4 ? $rich : $lorem . $lorem;
		if ( 5 === $i ) { $content = $lorem . '<!--nextpage-->' . $lorem . '<!--nextpage-->' . $lorem; }
		$args = [
			'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content,
			'post_excerpt' => 0 === $i % 3 ? 'A hand-written excerpt for fixture ' . ( $i + 1 ) . '.' : '',
			'post_date' => gmdate( 'Y-m-d H:i:s', time() - ( $i * 3 + 1 ) * DAY_IN_SECONDS ),
			'post_author' => 10 === $i ? $author2 : 1,
			'post_password' => 4 === $i ? 'fixture' : '',
			'post_category' => [ $cats[ $i % 3 ] ],
			'tags_input' => [], 'meta_input' => [ $marker => 1 ],
			'comment_status' => 'open',
		];
		$id = wp_insert_post( $args );
		if ( is_wp_error( $id ) ) { continue; }
		$ids[] = $id;
		wp_set_object_terms( $id, 9 === $i ? $tags : [ $tags[ $i % count( $tags ) ] ], 'post_tag' );
		if ( 2 !== $i ) { set_post_thumbnail( $id, $img( $i ) ); }
		if ( 13 === $i ) { stick_post( $id ); }
		if ( 6 === $i ) {
			for ( $c = 1; $c <= 3; $c++ ) {
				$cid = wp_insert_comment( [ 'comment_post_ID' => $id, 'comment_author' => "Fixture commenter $c", 'comment_author_email' => "c$c@hk9.test", 'comment_content' => "Fixture comment number $c with a thoughtful remark.", 'comment_approved' => 1 ] );
				if ( $cid ) { add_comment_meta( $cid, $marker, 1, true ); wp_insert_comment( [ 'comment_post_ID' => $id, 'comment_parent' => $cid, 'comment_author' => 'Reply author', 'comment_author_email' => 'reply@hk9.test', 'comment_content' => 'A threaded reply.', 'comment_approved' => 1 ] ); }
			}
		}
	}
	return sprintf( 'Created %d posts (last one sticky), %d categories, %d tags (+1 empty), %d images, author #%d.', count( $ids ), count( $cats ), count( $tags ), count( $images ), $author2 );
}
