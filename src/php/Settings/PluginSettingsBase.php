<?php
/**
 * PluginSettingsBase class file.
 *
 * @package kagg/compatibility
 */

namespace KAGG\Compatibility\Settings;

use KAGG\Compatibility\Settings\Abstracts\SettingsBase;

/**
 * Class PluginSettingsBase
 *
 * Extends general SettingsBase suitable for any plugin with current plugin-related methods.
 */
abstract class PluginSettingsBase extends SettingsBase {

	/**
	 * Plugin slug.
	 */
	const SLUG = 'kagg-compatibility';

	/**
	 * Plugin name.
	 * By default, the slug in snake case format.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Constant prefix.
	 * By default, the slug in upper case and snake case format.
	 *
	 * @var string
	 */
	protected $constant_prefix = '';

	/**
	 * Constructor.
	 *
	 * @param array|null $tabs Tabs of this settings page.
	 */
	public function __construct( $tabs = [] ) {
		if ( ! $this->name ) {
			$this->name = str_replace( '-', '_', self::SLUG );
		}

		if ( ! $this->constant_prefix ) {
			$this->constant_prefix = strtoupper( str_replace( '-', '_', self::SLUG ) );
		}

		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'update_footer' ], PHP_INT_MAX );

		parent::__construct( $tabs );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title(): string {
		return __( 'KAGG Compatibility', 'kagg-compatibility' );
	}

	/**
	 * Get screen id.
	 *
	 * @return string
	 */
	public function screen_id(): string {
		return 'settings_page_' . $this->name;
	}

	/**
	 * Get an option group.
	 *
	 * @return string
	 */
	protected function option_group(): string {
		return $this->name . '_group';
	}

	/**
	 * Get option page.
	 *
	 * @return string
	 */
	protected function option_page(): string {
		return $this->name;
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function option_name(): string {
		return $this->name . '_settings';
	}

	/**
	 * Get plugin base name.
	 *
	 * @return string
	 */
	protected function plugin_basename(): string {
		return plugin_basename( constant( $this->constant_prefix . '_FILE' ) );
	}

	/**
	 * Get plugin url.
	 *
	 * @return string
	 */
	protected function plugin_url(): string {
		return constant( $this->constant_prefix . '_URL' );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	protected function plugin_version(): string {
		return constant( $this->constant_prefix . '_VERSION' );
	}

	/**
	 * Get settings link label.
	 *
	 * @return string
	 */
	protected function settings_link_label(): string {
		return __( 'KAGG Compatibility Settings', 'kagg-compatibility' );
	}

	/**
	 * Get settings link text.
	 *
	 * @return string
	 */
	protected function settings_link_text(): string {
		return __( 'Settings', 'kagg-compatibility' );
	}

	/**
	 * Get text domain.
	 *
	 * @return string
	 */
	protected function text_domain(): string {
		return self::SLUG;
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		$prefix = $this->option_page() . '-' . $this->section_title() . '-';

		foreach ( $this->form_fields as $key => $form_field ) {
			if ( ! isset( $form_field['class'] ) ) {
				$this->form_fields[ $key ]['class'] = str_replace( '_', '-', $prefix . $key );
			}
		}

		parent::setup_fields();
	}

	/**
	 * Show settings page.
	 */
	public function settings_page() {
		?>
		<h1 class="kagg-compatibility-settings-header">
			<img
					src="<?php echo esc_url( constant( $this->constant_prefix . '_URL' ) . '/assets/images/icon.svg' ); ?>"
					alt="KAGG Compatibility Logo"
					class="<?php echo esc_attr( self::SLUG ); ?>-logo"
			/>
			<?php esc_html_e( 'KAGG Compatibility', 'kagg-compatibility' ); ?>
		</h1>

		<form
				id="<?php echo esc_attr( self::SLUG ); ?>-options"
				class="<?php echo esc_attr( self::SLUG . '-' . $this->section_title() ); ?>"
				action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
				method="post">
			<?php
			do_settings_sections( $this->option_page() ); // Sections with options.
			settings_fields( $this->option_group() ); // Hidden protection fields.

			if ( ! empty( $this->form_fields ) ) {
				submit_button();
			}
			?>
		</form>
		<?php
	}

	/**
	 * When a user is on the plugin admin page, display footer text that graciously asks them to rate us.
	 *
	 * @param string|mixed $text Footer text.
	 *
	 * @return string|mixed
	 * @noinspection HtmlUnknownTarget
	 */
	public function admin_footer_text( $text ) {
		if ( ! $this->is_options_screen( [] ) ) {
			return $text;
		}

		$slug = self::SLUG;

		$url = "https://wordpress.org/support/plugin/$slug/reviews/?filter=5#new-post";

		return wp_kses(
			sprintf(
			/* translators: 1: plugin name, 2: wp.org review link with stars, 3: wp.org review link with text. */
				__( 'Please rate %1$s %2$s on %3$s. Thank you!', 'kagg-compatibility' ),
				'<strong>KAGG Compatibility</strong>',
				sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">★★★★★</a>',
					$url
				),
				sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">WordPress.org</a>',
					$url
				)
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		);
	}

	/**
	 * Show a plugin version in the update footer.
	 *
	 * @param string|mixed $content The content that will be printed.
	 *
	 * @return string|mixed
	 */
	public function update_footer( $content ) {
		if ( ! $this->is_options_screen() ) {
			return $content;
		}

		return sprintf(
		/* translators: 1: plugin version. */
			__( 'Version %s', 'kagg-compatibility' ),
			constant( $this->constant_prefix . '_VERSION' )
		);
	}
}
