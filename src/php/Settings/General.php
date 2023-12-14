<?php
/**
 * General class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Settings;

use KAGG\Settings\Abstracts\SettingsBase;

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
	 * Directories section id.
	 */
	const SECTION_DIRECTORIES = 'directories';

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
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'dirs' => [
				'label'   => __( 'Directories', 'kagg-compatibility' ),
				'type'    => 'textarea',
				'section' => self::SECTION_DIRECTORIES,
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
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ) {
		if ( self::SECTION_DIRECTORIES !== $arguments['id'] ) {
			return;
		}

		?>
		<h2>
			<?php echo esc_html( $this->page_title() ); ?>
		</h2>
		<div id="<?php echo esc_attr( self::SLUG ); ?>-message"></div>
		<?php

		$this->print_section_header( $arguments['id'], __( 'Directories', 'kagg-compatibility' ) );
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

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
//		wp_enqueue_script(
//			self::HANDLE,
//			constant( 'HCAPTCHA_URL' ) . "/assets/js/general$this->min_prefix.js",
//			[ 'jquery' ],
//			constant( 'HCAPTCHA_VERSION' ),
//			true
//		);

//		wp_localize_script(
//			self::HANDLE,
//			self::OBJECT,
//			[
//				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
//				'checkConfigAction' => self::RESET_ACTION,
//				'nonce'             => wp_create_nonce( self::RESET_ACTION ),
//				'ResetNotice'       => $check_config_notice,
//			]
//		);
//
		wp_enqueue_style(
			self::HANDLE,
			constant( 'KAGG_COMPATIBILITY_URL' ) . "/assets/css/general$this->min_prefix.css",
			[ static::SLUG . '-' . SettingsBase::HANDLE ],
			constant( 'KAGG_COMPATIBILITY_VERSION' )
		);
	}
}
