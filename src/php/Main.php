<?php
/**
 * Main class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility;

use KAGG\Compatibility\Settings\General;
use KAGG\Compatibility\Settings\Settings;
use WP_Filesystem_Base;

/**
 * Class Main.
 */
class Main {

	/**
	 * Error handler filename.
	 */
	private const MU_FILENAME = 'kagg-compatibility-error-handler.php';

	/**
	 * Error handler source path.
	 *
	 * @var string
	 */
	private string $error_handler_source;

	/**
	 * Error handler destination path.
	 *
	 * @var string
	 */
	private string $error_handler_destination;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->error_handler_source      = __DIR__ . '/' . self::MU_FILENAME;
		$this->error_handler_destination = WPMU_PLUGIN_DIR . '/' . self::MU_FILENAME;
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		$this->settings = new Settings(
			[
				'KAGG Compatibility' => [ General::class ],
			]
		);

		register_activation_hook( KAGG_COMPATIBILITY_FILE, [ $this, 'activation_hook' ] );
		register_deactivation_hook( KAGG_COMPATIBILITY_FILE, [ $this, 'deactivation_hook' ] );

		add_action( 'plugins_loaded', [ $this, 'load' ] );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function activation_hook(): void {
		$dirs = $this->settings->get( 'dirs', [] );

		if ( ! $dirs ) {
			$tabs = $this->settings->get_tabs();

			foreach ( $tabs as $tab ) {
				if ( $tab instanceof General ) {
					$tab->update_option( 'dirs', implode( "\n", $this->init_dirs() ) );
				}
			}
		}

		if ( ! $this->copy_error_handler() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Cannot install mu-plugin with error handler.' );
		}
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function deactivation_hook(): void {
		if ( ! $this->delete_error_handler() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Cannot delete mu-plugin with error handler.' );
		}
	}

	/**
	 * Load plugin.
	 *
	 * @return void
	 */
	public function load(): void {
		global $wp_version;

		load_plugin_textdomain(
			'kagg-compatibility',
			false,
			dirname( plugin_basename( KAGG_COMPATIBILITY_FILE ) ) . '/languages/'
		);

		$admin_notices = new AdminNotices();

		// The plugin works with PHP 8.1+ only.
		if ( PHP_VERSION_ID < 80100 ) {
			$admin_notices->add_notice(
				__( 'KAGG Compatibility requires PHP version 8.1 to run.', 'kagg-compatibility' ),
				'notice notice-error'
			);

			return;
		}

		if ( version_compare( $wp_version, '5.9', '<' ) ) {
			$admin_notices->add_notice(
				__( 'KAGG Compatibility requires WordPress version 5.9 to run.', 'kagg-compatibility' ),
				'notice notice-error'
			);
		}
	}

	/**
	 * Get direct filesystem.
	 *
	 * @todo Add support for other filesystems.
	 *
	 * @return WP_Filesystem_Base|null
	 */
	private function get_filesystem_direct(): ?WP_Filesystem_Base {

		global $wp_filesystem;

		if ( ! $wp_filesystem && ! WP_Filesystem() ) {
			return null;
		}

		if ( 'direct' !== $wp_filesystem->method ) {
			return null;
		}

		return $wp_filesystem;
	}

	/**
	 * Copy error handler file to the mu-plugins folder.
	 *
	 * @return bool
	 */
	private function copy_error_handler(): bool {
		$filesystem = $this->get_filesystem_direct();

		if ( ! $filesystem ) {
			return false;
		}

		if ( ! $filesystem->is_dir( WPMU_PLUGIN_DIR ) && ! $filesystem->mkdir( WPMU_PLUGIN_DIR ) ) {
			return false;
		}

		return $filesystem->copy(
			$this->error_handler_source,
			$this->error_handler_destination,
			true
		);
	}

	/**
	 * Delete error handler file.
	 *
	 * @return bool
	 */
	private function delete_error_handler(): bool {
		$filesystem = $this->get_filesystem_direct();

		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->delete( $this->error_handler_destination );
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
}
