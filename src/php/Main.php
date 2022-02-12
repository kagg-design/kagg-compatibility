<?php
/**
 * Main class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility;

use WP_Filesystem_Direct;

/**
 * Class Main.
 */
class Main {

	/**
	 * Error handler filename.
	 */
	const ERROR_HANDLER_FILENAME = 'kagg-compatibility-error-handler.php';

	/**
	 * Error handler source path and filename.
	 *
	 * @var string
	 */
	private $error_handler_source;

	/**
	 * Error handler destination path and filename.
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
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public function activation_hook() {
		$result     = false;
		$filesystem = $this->get_filesystem();

		if ( $filesystem ) {
			$result = $filesystem->copy(
				$this->error_handler_source,
				$this->error_handler_destination,
				true
			);
		}

		if ( ! $result ) {
			$this->admin_notices->add_notice(
				__( 'Cannot install mu-plugin with error handler.', 'kagg-compatibility' ),
				'notice notice-error'
			);
		}
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public function deactivation_hook() {
		$result     = false;
		$filesystem = $this->get_filesystem();

		if ( $filesystem ) {
			$result = $filesystem->delete( $this->error_handler_destination );
		}

		if ( ! $result ) {
			$this->admin_notices->add_notice(
				__( 'Cannot delete mu-plugin with error handler.', 'kagg-compatibility' ),
				'notice notice-error'
			);
		}
	}

	/**
	 * Get direct filesystem.
	 *
	 * @return WP_Filesystem_Direct|null
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		$filesystem = $wp_filesystem;

		// @todo add support for other filesystems.
		if ( ! $filesystem instanceof WP_Filesystem_Direct ) {
			$method            = 'direct';
			$class_file_prefix = ABSPATH . 'wp-admin/includes/class-wp-filesystem-';
			$abstraction_file  = apply_filters( 'filesystem_method_file', $class_file_prefix . $method . '.php', $method );

			if ( ! file_exists( $abstraction_file ) ) {
				return null;
			}

			require_once $class_file_prefix . 'base.php';
			require_once $abstraction_file;

			if ( get_filesystem_method() !== 'direct' ) {
				return null;
			}

			$filesystem = new WP_Filesystem_Direct( null );
		}

		return $filesystem;
	}
}
