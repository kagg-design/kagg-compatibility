<?php
/**
 * Error-handler to be used as a mu-plugin.
 *
 * @package kagg/compatibility
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection AutoloadingIssuesInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace KAGG\Compatibility;

/**
 * Class ErrorHandler
 */
class ErrorHandler {

	/**
	 * Directories where can deprecation error occurs.
	 *
	 * @var string[]
	 */
	private array $dirs;

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->dirs = [
			ABSPATH . WPINC . '/', // WordPress wp-includes.
			ABSPATH . 'wp-admin/', // WordPress wp-admin.
			'/action-scheduler/', // Action Scheduler.
			'/vendor/rmccue/requests/', // Requests library used in WP-CLI.
			'/plugins/woocommerce/', // WooCommerce, many files.
		];

		$this->dirs = array_map(
			static function ( $dir ) {
				return str_replace( DIRECTORY_SEPARATOR, '/', $dir );
			},
			$this->dirs
		);

		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( [ $this, 'error_handler' ] );

		add_action( 'admin_head', [ $this, 'admin_head' ] );
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
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function error_handler( int $level, string $message, string $file, int $line ): bool {
		if ( E_DEPRECATED !== $level ) {
			// Use standard error handler.
			return false;
		}

		$file = str_replace( DIRECTORY_SEPARATOR, '/', $file );

		foreach ( $this->dirs as $dir ) {
			if ( str_contains( $file, $dir ) ) {
				// Suppress deprecated errors from this directory.
				return true;
			}
		}

		// Use standard error handler.
		return false;
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
}

( new ErrorHandler() )->init();
