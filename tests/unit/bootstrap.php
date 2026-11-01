<?php
/**
 * Bootstrap PHPUnit and WP_Mock for the unit tests.
 *
 * @package kagg/compatibility
 */

// Load the Composer autoloader before initializing WP_Mock.
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

WP_Mock::bootstrap();
WP_Mock::userFunction( 'get_option', [ 'return' => [] ] );
WP_Mock::userFunction(
	'KAGG\\Compatibility\\add_action',
	[
		'return' => static function ( ...$args ): void {
			add_action( ...$args );
		},
	]
);

// The MU file initializes itself; empty settings avoid installing a PHP handler here.
require dirname( __DIR__, 2 ) . '/src/php/kagg-compatibility-error-handler.php';
WP_Mock::tearDown();

require dirname( __DIR__, 2 ) . '/.github/scripts/update-changelog.php';
