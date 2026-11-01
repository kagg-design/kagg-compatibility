<?php /** @noinspection AutoloadingIssuesInspection */
/** Standalone behavior checks, compatible with PHP 7.4+. */

/** @noinspection PhpUndefinedFunctionInspection */

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
const ABSPATH = __DIR__ . '/';

$disabled = in_array( '--disabled', $argv, true );

if ( $disabled ) {
	define( 'KAGG_DISABLE_ERROR_HANDLER', true );
}

$actions = [];
$wp_version = '6.7';

function get_option( $name, $default = false ) {
	return [ 'dirs' => $GLOBALS['test_dirs'] ?? "  /vendor/  \n\n/wp-includes/" ];
}

function apply_filters( $name, $value, ...$args ) {
	if ( 'kagg_compatibility_dirs' === $name && ! isset( $GLOBALS['test_dirs'] ) ) {
		$value = array_merge( $value, [ ' /filtered/ ' ] );
	}
	return run_test_hook( $name, array_merge( [ $value ], $args ), true );
}

function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	remove_action( $name, $callback, $priority );
	$GLOBALS['actions'][ $name ][ $priority ][] = [ $callback, $accepted_args ];
}

function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) {
	add_action( $name, $callback, $priority, $accepted_args );
}

function remove_action( $name, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['actions'][ $name ][ $priority ] ?? [] as $key => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS['actions'][ $name ][ $priority ][ $key ] );
			return true;
		}
	}
	return false;
}

function has_action( $name, $callback ) {
	foreach ( $GLOBALS['actions'][ $name ] ?? [] as $priority => $entries ) {
		foreach ( $entries as $entry ) {
			if ( $entry[0] === $callback ) {
				return $priority;
			}
		}
	}
	return false;
}
function run_test_hook( $name, array $args, bool $filter = false ) {
	$priorities = array_keys( $GLOBALS['actions'][ $name ] ?? [] );
	sort( $priorities );
	foreach ( $priorities as $priority ) {
		foreach ( $GLOBALS['actions'][ $name ][ $priority ] ?? [] as $entry ) {
			$result = call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
			if ( $filter ) {
				$args[0] = $result;
			}
		}
	}
	return $args[0] ?? null;
}
function do_action( $name, ...$args ) {
	run_test_hook( $name, $args );
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
check( true === $installed[0]->error_handler( E_WARNING, 'test', 'C:\\vendor\\test.php', 1 ), 'Windows error path was not normalized.' );
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
$handler->set_error_handler();
check( true === $handler->error_handler( E_NOTICE, 'forward again', '/custom/test.php', 13 ), 'Repeated installation lost the previous handler.' );
restore_error_handler();
restore_error_handler();

// Re-registering the same instance must not create an infinite recursion loop.
$handler->set_error_handler();
$handler->set_error_handler();
check( false === $handler->error_handler( E_NOTICE, 'recursive', '/custom/test.php', 1 ), 'Recursive chain must fall back to PHP.' );
check( true === $handler->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ), 'Recursion guard was not reset.' );
restore_error_handler();
restore_error_handler();

set_error_handler( static function (): bool {
	throw new RuntimeException( 'Previous handler failed.' );
} );
$handler = new KAGG\Compatibility\MUErrorHandler( [ '/vendor/' ], E_WARNING );
$handler->set_error_handler();
try {
	$handler->error_handler( E_NOTICE, 'throw', '/custom/test.php', 1 );
	throw new LogicException( 'Previous handler exception was swallowed.' );
} catch ( RuntimeException $expected ) {
	check( 'Previous handler failed.' === $expected->getMessage(), 'Unexpected fallback exception.' );
}
check( true === $handler->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ), 'Exception left the recursion guard active.' );
restore_error_handler();
restore_error_handler();

// A circular third-party handler must see the guard on every re-entry.
$handler = new KAGG\Compatibility\MUErrorHandler( [ '/vendor/' ], E_WARNING );
$reentries = [];
set_error_handler( static function ( ...$args ) use ( $handler, &$reentries ): bool {
	$reentries[] = $handler->error_handler( ...$args );
	$reentries[] = $handler->error_handler( ...$args );
	return true;
} );
$handler->set_error_handler();
check( true === $handler->error_handler( E_NOTICE, 'circular', '/custom/test.php', 1 ), 'Circular fallback result was lost.' );
check( [ false, false ] === $reentries, 'Recursive entry cleared the outer recursion guard.' );
restore_error_handler();
restore_error_handler();

// Emulate a late Query Monitor/Automator handler which does not chain.
define( 'QM_VERSION', 'test' );
define( 'AUTOMATOR_PLUGIN_VERSION', 'test' );
$late_calls = 0;
set_error_handler( static function () use ( &$late_calls ): bool {
	++$late_calls;
	return true;
} );
do_action( 'plugins_loaded' );
$late_handler = set_error_handler( $probe );
restore_error_handler();
check( $late_handler[0] instanceof KAGG\Compatibility\MUErrorHandler, 'Late plugin handler was not chained.' );
check( true === $late_handler[0]->error_handler( E_WARNING, 'suppressed', '/vendor/test.php', 1 ) && 0 === $late_calls, 'Late handler received a suppressed warning.' );
check( true === $late_handler[0]->error_handler( E_WARNING, 'forward', '/custom/test.php', 1 ) && 1 === $late_calls, 'Late handler did not receive unrelated warnings.' );
restore_error_handler();
restore_error_handler();

// Only configured paths and E_USER_NOTICE may suppress early translations.
$test_dir = str_replace( '\\', '/', __DIR__ ) . '/';
$handler = new KAGG\Compatibility\MUErrorHandler( [ $test_dir ], E_USER_NOTICE );
$notice_args = [ '_load_textdomain_just_in_time', 'Translated message for <code>any-domain</code>', '6.7.0' ];
check( false === $handler->filter_doing_it_wrong_trigger_error( true, ...$notice_args ), 'Early translation notice in a selected directory was not suppressed.' );
check( true === $handler->filter_doing_it_wrong_trigger_error( true, 'custom_function', '', '' ), 'An unrelated doing-it-wrong notice was suppressed.' );
check( false === $handler->filter_doing_it_wrong_trigger_error( false, 'custom_function', '', '' ), 'An existing false filter result changed.' );
$unselected = new KAGG\Compatibility\MUErrorHandler( [ '/unselected/' ], E_USER_NOTICE );
check( true === $unselected->filter_doing_it_wrong_trigger_error( true, ...$notice_args ), 'An unselected directory was suppressed.' );
$warning_only = new KAGG\Compatibility\MUErrorHandler( [ $test_dir ], E_WARNING );
check( true === $warning_only->filter_doing_it_wrong_trigger_error( true, ...$notice_args ), 'Unselected E_USER_NOTICE level was suppressed.' );

// Missing Query Monitor must not interfere with WordPress actions.
$handler->action_doing_it_wrong_run( ...$notice_args );
$handler->restore_doing_it_wrong_collector();

if ( ! class_exists( 'QM_Collectors' ) ) {
	class QM_Collectors {
		public static $collector;
		public static function get( $name ) {
			return self::$collector;
		}
	}
}
$collector = new class {
	public array $notices = [];
	public function action_doing_it_wrong_run( ...$args ): void {
		$this->notices[] = $args;
	}
};
QM_Collectors::$collector = $collector;
$callback = [ $collector, 'action_doing_it_wrong_run' ];
$actions['doing_it_wrong_run'] = [];
add_action( 'doing_it_wrong_run', [ $handler, 'action_doing_it_wrong_run' ], 0, 3 );
add_action( 'doing_it_wrong_run', $callback, 10, 3 );
add_action( 'doing_it_wrong_run', [ $handler, 'restore_doing_it_wrong_collector' ], PHP_INT_MAX );
do_action( 'doing_it_wrong_run', ...$notice_args );
check( [] === $collector->notices, 'Query Monitor collected a selected translation notice.' );
check( 10 === has_action( 'doing_it_wrong_run', $callback ), 'Query Monitor callback was not restored.' );
do_action( 'doing_it_wrong_run', 'custom_function', 'Keep this message', '1.0' );
check( [ [ 'custom_function', 'Keep this message', '1.0' ] ] === $collector->notices, 'Unrelated Query Monitor notice was lost or changed.' );

// Nested unrelated notices must still reach the temporarily wrapped collector.
$nested = static function ( $function_name ) {
	if ( '_load_textdomain_just_in_time' === $function_name ) {
		do_action( 'doing_it_wrong_run', 'nested_function', 'Nested message', '1.0' );
	}
};
add_action( 'doing_it_wrong_run', $nested, 5 );
do_action( 'doing_it_wrong_run', ...$notice_args );
check( 2 === count( $collector->notices ) && 'nested_function' === $collector->notices[1][0], 'Nested unrelated notice was lost or selected notice leaked.' );
check( 10 === has_action( 'doing_it_wrong_run', $callback ), 'Nested action failed to restore the collector.' );
remove_action( 'doing_it_wrong_run', $nested, 5 );

// A stopped collector must stay stopped; custom callback priorities must survive.
remove_action( 'doing_it_wrong_run', $callback );
do_action( 'doing_it_wrong_run', ...$notice_args );
check( false === has_action( 'doing_it_wrong_run', $callback ), 'A stopped collector was re-enabled.' );
add_action( 'doing_it_wrong_run', $callback, 15, 3 );
do_action( 'doing_it_wrong_run', ...$notice_args );
check( 15 === has_action( 'doing_it_wrong_run', $callback ) && 2 === count( $collector->notices ), 'Custom collector priority was changed or suppression failed.' );
QM_Collectors::$collector = null;
do_action( 'doing_it_wrong_run', ...$notice_args );
check( 3 === count( $collector->notices ), 'Missing collector should leave existing actions alone.' );

// Empty settings, disabled levels, and older WordPress must not install translation hooks.
foreach ( [ [ '', E_USER_NOTICE, '6.7' ], [ $test_dir, 0, '6.7' ], [ $test_dir, E_USER_NOTICE, '6.6' ] ] as $settings ) {
	[ $test_dirs, $test_levels, $wp_version ] = $settings;
	$actions = [];
	add_filter( 'kagg_compatibility_levels', static function () use ( $test_levels ) { return $test_levels; } );
	$instance = new KAGG\Compatibility\MUErrorHandler();
	$instance->init();
	check( ! isset( $actions['doing_it_wrong_run'] ) && ! isset( $actions['doing_it_wrong_trigger_error'] ), 'Inactive/older configuration installed translation hooks.' );
	if ( $test_dirs && $test_levels ) {
		restore_error_handler();
	}
}
echo 'Error-handler checks passed on PHP ' . PHP_VERSION . ".\n";
