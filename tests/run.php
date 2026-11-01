<?php
/** Standalone behavior checks, compatible with PHP 7.4+. */

require dirname( __DIR__ ) . '/.github/scripts/update-changelog.php';

function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$readme = "== Changelog ==\n\n= 2.6.0 =\n* Current.\n\n= 2.5.0 =\n* Updated.\n\n= 2.0 =\n* Legacy.\n\n[See changelog for all versions](https://example.org).\n\n== Upgrade Notice ==\nNot release notes.\n";
$archive = "= 2.5.0 =\n* Old.\n\n= 2.4.3 =\n* Archived minor.\n\n= 1.0.0 =\n* Initial.\n";
$generated = kagg_compatibility_generate_changelog( $readme, $archive );
check( array_keys( kagg_compatibility_parse_releases( $generated ) ) === [ '2.6.0', '2.5.0', '2.4.3', '2.0', '1.0.0' ], 'Release ordering or archived history was lost.' );
check( strpos( $generated, '* Updated.' ) !== false && strpos( $generated, '* Old.' ) === false, 'Readme notes must replace archived notes.' );
check( strpos( $generated, 'See changelog' ) === false && strpos( $generated, 'Upgrade Notice' ) === false, 'Non-release text leaked into the archive.' );
check( $generated === kagg_compatibility_generate_changelog( $readme, $generated ), 'Repeated generation duplicated release entries.' );
check( $generated === kagg_compatibility_generate_changelog( str_replace( "\n", "\r\n", $readme ), $archive ), 'CRLF input changed the generated history.' );
try {
	kagg_compatibility_generate_changelog( 'No changelog.', '' );
	throw new LogicException( 'Missing release notes were accepted.' );
} catch ( RuntimeException $expected ) {
	// Expected invalid-input failure.
}
echo "Changelog checks passed.\n";

// Only the WordPress hooks/options used by MUErrorHandler are needed here.
define( 'ABSPATH', __DIR__ . '/' );
$disabled = in_array( '--disabled', $argv, true );
if ( $disabled ) {
	define( 'KAGG_DISABLE_ERROR_HANDLER', true );
}
$actions = [];
function get_option( $name, $default = false ) {
	return [ 'dirs' => "  /vendor/  \n\n/wp-includes/" ];
}
function apply_filters( $name, $value ) {
	return 'kagg_compatibility_dirs' === $name ? array_merge( $value, [ ' /filtered/ ' ] ) : $value;
}
function add_action( $name, $callback, $priority = 10 ) {
	$GLOBALS['actions'][ $name ][] = $callback;
}
// WordPress supplies this polyfill before loading mu-plugins on PHP 7.4.
if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}

require dirname( __DIR__ ) . '/src/php/kagg-compatibility-error-handler.php';
if ( $disabled ) {
	check( [] === $actions, 'Disable constant did not prevent hook registration.' );
	$previous = set_error_handler( static function () {} );
	restore_error_handler();
	check( null === $previous, 'Disable constant did not prevent handler installation.' );
	echo "Disabled-handler checks passed.\n";
	exit;
}
check( isset( $actions['plugin_loaded'], $actions['plugins_loaded'], $actions['action_scheduler_before_execute'], $actions['admin_head'] ), 'Required compatibility hooks were not registered.' );
$probe = static function () {};
$installed = set_error_handler( $probe );
restore_error_handler();
check( is_array( $installed ) && $installed[0] instanceof KAGG\Compatibility\MUErrorHandler, 'MU handler did not initialize.' );
check( true === $installed[0]->error_handler( E_DEPRECATED, 'test', '/vendor/library/test.php', 1 ), 'Configured directory was not normalized/suppressed.' );
check( true === $installed[0]->error_handler( E_WARNING, 'test', '/filtered/test.php', 1 ), 'Filtered directory was not normalized/suppressed.' );
check( false === $installed[0]->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ), 'Unconfigured directories must reach the fallback.' );
restore_error_handler();

foreach ( [ E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED ] as $level ) {
	$handler = new KAGG\Compatibility\MUErrorHandler( [ '/vendor/' ], $level );
	check( true === $handler->error_handler( $level, 'test', '/vendor/test.php', 1 ), 'Supported error level was not suppressed.' );
	check( false === $handler->error_handler( E_USER_ERROR, 'test', '/vendor/test.php', 1 ), 'Unconfigured error level must reach the fallback.' );
}
$forwarded = [];
set_error_handler( static function ( ...$args ) use ( &$forwarded ): bool {
	$forwarded = $args;
	return true;
} );
$handler = new KAGG\Compatibility\MUErrorHandler( [ '/vendor/' ], E_WARNING );
$handler->set_error_handler();
check( true === $handler->error_handler( E_NOTICE, 'forward', '/custom/test.php', 12 ), 'Previous handler result was lost.' );
check( [ E_NOTICE, 'forward', '/custom/test.php', 12 ] === $forwarded, 'Previous handler arguments changed.' );
restore_error_handler();
restore_error_handler();

// Re-registering the same instance must not create an infinite recursion loop.
$handler->set_error_handler();
$handler->set_error_handler();
check( false === $handler->error_handler( E_NOTICE, 'recursive', '/custom/test.php', 1 ), 'Recursive chain must fall back to PHP.' );
check( true === $handler->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ), 'Recursion guard was not reset.' );
restore_error_handler();
restore_error_handler();
echo 'Error-handler checks passed on PHP ' . PHP_VERSION . ".\n";
