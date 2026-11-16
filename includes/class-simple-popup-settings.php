<?php
/**
 * Global "Settings" screen: the pause (in seconds) enforced between the
 * closing of one popup and the opening of the next, on the same page.
 * Read everywhere else through `get_gap()`.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Settings {

	const OPTION_GAP   = 'simple_popup_gap';
	const DEFAULT_GAP  = 30;
	const PAGE_SLUG    = 'simple-popup-settings';
	const OPTION_GROUP = 'simple_popup_settings';
	const SECTION      = 'simple_popup_settings_main';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
	}

	/**
	 * @return int Minimum number of seconds between the closing of a popup
	 *             and the opening of the next one, on the same page.
	 */
	public static function get_gap() {
		return (int) get_option( self::OPTION_GAP, self::DEFAULT_GAP );
	}

	public static function register_page() {
		add_submenu_page(
			'edit.php?post_type=' . Simple_Popup_CPT::POST_TYPE,
			__( 'Popups settings', 'simple-popup' ),
			__( 'Settings', 'simple-popup' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_GAP,
			array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_GAP,
				'sanitize_callback' => 'absint',
			)
		);

		add_settings_section( self::SECTION, '', '__return_false', self::PAGE_SLUG );

		add_settings_field(
			self::OPTION_GAP,
			__( 'Pause between two popups (seconds)', 'simple-popup' ),
			array( __CLASS__, 'render_gap_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);
	}

	public static function render_gap_field() {
		?>
		<input
			type="number"
			min="0"
			step="1"
			name="<?php echo esc_attr( self::OPTION_GAP ); ?>"
			value="<?php echo esc_attr( self::get_gap() ); ?>"
			class="small-text"
		/>
		<p class="description">
			<?php
			esc_html_e(
				'Minimum number of seconds between the closing of one popup and the opening of the next, on the same page. A popup whose moment arrives earlier simply waits: it opens as soon as the pause ends. 0 means no pause.',
				'simple-popup'
			);
			?>
		</p>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
