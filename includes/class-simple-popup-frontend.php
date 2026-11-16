<?php
/**
 * Front-end rendering: prints the published and active popups (filtered to
 * the current Polylang language, if any) inside `<dialog>` markup in the
 * footer, ordered the same way as the admin list (menu order, then date).
 *
 * Also handles the `?simple_popup_preview={ID}` link used by editors to test
 * a single popup (published or not) in isolation.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Frontend {

	const PREVIEW_QUERY_VAR = 'simple_popup_preview';

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_popups' ) );
	}

	/**
	 * Builds the preview link for a popup: the home page of its Polylang
	 * language when Polylang is active, else the default home page, with
	 * the preview query var appended.
	 *
	 * @param int $post_id Popup post ID.
	 * @return string
	 */
	public static function get_preview_url( $post_id ) {
		$home = home_url( '/' );

		if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_home_url' ) ) {
			$language = pll_get_post_language( $post_id );

			if ( $language ) {
				$home = pll_home_url( $language );
			}
		}

		return add_query_arg( self::PREVIEW_QUERY_VAR, $post_id, $home );
	}

	/**
	 * Returns the popup requested via `?simple_popup_preview={ID}`, if the
	 * current request is a legitimate preview: the post exists, is a
	 * `simple_popup` (any status but trash), and the current user may edit
	 * it. Returns null otherwise, so the page renders normally.
	 *
	 * @return WP_Post|null
	 */
	private static function get_preview_popup() {
		if ( ! isset( $_GET[ self::PREVIEW_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview link, no state change.
			return null;
		}

		$post_id = absint( wp_unslash( $_GET[ self::PREVIEW_QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id ) {
			return null;
		}

		$popup = get_post( $post_id );

		if ( ! $popup || Simple_Popup_CPT::POST_TYPE !== $popup->post_type || 'trash' === $popup->post_status ) {
			return null;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}

		return $popup;
	}

	/**
	 * @return WP_Post[]
	 */
	private static function get_displayable_popups() {
		$args = array(
			'post_type'      => Simple_Popup_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'no_found_rows'  => true,
			'meta_key'       => '_simple_popup_active',
			'meta_value'     => '1',
		);

		// Explicitly restrict to the current language: don't rely on Polylang's
		// automatic `pre_get_posts` filtering, which doesn't apply to every
		// secondary `WP_Query`. No fallback to another language on purpose.
		if ( function_exists( 'pll_is_translated_post_type' ) && function_exists( 'pll_current_language' )
			&& pll_is_translated_post_type( Simple_Popup_CPT::POST_TYPE )
		) {
			$current_language = pll_current_language();

			if ( $current_language ) {
				$args['lang'] = $current_language;
			}
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}

	public static function render_popups() {
		$preview_popup = self::get_preview_popup();

		if ( $preview_popup ) {
			self::enqueue_assets();
			self::render_popup( $preview_popup, true );
			return;
		}

		$popups = self::get_displayable_popups();

		if ( empty( $popups ) ) {
			return;
		}

		$displayable = array();

		foreach ( $popups as $popup ) {
			/**
			 * Filters whether a given popup should be rendered in the current
			 * context (e.g. the theme can exclude a page template).
			 *
			 * @param bool    $display Whether to display the popup. Default true.
			 * @param WP_Post $popup   The popup post.
			 */
			if ( apply_filters( 'simple_popup_should_display', true, $popup ) ) {
				$displayable[] = $popup;
			}
		}

		if ( empty( $displayable ) ) {
			return;
		}

		self::enqueue_assets();

		foreach ( $displayable as $popup ) {
			self::render_popup( $popup, false );
		}
	}

	/**
	 * Enqueues the public assets and passes the global pause (gap) setting,
	 * in seconds, to the script as `window.simplePopupConfig`.
	 */
	private static function enqueue_assets() {
		wp_enqueue_script( Simple_Popup_Assets::PUBLIC_SCRIPT_HANDLE );
		wp_enqueue_style( Simple_Popup_Assets::PUBLIC_STYLE_HANDLE );

		$gap = class_exists( 'Simple_Popup_Settings' ) ? (int) Simple_Popup_Settings::get_gap() : 30;

		wp_add_inline_script(
			Simple_Popup_Assets::PUBLIC_SCRIPT_HANDLE,
			'window.simplePopupConfig = ' . wp_json_encode( array( 'gap' => max( 0, $gap ) ) ) . ';',
			'before'
		);
	}

	private static function render_popup( $popup, $is_preview = false ) {
		$id                  = $popup->ID;
		$trigger             = get_post_meta( $id, '_simple_popup_trigger', true );
		$delay               = (int) get_post_meta( $id, '_simple_popup_delay', true );
		$frequency           = get_post_meta( $id, '_simple_popup_frequency', true );
		$frequency_value     = (int) get_post_meta( $id, '_simple_popup_frequency_value', true );
		$conversion_selector = get_post_meta( $id, '_simple_popup_conversion_selector', true );
		$title_id            = 'simple-popup-title-' . $id;
		?>
		<dialog
			class="simple-popup"
			id="simple-popup-<?php echo esc_attr( $id ); ?>"
			data-trigger="<?php echo esc_attr( $trigger ); ?>"
			data-delay="<?php echo esc_attr( $delay ); ?>"
			data-frequency="<?php echo esc_attr( $frequency ); ?>"
			data-frequency-value="<?php echo esc_attr( $frequency_value ); ?>"
			data-conversion="<?php echo esc_attr( $conversion_selector ); ?>"
			<?php echo $is_preview ? 'data-preview="1"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute, no user input. ?>
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			tabindex="-1"
			autofocus
		>
			<h2 id="<?php echo esc_attr( $title_id ); ?>" class="screen-reader-text"><?php echo esc_html( get_the_title( $popup ) ); ?></h2>
			<button type="button" class="simple-popup__close" aria-label="<?php esc_attr_e( 'Close', 'simple-popup' ); ?>">&times;</button>
			<div class="simple-popup__content">
				<?php echo do_shortcode( do_blocks( $popup->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render output. ?>
			</div>
		</dialog>
		<?php
	}
}
