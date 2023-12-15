<?php
/**
 * General class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Settings;

use KAGG\Compatibility\Settings\Abstracts\SettingsBase;

/**
 * Class General
 *
 * Settings page "General".
 */
class General extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'kagg-compatibility-general';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'KAGGCompatibilityGeneralObject';

	/**
	 * Reset action.
	 */
	const RESET_ACTION = 'kagg-compatibility-general-reset';

	/**
	 * Parameters section id.
	 */
	const SECTION_PARAMETERS = 'parameters';

	/**
	 * Live mode.
	 */
	const MODE_LIVE = 'live';

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'General', 'kagg-compatibility' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'general';
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::RESET_ACTION, [ $this, 'reset' ] );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'dirs' => [
				'label'   => __( 'Directories', 'kagg-compatibility' ),
				'type'    => 'textarea',
				'section' => self::SECTION_PARAMETERS,
			],
		];
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		parent::setup_fields();
	}

	/**
	 * Show settings page.
	 */
	public function settings_page() {
		parent::settings_page();

		submit_button(
			__( 'Reset to Default', 'cyr2lat' ),
			'secondary',
			'kagg-compatibility-reset-button',
			false
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ) {
		if ( self::SECTION_PARAMETERS !== $arguments['id'] ) {
			return;
		}

		?>
		<h2>
			<?php echo esc_html( $this->page_title() ); ?>
		</h2>
		<div id="<?php echo esc_attr( self::SLUG ); ?>-message"></div>
		<?php

		$this->print_section_header( $arguments['id'], __( 'Parameters', 'kagg-compatibility' ) );
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script(
			self::HANDLE,
			constant( 'KAGG_COMPATIBILITY_URL' ) . "/assets/js/general$this->min_prefix.js",
			[ 'jquery' ],
			constant( 'KAGG_COMPATIBILITY_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'resetAction'       => self::RESET_ACTION,
				'nonce'             => wp_create_nonce( self::RESET_ACTION ),
				'resetConfirmation' => __( 'Are you sure?', 'kagg-compatibility' ),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'KAGG_COMPATIBILITY_URL' ) . "/assets/css/general$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'KAGG_COMPATIBILITY_VERSION' )
		);
	}

	/**
	 * Reset settings to default.
	 *
	 * @return void
	 */
	public function reset() {
		$this->update_option( 'dirs', implode( "\n", $this->init_dirs() ) );

		wp_send_json_success();
	}

	/**
	 * Init dirs to suppress messages from.
	 *
	 * @return array Default dirs.
	 */
	public function init_dirs(): array {
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
	 * Print section header.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 *
	 * @return void
	 */
	private function print_section_header( string $id, string $title ) {
		?>
		<h3 class="<?php echo esc_attr( self::SLUG ); ?>-section-<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $title ); ?>
		</h3>
		<?php
	}
}
