<?php
/**
 * Smoke checks using real WordPress functions without changing site settings.
 * Run: wp --path=/path/to/wordpress --skip-plugins --skip-themes eval-file /path/to/plugin/tests/wordpress.php
 */

function kagg_release_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

// Keep all plugin option reads in memory, including the activation defaults.
add_filter( 'pre_option_kagg_compatibility_settings', static function () {
	return [ 'dirs' => '/release-smoke/' ];
} );
$plugin_root = getenv( 'KAGG_COMPATIBILITY_ROOT' ) ?: dirname( __DIR__ );
require $plugin_root . '/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
WP_Filesystem();

$main = new KAGG\Compatibility\Main();
$main->init();
$main->load();
kagg_release_check( has_action( 'activate_' . plugin_basename( KAGG_COMPATIBILITY_FILE ) ), 'Activation hook missing.' );
kagg_release_check( has_action( 'wp_ajax_kagg-compatibility-general-reset' ), 'Settings reset hook missing.' );
$general = new KAGG\Compatibility\Settings\General();
kagg_release_check( in_array( '/wp-content/plugins/woocommerce/', $general->init_dirs(), true ), 'WooCommerce directory missing from defaults.' );

// Copy/delete the actual mu-plugin payload only in the system temp directory.
$destination = tempnam( sys_get_temp_dir(), 'kagg-release-' );
$property = new ReflectionProperty( $main, 'error_handler_destination' );
if ( PHP_VERSION_ID < 80100 ) {
	$property->setAccessible( true );
}
$property->setValue( $main, $destination );
try {
	$main->activation_hook();
	kagg_release_check( file_get_contents( $destination ) === file_get_contents( $plugin_root . '/src/php/kagg-compatibility-error-handler.php' ), 'Activation did not install the error-handler payload.' );
	$main->deactivation_hook();
	kagg_release_check( ! file_exists( $destination ), 'Deactivation did not remove the payload.' );
} finally {
	if ( file_exists( $destination ) ) {
		unlink( $destination );
	}
}

require $plugin_root . '/src/php/kagg-compatibility-error-handler.php';
$handler = new KAGG\Compatibility\MUErrorHandler( [ '/release-smoke/' ], E_WARNING | E_DEPRECATED );
kagg_release_check( true === $handler->error_handler( E_DEPRECATED, 'test', '/release-smoke/test.php', 1 ), 'Deprecation was not suppressed.' );
kagg_release_check( false === $handler->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ), 'Custom code warning was suppressed.' );
echo 'WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ": smoke checks passed.\n";
