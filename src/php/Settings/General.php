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
	protected const HANDLE = 'kagg-compatibility-general';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'KAGGCompatibilityGeneralObject';

	/**
	 * Reset action.
	 */
	private const RESET_ACTION = 'kagg-compatibility-general-reset';

	/**
	 * Parameters section id.
	 */
	private const SECTION_PARAMETERS = 'parameters';

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
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::RESET_ACTION, [ $this, 'reset' ] );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields(): void {
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
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		parent::setup_fields();
	}

	/**
	 * Show settings page.
	 */
	public function settings_page(): void {
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
	public function section_callback( array $arguments ): void {
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
	public function admin_enqueue_scripts(): void {
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
	public function reset(): void {
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
			'/rmccue/requests/', // Requests library used in WP-CLI.
			'/action-scheduler/', // Action Scheduler.
			// Plugins producing deprecated messages.
			WP_PLUGIN_DIR . '/advanced-custom-fields-pro/', // Advanced Custom Fields Pro.
			WP_PLUGIN_DIR . '/backwpup/', // BackWPup.
			WP_PLUGIN_DIR . '/business-reviews-bundle/', // Business review bundle.
			WP_PLUGIN_DIR . '/cloudflare/', // Cloudflare.
			WP_PLUGIN_DIR . '/easy-digital-downloads/', // Easy Digital Downloads.
			WP_PLUGIN_DIR . '/elementor/', // Elementor.
			WP_PLUGIN_DIR . '/elementor-pro/', // Elementor Pro.
			WP_PLUGIN_DIR . '/google-site-kit/', // Google Site Kit.
			WP_PLUGIN_DIR . '/gravityforms/', // Gravity Forms.
			WP_PLUGIN_DIR . '/gravityperks/', // Gravity Perks.
			WP_PLUGIN_DIR . '/mailpoet/', // MailPoet.
			WP_PLUGIN_DIR . '/pagelayer/', // Pagelayer.
			WP_PLUGIN_DIR . '/seo-by-rank-math/', // Rank Math SEO.
			WP_PLUGIN_DIR . '/sitepress-multilingual-cms/', // WPML.
			WP_PLUGIN_DIR . '/woocommerce/', // WooCommerce.
			WP_PLUGIN_DIR . '/wp-google-places-review-slider/', // Google places review slider.
			WP_PLUGIN_DIR . '/wp-job-openings/', // Job openings.
			WP_PLUGIN_DIR . '/wp-seo-multilingual/', // WPML SEO.
			WP_PLUGIN_DIR . '/wp-super-cache/', // WP Super Cache.
			// Themes producing deprecated messages.
			WP_CONTENT_DIR . '/themes/Avada/', // Avada Theme.
			WP_CONTENT_DIR . '/themes/Divi/', // Divi Theme.
			WP_CONTENT_DIR . '/themes/popularfx/', // Popular FX Theme.
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
	private function print_section_header( string $id, string $title ): void {
		?>
		<h3 class="<?php echo esc_attr( self::SLUG ); ?>-section-<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $title ); ?>
		</h3>
		<?php
	}
}
