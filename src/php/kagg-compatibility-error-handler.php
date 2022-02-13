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
	private $dirs;

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		$this->dirs = [
			ABSPATH . WPINC,
			ABSPATH . 'wp-admin',
			'/vendor/woocommerce/action-scheduler',
		];

		$this->dirs = array_map(
			static function( $dir ) {
				return trailingslashit(
					str_replace( DIRECTORY_SEPARATOR, '/', $dir )
				);
			},
			$this->dirs
		);

		$this->init_hooks();
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
	public function error_handler( $level, $message, $file, $line ) {
		if ( E_DEPRECATED !== $level ) {
			// Use standard error handler.
			return false;
		}

		$file = str_replace( DIRECTORY_SEPARATOR, '/', $file );

		foreach ( $this->dirs as $dir ) {
			if ( strpos( $file, $dir ) !== false ) {
				// Suppress deprecated errors from this directory.
				return true;
			}
		}

		// Use standard error handler.
		return false;
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( [ $this, 'error_handler' ] );
	}
}

( new ErrorHandler() )->init();
