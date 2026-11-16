<?php
/**
 * Front-end rendering: prints the published and active popups (filtered to
 * the current Polylang language, if any) inside `<dialog>` markup in the
 * footer.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Frontend {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_popups' ) );
	}

	/**
	 * @return WP_Post[]
	 */
	private static function get_displayable_popups() {
		$args = array(
			'post_type'      => Simple_Popup_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
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

		wp_enqueue_script( Simple_Popup_Assets::PUBLIC_SCRIPT_HANDLE );
		wp_enqueue_style( Simple_Popup_Assets::PUBLIC_STYLE_HANDLE );

		foreach ( $displayable as $popup ) {
			self::render_popup( $popup );
		}
	}

	private static function render_popup( $popup ) {
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
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
		>
			<h2 id="<?php echo esc_attr( $title_id ); ?>" class="screen-reader-text"><?php echo esc_html( get_the_title( $popup ) ); ?></h2>
			<button type="button" class="simple-popup__close" aria-label="<?php esc_attr_e( 'Close', 'simple-popup' ); ?>">&times;</button>
			<div class="simple-popup__content">
				<?php echo do_blocks( $popup->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render output. ?>
			</div>
		</dialog>
		<?php
	}
}
