<?php
/**
 * Plugin Name: Heartland K9s Core
 * Plugin URI: https://heartlandk9s.org/
 * Description: Content models, admin controls, forms, redirects and the content importer for the Heartland Canines for Veterans website. Companion to the "Heartland Canines for Veterans" theme.
 * Version: 1.3.2
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Heartland Canines for Veterans
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: heartland-k9s-core
 *
 * @package HK9\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'HK9_CORE_VERSION', '1.3.2' );
define( 'HK9_CORE_FILE', __FILE__ );
define( 'HK9_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'HK9_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once HK9_CORE_DIR . 'src/Autoloader.php';
HK9\Core\Autoloader::register( HK9_CORE_DIR . 'src' );

register_activation_hook( __FILE__, [ HK9\Core\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ HK9\Core\Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ HK9\Core\Plugin::class, 'boot' ], 5 );
