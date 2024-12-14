<?php
/**
 * The error handler to suppress error messages from vendor directories,
 * WodPress Core, and some plugins.
 *
 * @package kagg/compatibility
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection AutoloadingIssuesInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace KAGG\Compatibility;

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
	private $dirs = [];

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
	private $levels;

	/**
	 * Whether the error handler is handling an error.
	 *
	 * @var bool
	 */
	private $handling = false;

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
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public function init(): void {
		if ( defined( 'KAGG_DISABLE_ERROR_HANDLER' ) && KAGG_DISABLE_ERROR_HANDLER ) {
			return;
		}

		$option     = get_option( self::OPTION, [] );
		$this->dirs = empty( $option[ self::OPTION_KEY ] ) ? [] : explode( "\n", $option[ self::OPTION_KEY ] );

		$this->normalize_dirs();

		/**
		 * Allow modifying the list of dirs to suppress messages from.
		 *
		 * @param bool $dirs The list of dirs to suppress messages from.
		 */
		$this->dirs = (array) apply_filters( 'kagg_compatibility_dirs', $this->dirs );

		$this->normalize_dirs();

		if ( ! $this->dirs ) {
			return;
		}

		/**
		 * Allow modifying the levels of messages to suppress.
		 *
		 * @param bool $levels Error levels of messages to suppress.
		 */
		$this->levels = (int) apply_filters(
			'kagg_compatibility_levels',
			E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED
		);

		if ( 0 === $this->levels ) {
			return;
		}

		$this->set_error_handler();
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_head', [ $this, 'admin_head' ] );

		add_action(
			'action_scheduler_before_execute',
			[ new self( $this->dirs, $this->levels ), 'set_error_handler' ],
			1000
		);

		// Some plugins destroy an error handler chain. Set the error handler again upon loading them.
		add_action( 'plugin_loaded', [ $this, 'plugin_loaded' ] );
	}

	/**
	 * Set error handler and save original.
	 */
	public function set_error_handler(): void {

		// To chain error handlers, we must not specify the second argument and catch all errors in our handler.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$this->previous_error_handler = set_error_handler( [ $this, 'error_handler' ] );
	}

	/**
	 * Clear error caused by xdebug with PHP 8.1.
	 * This error leads to adding .php-error class (margin-top: 2em;) to the #adminmenuwrap.
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
		$plugin = str_replace( DIRECTORY_SEPARATOR, '/', $plugin );

		// Plugins that destroy an error handler chain.
		$plugin_files = [
			'query-monitor/query-monitor.php', // Query Monitor.
			'uncanny-automator/uncanny-automator.php', // Uncanny Automator.
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
		( new self( $this->dirs, $this->levels ) )->set_error_handler( $this->dirs, $this->levels );
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
			$this->handling = false;

			// Prevent infinite recursion and fallback to standard error handler.
			return false;
		}

		$this->handling = true;

		if ( ( $level & $this->levels ) === 0 ) {
			// Not served error level, use fallback error handler.
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			return $this->fallback_error_handler( func_get_args() );
		}

		// Process error.
		$normalized_file = str_replace( DIRECTORY_SEPARATOR, '/', $file );

		foreach ( $this->dirs as $dir ) {
			if ( str_contains( $normalized_file, $dir ) ) {
				$this->handling = false;

				// Suppress deprecated errors from this directory.
				return true;
			}
		}

		// Not served directory, use fallback error handler.
		// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
		return $this->fallback_error_handler( func_get_args() );
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
		$result = null === $this->previous_error_handler ?
			// Use standard error handler.
			false :
			(bool) call_user_func_array( $this->previous_error_handler, $args );

		$this->handling = false;

		return $result;
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
					return str_replace( DIRECTORY_SEPARATOR, '/', trim( $dir ) );
				},
				$this->dirs
			)
		);
	}
}

( new MUErrorHandler() )->init();
