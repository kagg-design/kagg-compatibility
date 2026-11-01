<?php
/**
 * Early translation notice filtering tests.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use KAGG\Compatibility\MUErrorHandler;
use WP_Mock;

/**
 * Verify that translation notice suppression follows the configured scope.
 */
final class TranslationNoticeTest extends CompatibilityTestCase {

	/**
	 * Verify that translation suppression respects directories, error levels, and existing filter results.
	 *
	 * @dataProvider notice_cases
	 *
	 * @param string $dirs Directory to suppress, or the selected-path fixture marker.
	 * @param int    $levels Error levels configured for suppression.
	 * @param bool   $trigger Existing decision to trigger the WordPress notice.
	 * @param string $function_name Function reported by the doing-it-wrong notice.
	 * @param bool   $expected Expected decision after applying the handler filter.
	 *
	 * @return void
	 */
	public function test_filters_only_selected_translation_notices( string $dirs, int $levels, bool $trigger, string $function_name, bool $expected ): void {
		$directory = 'selected' === $dirs ? str_replace( '\\', '/', __DIR__ ) . '/' : $dirs;
		$handler   = new MUErrorHandler( [ $directory ], $levels );

		self::assertSame(
			$expected,
			$handler->filter_doing_it_wrong_trigger_error( $trigger, $function_name, 'Translated <code>any-domain</code> message', '6.7.0' )
		);
	}

	/**
	 * Provide selected and unrelated translation-notice scenarios.
	 *
	 * @return array<string, array{0: string, 1: int, 2: bool, 3: string, 4: bool}> Filter scenarios.
	 */
	public static function notice_cases(): array {
		return [
			'selected path and level' => [ 'selected', E_USER_NOTICE, true, '_load_textdomain_just_in_time', false ],
			'unselected path'         => [ '/unselected/', E_USER_NOTICE, true, '_load_textdomain_just_in_time', true ],
			'unselected level'        => [ 'selected', E_WARNING, true, '_load_textdomain_just_in_time', true ],
			'other function'          => [ 'selected', E_USER_NOTICE, true, 'custom_function', true ],
			'existing false result'   => [ 'selected', E_USER_NOTICE, false, 'custom_function', false ],
		];
	}

	/**
	 * Verify that missing Query Monitor integration does not prevent translation suppression.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_works_without_query_monitor(): void {
		self::assertFalse( class_exists( 'QM_Collectors' ) );
		$handler = new MUErrorHandler( [ str_replace( '\\', '/', __DIR__ ) . '/' ], E_USER_NOTICE );
		WP_Mock::userFunction( 'has_action', [ 'times' => 0 ] );
		WP_Mock::userFunction( 'remove_action', [ 'times' => 0 ] );
		$handler->action_doing_it_wrong_run( '_load_textdomain_just_in_time', 'Notice', '6.7.0' );
		$handler->restore_doing_it_wrong_collector();

		self::assertFalse( $handler->filter_doing_it_wrong_trigger_error( true, '_load_textdomain_just_in_time', 'Notice', '6.7.0' ) );
	}
}
