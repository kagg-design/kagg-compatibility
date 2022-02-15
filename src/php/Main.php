<?php
/**
 * Main class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility;

use WP_Filesystem_Base;

/**
 * Class Main.
 */
class Main {

	/**
	 * Error handler filename.
	 */
	const ERROR_HANDLER_FILENAME = 'kagg-compatibility-error-handler.php';

	/**
	 * Error handler source path.
	 *
	 * @var string
	 */
	private $error_handler_source;

	/**
	 * Error handler destination path.
	 *
	 * @var string
	 */
	private $error_handler_destination;

	/**
	 * AdminNotices instance.
	 *
	 * @var AdminNotices
	 */
	private $admin_notices;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->error_handler_source      = KAGG_COMPATIBILITY_PATH . '/src/php/' . self::ERROR_HANDLER_FILENAME;
		$this->error_handler_destination = WPMU_PLUGIN_DIR . '/' . self::ERROR_HANDLER_FILENAME;
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		global $wp_version;

		$this->admin_notices = new AdminNotices();

		// Plugin works with PHP 8.1+ only.
		if ( PHP_VERSION_ID < 80100 ) {
			$this->admin_notices->add_notice(
				__( 'KAGG Compatibility requires PHP version 8.1 to run.', 'kagg-compatibility' ),
				'notice notice-error'
			);

			return;
		}

		if ( version_compare( $wp_version, '5.9', '<' ) ) {
			$this->admin_notices->add_notice(
				__( 'KAGG Compatibility requires WordPress version 5.9 to run.', 'kagg-compatibility' ),
				'notice notice-error'
			);

			return;
		}

		$this->hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function hooks() {
		register_activation_hook( KAGG_COMPATIBILITY_FILE, [ $this, 'activation_hook' ] );
		register_deactivation_hook( KAGG_COMPATIBILITY_FILE, [ $this, 'deactivation_hook' ] );

		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public function activation_hook() {
		if ( $this->copy_error_handler() ) {
			return;
		}

		$this->load_plugin_textdomain();
		$this->admin_notices->add_notice(
			__( 'Cannot install mu-plugin with error handler.', 'kagg-compatibility' ),
			'notice notice-error'
		);
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public function deactivation_hook() {
		if ( $this->delete_error_handler() ) {
			return;
		}

		$this->load_plugin_textdomain();
		$this->admin_notices->add_notice(
			__( 'Cannot delete mu-plugin with error handler.', 'kagg-compatibility' ),
			'notice notice-error'
		);
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		global $l10n;

		$domain = 'kagg-compatibility';

		if ( isset( $l10n[ $domain ] ) ) {
			return;
		}

		load_plugin_textdomain(
			$domain,
			false,
			dirname( plugin_basename( KAGG_COMPATIBILITY_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Get direct filesystem.
	 *
	 * @todo Add support for other filesystems.
	 *
	 * @return WP_Filesystem_Base|null
	 */
	private function get_filesystem_direct() {

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
	private function copy_error_handler() {
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
	private function delete_error_handler() {
		$filesystem = $this->get_filesystem_direct();

		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->delete( $this->error_handler_destination );
	}
}
