<?php
/**
 * Error-handler initialization and plugin compatibility tests.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use KAGG\Compatibility\MUErrorHandler;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;

/**
 * Verify settings, hook registration, and error-handler chain restoration.
 */
final class ErrorHandlerInitializationTest extends CompatibilityTestCase {

	/**
	 * Verify that settings and filtered directories are normalized before error matching.
	 *
	 * @return void
	 */
	public function test_loads_and_normalizes_settings_and_filtered_directories(): void {
		$this->directories = "  /vendor/  \r\n\n C:\\libraries\\ ";
		WP_Mock::onFilter( 'kagg_compatibility_dirs' )
			->with(
				[
					0 => '/vendor/',
					2 => 'C:/libraries/',
				]
			)
			->reply( [ '/vendor/', 'C:/libraries/', ' /filtered/ ', '', ' ', null ] );
		$handler = new MUErrorHandler();
		$handler->init();

		self::assertTrue( $handler->error_handler( E_DEPRECATED, 'test', '/vendor/library/test.php', 1 ) );
		self::assertTrue( $handler->error_handler( E_WARNING, 'test', 'C:/libraries/test.php', 1 ) );
		self::assertTrue( $handler->error_handler( E_WARNING, 'test', '/filtered/test.php', 1 ) );
		self::assertFalse( $handler->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ) );
	}

	/**
	 * Verify that initialization installs the handler and registers compatibility hooks.
	 *
	 * @return void
	 */
	public function test_registers_compatibility_hooks_and_installs_the_handler(): void {
		$handler = new MUErrorHandler();
		WP_Mock::expectActionAdded( 'plugin_loaded', [ $handler, 'plugin_loaded' ], 500 );
		WP_Mock::expectActionAdded( 'plugins_loaded', [ $handler, 'plugins_loaded' ], 1000 );
		WP_Mock::expectActionAdded( 'action_scheduler_before_execute', [ new AnyInstance( MUErrorHandler::class ), 'set_error_handler' ], 500 );
		WP_Mock::expectActionAdded( 'admin_head', [ $handler, 'admin_head' ] );
		WP_Mock::expectActionAdded( 'doing_it_wrong_run', [ $handler, 'action_doing_it_wrong_run' ], 0, 3 );
		WP_Mock::expectFilterAdded( 'doing_it_wrong_trigger_error', [ $handler, 'filter_doing_it_wrong_trigger_error' ], 10, 4 );
		$handler->init();

		self::assertSame( [ $handler, 'error_handler' ], self::current_error_handler() );
	}

	/**
	 * Verify that the configured error-level filter controls which errors are suppressed.
	 *
	 * @return void
	 */
	public function test_honors_the_error_levels_filter(): void {
		WP_Mock::onFilter( 'kagg_compatibility_levels' )->with( self::DEFAULT_ERROR_LEVELS )->reply( E_DEPRECATED );
		$handler = new MUErrorHandler();
		$handler->init();

		self::assertTrue( $handler->error_handler( E_DEPRECATED, 'test', '/vendor/test.php', 1 ) );
		self::assertFalse( $handler->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ) );
	}

	/**
	 * Verify that inactive settings and older WordPress versions leave translation hooks unregistered.
	 *
	 * @dataProvider inactive_configurations
	 *
	 * @param string $dirs Configured directories, or an empty string to disable suppression.
	 * @param int    $levels Error levels selected by the settings filter.
	 * @param string $wp_version WordPress version used to evaluate hook availability.
	 *
	 * @return void
	 */
	public function test_does_not_install_translation_hooks_when_inactive_or_on_older_wordpress( string $dirs, int $levels, string $wp_version ): void {
		$this->directories = $dirs;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate the WordPress version used by this test.
		$GLOBALS['wp_version'] = $wp_version;
		WP_Mock::onFilter( 'kagg_compatibility_levels' )->with( self::DEFAULT_ERROR_LEVELS )->reply( $levels );
		$before  = self::current_error_handler();
		$handler = new MUErrorHandler();
		WP_Mock::expectActionNotAdded( 'doing_it_wrong_run', [ $handler, 'action_doing_it_wrong_run' ], 0, 3 );
		WP_Mock::expectFilterNotAdded( 'doing_it_wrong_trigger_error', [ $handler, 'filter_doing_it_wrong_trigger_error' ], 10, 4 );
		$handler->init();

		self::assertSame( '' === $dirs || 0 === $levels ? $before : [ $handler, 'error_handler' ], self::current_error_handler() );
	}

	/**
	 * Provide disabled configurations and WordPress versions without translation notices.
	 *
	 * @return array<string, array{0: string, 1: int, 2: string}> Initialization scenarios.
	 */
	public static function inactive_configurations(): array {
		return [
			'empty directories' => [ '', E_USER_NOTICE, '6.7' ],
			'disabled levels'   => [ '/vendor/', 0, '6.7' ],
			'WordPress 6.6'     => [ '/vendor/', E_USER_NOTICE, '6.6' ],
		];
	}

	/**
	 * Verify that the disable constant prevents handler installation and hook registration.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_disable_constant_prevents_initialization(): void {
		define( 'KAGG_DISABLE_ERROR_HANDLER', true );
		$before  = self::current_error_handler();
		$handler = new MUErrorHandler();
		WP_Mock::expectActionNotAdded( 'admin_head', [ $handler, 'admin_head' ] );
		WP_Mock::expectActionNotAdded( 'plugin_loaded', [ $handler, 'plugin_loaded' ], 500 );
		$handler->init();

		self::assertSame( $before, self::current_error_handler() );
	}

	/**
	 * Verify that loading Query Monitor restores suppression while other plugins leave the chain intact.
	 *
	 * @dataProvider plugin_paths
	 *
	 * @param string $plugin Loaded plugin path in Unix or Windows notation.
	 * @param bool   $expected Whether the plugin should cause handler reinstallation.
	 *
	 * @return void
	 */
	public function test_restores_the_handler_after_query_monitor_loads( string $plugin, bool $expected ): void {
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );
		$before  = self::current_error_handler();
		$handler->plugin_loaded( $plugin );

		if ( ! $expected ) {
			self::assertSame( $before, self::current_error_handler() );
			return;
		}
		$installed = self::current_error_handler();
		self::assertInstanceOf( MUErrorHandler::class, $installed[0] );
		self::assertTrue( $installed[0]->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ) );
		self::assertFalse( $installed[0]->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ) );
	}

	/**
	 * Provide Query Monitor paths and an unrelated plugin for load-hook checks.
	 *
	 * @return array<string, array{0: string, 1: bool}> Plugin paths and expected reinstallation flags.
	 */
	public static function plugin_paths(): array {
		return [
			'Unix path'      => [ '/plugins/query-monitor/query-monitor.php', true ],
			'Windows path'   => [ 'C:\\plugins\\query-monitor\\query-monitor.php', true ],
			'another plugin' => [ '/plugins/custom/plugin.php', false ],
		];
	}

	/**
	 * Verify that late plugin handlers receive only errors outside the configured directories.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider late_plugins
	 *
	 * @param string $constant Version constant identifying the plugin that replaced the handler.
	 *
	 * @return void
	 */
	public function test_chains_handlers_installed_late_by_other_plugins( string $constant ): void {
		define( $constant, 'test' );
		$handler = new MUErrorHandler();
		$handler->init();
		$calls = 0;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function () use ( &$calls ): bool {
				++$calls;
				return true;
			}
		);

		$handler->plugins_loaded();
		$installed = self::current_error_handler();

		self::assertInstanceOf( MUErrorHandler::class, $installed[0] );
		self::assertTrue( $installed[0]->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ) );
		self::assertSame( 0, $calls );
		self::assertTrue( $installed[0]->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ) );
		self::assertSame( 1, $calls );
	}

	/**
	 * Provide plugin constants that require rebuilding the error-handler chain.
	 *
	 * @return array<string, array{0: string}> Plugin version constants.
	 */
	public static function late_plugins(): array {
		return [
			'Query Monitor'     => [ 'QM_VERSION' ],
			'Uncanny Automator' => [ 'AUTOMATOR_PLUGIN_VERSION' ],
		];
	}

	/**
	 * Verify that repeated scheduler jobs preserve suppression and the previous handler.
	 *
	 * @return void
	 */
	public function test_reinstalls_the_handler_for_repeated_action_scheduler_jobs(): void {
		$scheduler_callback = null;
		WP_Mock::userFunction(
			'KAGG\\Compatibility\\add_action',
			[
				'return' => static function ( $hook, $callback ) use ( &$scheduler_callback ): void {
					if ( 'action_scheduler_before_execute' === $hook ) {
						$scheduler_callback = $callback;
					}
				},
			]
		);
		$handler = new MUErrorHandler();
		$handler->init();
		$calls = 0;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function () use ( &$calls ): bool {
				++$calls;
				return true;
			}
		);
		self::assertIsCallable( $scheduler_callback );
		$scheduler_callback();
		$scheduler_callback();
		$installed = self::current_error_handler();

		self::assertTrue( $installed[0]->error_handler( E_WARNING, 'test', '/vendor/test.php', 1 ) );
		self::assertSame( 0, $calls );
		self::assertTrue( $installed[0]->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ) );
		self::assertSame( 1, $calls );
	}
}
