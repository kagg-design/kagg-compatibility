<?php
/**
 * Error suppression and fallback handling tests.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use KAGG\Compatibility\MUErrorHandler;
use RuntimeException;

/**
 * Verify directory matching, fallback forwarding, and recursion protection.
 */
final class ErrorHandlerTest extends CompatibilityTestCase {

	/**
	 * Verify that a selected error level is suppressed while an unselected fatal level is forwarded.
	 *
	 * @dataProvider suppressed_levels
	 *
	 * @param int $level Error level configured for suppression.
	 *
	 * @return void
	 */
	public function test_suppresses_configured_error_levels( int $level ): void {
		$handler = new MUErrorHandler( [ '/vendor/' ], $level );

		self::assertTrue( $handler->error_handler( $level, 'test', '/vendor/test.php', 1 ) );
		self::assertFalse( $handler->error_handler( E_USER_ERROR, 'test', '/vendor/test.php', 1 ) );
	}

	/**
	 * Provide the warning, notice, and deprecation levels supported by default.
	 *
	 * @return array<string, array{0: int}> Supported error levels.
	 */
	public static function suppressed_levels(): array {
		return [
			'warning'         => [ E_WARNING ],
			'notice'          => [ E_NOTICE ],
			'user warning'    => [ E_USER_WARNING ],
			'user notice'     => [ E_USER_NOTICE ],
			'deprecated'      => [ E_DEPRECATED ],
			'user deprecated' => [ E_USER_DEPRECATED ],
		];
	}

	/**
	 * Verify that errors from directories outside the configured list reach the fallback.
	 *
	 * @return void
	 */
	public function test_does_not_suppress_unconfigured_directories(): void {
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );

		self::assertFalse( $handler->error_handler( E_WARNING, 'test', '/custom/test.php', 1 ) );
	}

	/**
	 * Verify that Windows path separators match normalized suppression directories.
	 *
	 * @return void
	 */
	public function test_normalizes_windows_error_paths(): void {
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );

		self::assertTrue( $handler->error_handler( E_WARNING, 'test', 'C:\\vendor\\test.php', 1 ) );
	}

	/**
	 * Verify that the previous handler receives unchanged arguments and controls the result.
	 *
	 * @dataProvider fallback_results
	 *
	 * @param bool $result Whether the previous handler reports the error as handled.
	 *
	 * @return void
	 */
	public function test_forwards_arguments_and_preserves_fallback_result( bool $result ): void {
		$forwarded = [];
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function ( ...$args ) use ( &$forwarded, $result ): bool {
				$forwarded = $args;
				return $result;
			}
		);
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );
		$handler->set_error_handler();

		self::assertSame( $result, $handler->error_handler( E_NOTICE, 'forward', '/custom/test.php', 12 ) );
		self::assertSame( [ E_NOTICE, 'forward', '/custom/test.php', 12 ], $forwarded );
	}

	/**
	 * Provide both handled and unhandled results from the previous error handler.
	 *
	 * @return array<string, array{0: bool}> Fallback results.
	 */
	public static function fallback_results(): array {
		return [
			'handled'         => [ true ],
			'use PHP handler' => [ false ],
		];
	}

	/**
	 * Verify that registering the same instance twice keeps its original fallback.
	 *
	 * @return void
	 */
	public function test_repeated_registration_preserves_the_previous_handler(): void {
		$calls = 0;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function () use ( &$calls ): bool {
				++$calls;
				return true;
			}
		);
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );
		$handler->set_error_handler();
		$handler->set_error_handler();

		self::assertTrue( $handler->error_handler( E_NOTICE, 'test', '/custom/test.php', 1 ) );
		self::assertSame( 1, $calls );
	}

	/**
	 * Verify that every recursive entry is rejected until the outer handler finishes.
	 *
	 * @return void
	 */
	public function test_keeps_the_guard_active_for_all_recursive_entries(): void {
		$handler   = new MUErrorHandler( [ '/vendor/' ], E_WARNING );
		$calls     = 0;
		$reentries = [];
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function ( ...$args ) use ( $handler, &$calls, &$reentries ): bool {
				if ( ++$calls > 1 ) {
					return true; // Bound recursion if the guard regresses.
				}
				$reentries[] = $handler->error_handler( ...$args );
				$reentries[] = $handler->error_handler( ...$args );
				return true;
			}
		);
		$handler->set_error_handler();

		self::assertTrue( $handler->error_handler( E_NOTICE, 'circular', '/custom/test.php', 1 ) );
		self::assertSame( 1, $calls );
		self::assertSame( [ false, false ], $reentries );
		self::assertTrue( $handler->error_handler( E_WARNING, 'next error', '/vendor/test.php', 1 ) );
	}

	/**
	 * Verify that fallback exceptions propagate and leave the next error free to be handled.
	 *
	 * @return void
	 */
	public function test_propagates_fallback_exceptions_and_resets_the_guard(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Exercise and restore the native error-handler chain.
		set_error_handler(
			static function (): bool {
				throw new RuntimeException( 'Previous handler failed.' );
			}
		);
		$handler = new MUErrorHandler( [ '/vendor/' ], E_WARNING );
		$handler->set_error_handler();

		try {
			$handler->error_handler( E_NOTICE, 'throw', '/custom/test.php', 1 );
			self::fail( 'The previous handler exception was swallowed.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Previous handler failed.', $exception->getMessage() );
		}

		self::assertTrue( $handler->error_handler( E_WARNING, 'next error', '/vendor/test.php', 1 ) );
	}
}
