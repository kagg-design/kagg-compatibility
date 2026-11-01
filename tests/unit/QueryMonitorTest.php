<?php
/**
 * Query Monitor notice suppression and callback restoration tests.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Tests\Unit;

use Closure;
use KAGG\Compatibility\MUErrorHandler;
use PHPUnit\Framework\MockObject\MockObject;
use QM_Collectors;
use stdClass;
use WP_Mock;

/**
 * Verify selective collector wrapping, nested notices, and callback restoration.
 */
final class QueryMonitorTest extends CompatibilityTestCase {

	/**
	 * Error handler configured to suppress notices originating in this test directory.
	 *
	 * @var MUErrorHandler
	 */
	private MUErrorHandler $handler;

	/**
	 * Mock receiving notices forwarded to Query Monitor.
	 *
	 * @var MockObject
	 */
	private MockObject $collector;

	/**
	 * Action registrations captured by the add_action() mock.
	 *
	 * @var array
	 */
	private array $added_actions = [];

	/**
	 * Callback removals captured by the remove_action() mock.
	 *
	 * @var array
	 */
	private array $removed_actions = [];

	/**
	 * Original collector callback priority, or false when it is not registered.
	 *
	 * @var int|false
	 */
	private $collector_priority = 10;

	/**
	 * Create the collector mock and capture changes to its WordPress action registration.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/Fixtures/QMCollectors.php';

		$this->handler            = new MUErrorHandler( [ str_replace( '\\', '/', __DIR__ ) . '/' ], E_USER_NOTICE );
		$this->collector          = $this->getMockBuilder( stdClass::class )
			->addMethods( [ 'action_doing_it_wrong_run' ] )
			->getMock();
		QM_Collectors::$collector = $this->collector;

		WP_Mock::userFunction(
			'has_action',
			[
				'return' => function () {
					return $this->collector_priority;
				},
			]
		);
		WP_Mock::userFunction(
			'remove_action',
			[
				'return' => function ( $hook, $callback, $priority ): bool {
					$this->removed_actions[] = [ $hook, $callback, $priority ];
					return true;
				},
			]
		);
		WP_Mock::userFunction(
			'KAGG\\Compatibility\\add_action',
			[
				'return' => function ( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
					$this->added_actions[] = [ $hook, $callback, $priority, $accepted_args ];
				},
			]
		);
	}

	/**
	 * Clear the collector registry and restore mocks and PHP error handlers.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		QM_Collectors::$collector = null;
		parent::tearDown();
	}

	/**
	 * Verify that selected notices are suppressed and the original collector is restored.
	 *
	 * @return void
	 */
	public function test_suppresses_selected_notices_and_restores_the_collector(): void {
		$this->collector->expects( self::never() )->method( 'action_doing_it_wrong_run' );
		$wrapper = $this->start_selected_notice();

		$wrapper( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();

		$callback = [ $this->collector, 'action_doing_it_wrong_run' ];
		self::assertSame( [ 'doing_it_wrong_run', $callback, 10, 3 ], $this->added_actions[1] );
		self::assertSame(
			[
				[ 'doing_it_wrong_run', $callback, 10 ],
				[ 'doing_it_wrong_run', $wrapper, 10 ],
			],
			$this->removed_actions
		);
	}

	/**
	 * Verify that the temporary wrapper forwards unrelated notices with unchanged arguments.
	 *
	 * @return void
	 */
	public function test_preserves_unrelated_notices_and_their_arguments(): void {
		$this->collector->expects( self::once() )->method( 'action_doing_it_wrong_run' )
			->with( 'custom_function', 'Keep this notice', '1.0' );
		$wrapper = $this->start_selected_notice();

		$wrapper( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$wrapper( 'custom_function', 'Keep this notice', '1.0' );
		$this->handler->restore_doing_it_wrong_collector();
	}

	/**
	 * Verify that unrelated nested notices reach the collector before the outer wrapper is removed.
	 *
	 * @return void
	 */
	public function test_preserves_nested_unrelated_notices(): void {
		$this->collector->expects( self::once() )->method( 'action_doing_it_wrong_run' )
			->with( 'nested_function', 'Nested notice', '1.0' );
		$wrapper = $this->start_selected_notice();

		$this->handler->action_doing_it_wrong_run( 'nested_function', 'Nested notice', '1.0' );
		$wrapper( 'nested_function', 'Nested notice', '1.0' );
		$this->handler->restore_doing_it_wrong_collector();
		self::assertCount( 1, $this->added_actions, 'The outer wrapper must remain until the outer call finishes.' );

		$wrapper( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();
		self::assertSame( [ $this->collector, 'action_doing_it_wrong_run' ], $this->added_actions[1][1] );
	}

	/**
	 * Verify that nested selected notices stay suppressed until the outer collector is restored.
	 *
	 * @return void
	 */
	public function test_suppresses_both_outer_and_nested_selected_notices(): void {
		$this->collector->expects( self::never() )->method( 'action_doing_it_wrong_run' );
		$wrapper                  = $this->start_selected_notice();
		$this->collector_priority = false; // The original callback has been replaced for the outer call.

		$this->handler->action_doing_it_wrong_run( '_load_textdomain_just_in_time', 'Nested notice', '6.7.0' );
		$wrapper( '_load_textdomain_just_in_time', 'Nested notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();
		self::assertCount( 1, $this->added_actions );

		$wrapper( '_load_textdomain_just_in_time', 'Outer notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();
		self::assertSame( [ $this->collector, 'action_doing_it_wrong_run' ], $this->added_actions[1][1] );
	}

	/**
	 * Verify that a collector without a registered callback is not re-enabled.
	 *
	 * @return void
	 */
	public function test_does_not_restart_a_stopped_collector(): void {
		$this->collector_priority = false;
		$this->handler->action_doing_it_wrong_run( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();

		self::assertSame( [], $this->added_actions );
		self::assertSame( [], $this->removed_actions );
	}

	/**
	 * Verify that replacing and restoring the collector preserves its callback priority.
	 *
	 * @return void
	 */
	public function test_preserves_a_custom_collector_priority(): void {
		$this->collector_priority = 15;
		$this->collector->expects( self::never() )->method( 'action_doing_it_wrong_run' );
		$wrapper = $this->start_selected_notice();
		$wrapper( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();

		self::assertSame( 15, $this->added_actions[0][2] );
		self::assertSame( [ 'doing_it_wrong_run', [ $this->collector, 'action_doing_it_wrong_run' ], 15, 3 ], $this->added_actions[1] );
	}

	/**
	 * Verify that a missing collector leaves WordPress action registrations untouched.
	 *
	 * @return void
	 */
	public function test_leaves_hooks_unchanged_when_the_registry_has_no_collector(): void {
		QM_Collectors::$collector = null;
		$this->handler->action_doing_it_wrong_run( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		$this->handler->restore_doing_it_wrong_collector();

		self::assertSame( [], $this->added_actions );
		self::assertSame( [], $this->removed_actions );
	}

	/**
	 * Begin handling a selected notice and capture the registered collector wrapper.
	 *
	 * @return Closure The callback temporarily replacing the Query Monitor collector.
	 */
	private function start_selected_notice(): Closure {
		$this->handler->action_doing_it_wrong_run( '_load_textdomain_just_in_time', 'Selected notice', '6.7.0' );
		self::assertCount( 1, $this->added_actions );
		self::assertInstanceOf( Closure::class, $this->added_actions[0][1] );

		return $this->added_actions[0][1];
	}
}
