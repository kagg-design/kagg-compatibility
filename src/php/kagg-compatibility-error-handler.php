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
	private const OPTION = 'kagg-compatibility-settings';

	/**
	 * Error handler option key.
	 */
	private const OPTION_KEY = 'dirs';

	/**
	 * Directories where can deprecation error occur.
	 *
	 * @var string[]
	 */
	private array $dirs = [];

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
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$option = get_option( self::OPTION, [] );

		$this->dirs = empty( $option[ self::OPTION_KEY ] ) ? [] : $option[ self::OPTION_KEY ];

		if ( ! $this->dirs ) {
			return;
		}

		$this->dirs = array_map(
			static function ( $dir ) {

				return str_replace( DIRECTORY_SEPARATOR, '/', $dir );
			},
			$this->dirs
		);

		/**
		 * Allow modifying the levels of messages to suppress.
		 *
		 * @param bool $level Error levels of messages to suppress.
		 */
		$this->levels = (int) apply_filters(
			'wpf_error_handler_level',
			E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED
		);

		// Set this error handler early to catch any errors on the plugin loading stage.
		// To chain error handlers, we must not specify the second argument and catch all errors in our handler.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$this->previous_error_handler = set_error_handler( [ $this, 'error_handler' ] );

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		if ( current_action() === 'plugins_loaded' ) {
			return;
		}

		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_action( 'plugin_loaded', [ $this, 'qm_loaded' ] );
	}

	/**
	 * Clear error caused by xdebug with PHP 8.1.
	 * This error leads to adding .php-error class (2em margin-top) to the #adminmenuwrap.
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
	 * QM loaded hook.
	 *
	 * @param string $plugin Full path to the plugin's main file.
	 *
	 * @return void
	 */
	public function qm_loaded( string $plugin ): void {

		if ( ! str_contains( $plugin, 'query-monitor/query-monitor.php' ) ) {
			return;
		}

		// Set this error handler after loading the Query Monitor plugin to chain its error handler.
		( new self() )->init();
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
	 */
	public function error_handler( int $level, string $message, string $file, int $line ): bool {
		if ( ( $level & $this->levels ) === 0 ) {
			// Use standard error handler.
			return null === $this->previous_error_handler ?
				false :
				// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
				call_user_func_array( $this->previous_error_handler, func_get_args() );
		}

		$normalized_file = str_replace( DIRECTORY_SEPARATOR, '/', $file );

		foreach ( $this->dirs as $dir ) {
			if ( str_contains( $normalized_file, $dir ) ) {
				// Suppress deprecated errors from this directory.
				return true;
			}
		}

		// Use standard error handler.
		return null === $this->previous_error_handler ?
			false :
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			call_user_func_array( $this->previous_error_handler, func_get_args() );
	}
}

( new MUErrorHandler() )->init();
