<?php
/**
 * Shared WordPress mock and PHP error-handler fixtures.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use Closure;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WP_Mock;

/**
 * Isolate WordPress mocks and native error handlers between test cases.
 */
abstract class CompatibilityTestCase extends PHPUnitTestCase {

	protected const DEFAULT_ERROR_LEVELS = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED;

	/**
	 * Directory settings returned by the mocked WordPress options API.
	 *
	 * @var string
	 */
	protected string $directories = '/vendor/';

	/**
	 * Handler marking the start of the current test's native handler stack.
	 *
	 * @var Closure
	 */
	private Closure $baseline_handler;

	/**
	 * Initialize WordPress mocks and preserve the PHP error handler for each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		WP_Mock::setUp();
		WP_Mock::userFunction(
			'KAGG\\Compatibility\\add_action',
			[
				'return' => static function ( ...$args ): void {
					add_action( ...$args );
				},
			]
		)->byDefault();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate the WordPress version used by this test.
		$GLOBALS['wp_version'] = '6.7';

		WP_Mock::userFunction(
			'get_option',
			[
				'return' => function () {
					return [ 'dirs' => $this->directories ];
				},
			]
		);

		$this->baseline_handler = static function (): bool {
			return false;
		};
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler( $this->baseline_handler );
	}

	/**
	 * Restore the PHP error-handler stack and verify WordPress mock expectations.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Unwind handlers installed by the test and restore PHPUnit's handler.
		while ( self::current_error_handler() !== $this->baseline_handler ) {
			restore_error_handler();
		}
		restore_error_handler();

		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Inspect the active PHP error handler without changing the handler stack.
	 *
	 * @return callable|null The active user-defined handler, or null for PHP defaults.
	 */
	protected static function current_error_handler(): ?callable {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		$handler = set_error_handler(
			static function (): bool {
				return false;
			}
		);
		restore_error_handler();

		return $handler;
	}
}
