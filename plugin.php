<?php
/**
 * Compatibility
 *
 * @package           kagg/compatibility
 * @author            KAGG Design
 * @license           GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:       KAGG Compatibility
 * Plugin URI:        https://wordpress.org/plugins/kagg-compatibility/
 * Description:       Blocks PHP 8.1 deprecation messages from WordPress core, WooCommerce, Jetpack and other plugins.
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      8.1
 * Author:            KAGG Design
 * Author URI:        https://profiles.wordpress.org/kaggdesign/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kagg-compatibility
 * Domain Path:       /languages/
 */

namespace KAGG\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

if ( defined( 'KAGG_COMPATIBILITY_VERSION' ) ) {
	return;
}

/**
 * Plugin version.
 */
define( 'KAGG_COMPATIBILITY_VERSION', '1.1.0' );

/**
 * Path to the plugin dir.
 */
define( 'KAGG_COMPATIBILITY_PATH', __DIR__ );

/**
 * Plugin dir url.
 */
define( 'KAGG_COMPATIBILITY_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 */
define( 'KAGG_COMPATIBILITY_FILE', __FILE__ );

/**
 * Init plugin on plugin load.
 */
require_once constant( 'KAGG_COMPATIBILITY_PATH' ) . '/vendor/autoload.php';

( new Main() )->init();
