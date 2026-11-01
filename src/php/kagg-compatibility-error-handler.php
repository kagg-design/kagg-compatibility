<?php
/**
 * The error handler to suppress error messages from vendor directories,
 * WordPress Core, and some plugins.
 *
 * @package kagg/compatibility
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection AutoloadingIssuesInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace KAGG\Compatibility;

use QM_Collectors;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MUErrorHandler.
 */
class MUErrorHandler {

	/**
	 * Option name.
	 */
	private const OPTION = 'kagg_compatibility_settings';

	/**
	 * Error handler option key.
	 */
	private const OPTION_KEY = 'dirs';

	/**
	 * Directories from where errors should be suppressed.
	 *
	 * @var string[]
	 */
	private array $dirs;

	/**
	 * Previous error handler.
	 *
	 * @var callable|null
	 */
	private $previous_error_handler;

	/**
	 * Error levels to suppress.
	 *
	 * @var int
	 */
	private int $levels;

	/**
	 * Whether the error handler is handling an error.
	 *
	 * @var bool
	 */
	private bool $handling = false;

	/**
	 * Temporarily replaced Query Monitor callbacks, one entry per nested action.
	 *
	 * @var array
	 */
	private array $qm_callbacks = [];

	/**
	 * Class constructor.
	 *
	 * @param array $dirs   Directories from where errors should be suppressed.
	 * @param int   $levels Error levels to suppress.
	 */
	public function __construct( array $dirs = [], int $levels = 0 ) {
		$this->dirs   = $dirs;
		$this->levels = $levels;
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( defined( 'KAGG_DISABLE_ERROR_HANDLER' ) && KAGG_DISABLE_ERROR_HANDLER ) {
			return;
		}

		$option     = get_option( self::OPTION, [] );
		$this->dirs = empty( $option[ self::OPTION_KEY ] )
			? []
			: (array) explode( "\n", $option[ self::OPTION_KEY ] );

		$this->normalize_dirs();

		/**
		 * Allow modifying the list of dirs to suppress messages from.
		 *
		 * @param array $dirs The list of dirs to suppress messages from.
		 */
		$this->dirs = (array) apply_filters( 'kagg_compatibility_dirs', $this->dirs );

		$this->normalize_dirs();

		/**
		 * Allow modifying the levels of messages to suppress.
		 *
		 * @param int $levels Error levels of messages to suppress.
		 */
		$this->levels = (int) apply_filters(
			'kagg_compatibility_levels',
			E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED
		);

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		if ( $this->dirs && $this->levels ) {
			$this->set_error_handler();

			// Some plugins destroy an error handler chain. Set the error handler again upon loading them.
			add_action( 'plugin_loaded', [ $this, 'plugin_loaded' ], 500 );
			add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 1000 );
			add_action(
				'action_scheduler_before_execute',
				[ new self( $this->dirs, $this->levels ), 'set_error_handler' ],
				500
			);

			// Translation notices also reach Query Monitor directly, bypassing the PHP error handler.
			if ( version_compare( $GLOBALS['wp_version'], '6.7', '>=' ) ) {
				add_action( 'doing_it_wrong_run', [ $this, 'action_doing_it_wrong_run' ], 0, 3 );
				add_action( 'doing_it_wrong_run', [ $this, 'restore_doing_it_wrong_collector' ], PHP_INT_MAX );
				add_filter( 'doing_it_wrong_trigger_error', [ $this, 'filter_doing_it_wrong_trigger_error' ], 10, 4 );
			}
		}

		add_action( 'admin_head', [ $this, 'admin_head' ] );
	}

	/**
	 * Set the error handler and save the original.
	 */
	public function set_error_handler(): void {
		// To chain error handlers, we must not specify the second argument and catch all errors in our handler.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$previous_error_handler = set_error_handler( [ $this, 'error_handler' ] );

		if ( [ $this, 'error_handler' ] === $previous_error_handler ) {
			// Keep the existing fallback when the same instance is already installed.
			restore_error_handler();

			return;
		}

		$this->previous_error_handler = $previous_error_handler;
	}

	/**
	 * Clear error caused by xdebug with PHP 8.1.
	 * This error leads to adding .php-error class `margin-top: 2em;` to the #adminmenuwrap.
	 *
	 * @return void
	 */
	public function admin_head(): void {
		$error_get_last = error_get_last();

		if ( ! isset( $error_get_last['file'] ) ) {
			return;
		}

		if ( 'xdebug://debug-eval' === $error_get_last['file'] ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.error_clear_lastFound
			error_clear_last();
		}
	}

	/**
	 * The 'plugin_loaded' hook.
	 *
	 * @param string|mixed $plugin Plugin file path.
	 *
	 * @return void
	 */
	public function plugin_loaded( $plugin ): void {
		$plugin = (string) $plugin;
		$plugin = str_replace( '\\', '/', $plugin );

		// Plugins that destroy an error handler chain.
		// These plugins set their error handler before the plugin_loaded event, during an initial plugin load.
		$plugin_files = [
			'query-monitor/query-monitor.php', // Query Monitor.
		];

		$found = false;

		foreach ( $plugin_files as $plugin_file ) {
			if ( false !== strpos( $plugin, $plugin_file ) ) {
				$found = true;

				break;
			}
		}

		if ( ! $found ) {
			return;
		}

		// Set this error handler after loading a plugin to chain its error handler.
		( new self( $this->dirs, $this->levels ) )->set_error_handler();
	}

	/**
	 * The 'plugins_loaded' hook.
	 *
	 * @return void
	 */
	public function plugins_loaded(): void {
		// Constants of plugins that destroy an error handler chain.
		// These plugins set their error handler on plugins_loaded event.
		$constants = [
			'QM_VERSION', // Query Monitor can replace its handler during plugins_loaded too.
			'AUTOMATOR_PLUGIN_VERSION', // Uncanny Automator.
		];

		$found = false;

		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				$found = true;

				break;
			}
		}

		if ( ! $found ) {
			return;
		}

		// Set this error handler after loading a plugin to chain its error handler.
		( new self( $this->dirs, $this->levels ) )->set_error_handler();
	}

	/**
	 * Error handler.
	 *
	 * @param int    $level   Error level.
	 * @param string $message Error message.
	 * @param string $file    File produced an error.
	 * @param int    $line    Line number.
	 *
	 * @return bool
	 * @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection
	 */
	public function error_handler( int $level, string $message, string $file, int $line ): bool {
		if ( $this->handling ) {
			// Prevent infinite recursion and fallback to the standard error handler.
			return false;
		}

		$this->handling = true;

		try {
			if ( ( $level & $this->levels ) !== 0 && $this->is_suppressed_file( $file ) ) {
				// Suppress configured error levels from this directory.
				return true;
			}

			// Not a configured error level or directory, use the fallback error handler.
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			return $this->fallback_error_handler( func_get_args() );
		} finally {
			$this->handling = false;
		}
	}

	/**
	 * Temporarily filter Query Monitor's collector for selected translation notices.
	 *
	 * @param string $function_name The function that was called.
	 * @param string $message       A message explaining what has been done incorrectly.
	 * @param string $version       The WordPress version where the message was added.
	 *
	 * @return void
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function action_doing_it_wrong_run( string $function_name, $message, $version ): void {
		$this->qm_callbacks[] = null;

		if ( ! class_exists( 'QM_Collectors' ) || ! $this->is_just_in_time_error( $function_name ) ) {
			return;
		}

		$collector = QM_Collectors::get( 'doing_it_wrong' );

		if ( null === $collector ) {
			return;
		}

		$callback = [ $collector, 'action_doing_it_wrong_run' ];
		$priority = has_action( 'doing_it_wrong_run', $callback );

		if ( false === $priority || $priority <= 0 || PHP_INT_MAX === $priority ) {
			return;
		}

		// A wrapper preserves unrelated notices even when another callback triggers a nested action.
		$wrapper = function ( $function_name, $message, $version ) use ( $callback ): void {
			if ( ! $this->is_just_in_time_error( (string) $function_name ) ) {
				$callback( $function_name, $message, $version );
			}
		};

		$this->qm_callbacks[ count( $this->qm_callbacks ) - 1 ] = [ $callback, $wrapper, $priority ];
		remove_action( 'doing_it_wrong_run', $callback, $priority );
		add_action( 'doing_it_wrong_run', $wrapper, $priority, 3 );
	}

	/**
	 * Restore only the collector callback replaced for the current action.
	 *
	 * @return void
	 */
	public function restore_doing_it_wrong_collector(): void {
		$callbacks = array_pop( $this->qm_callbacks );

		if ( null === $callbacks ) {
			return;
		}

		[ $callback, $wrapper, $priority ] = $callbacks;
		remove_action( 'doing_it_wrong_run', $wrapper, $priority );
		add_action( 'doing_it_wrong_run', $callback, $priority, 3 );
	}

	/**
	 * Suppress selected early translation notices before WordPress triggers a PHP error.
	 *
	 * @param bool|mixed $trigger       Whether to trigger the error.
	 * @param string     $function_name The function that was called.
	 * @param string     $message       A message explaining what has been done incorrectly.
	 * @param string     $version       The WordPress version where the message was added.
	 *
	 * @return bool
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function filter_doing_it_wrong_trigger_error( $trigger, string $function_name, $message, $version ): bool {
		return ! $this->is_just_in_time_error( $function_name ) && $trigger;
	}

	/**
	 * Whether an early translation notice originates in a configured directory.
	 *
	 * @param string $function_name The function that was called.
	 *
	 * @return bool
	 */
	private function is_just_in_time_error( string $function_name ): bool {
		if ( '_load_textdomain_just_in_time' !== $function_name || ( E_USER_NOTICE & $this->levels ) === 0 ) {
			return false;
		}

		// Inspect file paths only: translated messages and text domains need not match directory names.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
			if ( isset( $frame['file'] ) && __FILE__ !== $frame['file'] && $this->is_suppressed_file( $frame['file'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a file belongs to a configured directory.
	 *
	 * @param string $file File path.
	 *
	 * @return bool
	 */
	private function is_suppressed_file( string $file ): bool {
		$normalized_file = str_replace( '\\', '/', $file );

		foreach ( $this->dirs as $dir ) {
			if ( false !== strpos( $normalized_file, $dir ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fallback error handler.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 * @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection
	 */
	private function fallback_error_handler( array $args ): bool {
		return null === $this->previous_error_handler ?
			// Use standard error handler.
			false :
			(bool) call_user_func_array( $this->previous_error_handler, $args );
	}

	/**
	 * Normalize dirs.
	 *
	 * @return void
	 */
	private function normalize_dirs(): void {
		$this->dirs = array_filter(
			array_map(
				static function ( $dir ) {
					return is_string( $dir ) ? str_replace( '\\', '/', trim( $dir ) ) : '';
				},
				$this->dirs
			)
		);
	}
}

( new MUErrorHandler() )->init();
