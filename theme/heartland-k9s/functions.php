<?php
/**
 * Heartland Canines for Veterans theme bootstrap.
 *
 * Keeps functions.php tiny: every concern lives in inc/.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

define( 'HK9_THEME_VERSION', '1.3.2' );
define( 'HK9_THEME_DIR', get_template_directory() );
define( 'HK9_THEME_URI', get_template_directory_uri() );

foreach ( [ 'defaults', 'options', 'section-defaults', 'setup', 'assets', 'icons', 'template-tags', 'menus', 'sections', 'blocks', 'compat', 'seo', 'plugin-notice', 'template-tags-pages', 'template-tags-records', 'template-tags-blog', 'updater' ] as $hk9_module ) {
	$hk9_file = HK9_THEME_DIR . '/inc/' . $hk9_module . '.php';
	if ( file_exists( $hk9_file ) ) {
		require_once $hk9_file;
	}
}
unset( $hk9_module, $hk9_file );
