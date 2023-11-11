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
		$this->dirs = $this->init_dirs();
		$this->init_hooks();
	}

	/**
	 * Init dirs to suppress messages from.
	 *
	 * @return array Default dirs.
	 */
	private function init_dirs(): array {
		$dirs = [
			// WP Core.
			ABSPATH . WPINC . '/', // WordPress wp-includes.
			ABSPATH . 'wp-admin/', // WordPress wp-admin.
			// Known libraries in different plugins producing deprecated messages.
			'/vendor/rmccue/requests/', // Requests library used in WP-CLI.
			'/vendor/woocommerce/action-scheduler/', // Action Scheduler.
			// Plugins producing deprecated messages.
			WP_PLUGIN_DIR . '/backwpup/', // BackWPup.
			WP_PLUGIN_DIR . '/business-reviews-bundle/', // Business review bundle.
			WP_PLUGIN_DIR . '/cloudflare/', // Cloudflare.
			WP_PLUGIN_DIR . '/easy-digital-downloads/', // Easy Digital Downloads.
			WP_PLUGIN_DIR . '/google-site-kit/', // Google Site Kit.
			WP_PLUGIN_DIR . '/gravityforms/', // Gravity Forms.
			WP_PLUGIN_DIR . '/gravityperks/', // Gravity Perks.
			WP_PLUGIN_DIR . '/mailpoet/', // MailPoet.
			WP_PLUGIN_DIR . '/seo-by-rank-math/', // Rank Math SEO.
			WP_PLUGIN_DIR . '/sitepress-multilingual-cms/', // WPML.
			WP_PLUGIN_DIR . '/woocommerce/', // WooCommerce.
			WP_PLUGIN_DIR . '/wp-google-places-review-slider/', // Google places review slider.
			WP_PLUGIN_DIR . '/wp-job-openings/', // Job openings.
			WP_PLUGIN_DIR . '/wp-seo-multilingual/', // WPML SEO.
			WP_PLUGIN_DIR . '/wp-super-cache/', // WP Super Cache.
			// Themes producing deprecated messages.
			WP_CONTENT_DIR . '/themes/Divi/', // Divi Theme.
		];

		$abspath = str_replace( '\\', '/', realpath( ABSPATH ) );

		return array_map(
			static function ( $dir ) use ( $abspath ) {

				return str_replace( [ '\\', $abspath ], [ '/', '' ], $dir );
			},
			$dirs
		);
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
