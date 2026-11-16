<?php
/**
 * Block editor integration: enqueues the settings panel script/style and
 * exposes a `translations` REST field used by the "Languages" box.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Editor {

	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );
	}

	public static function enqueue_assets() {
		$screen = get_current_screen();

		if ( ! $screen || Simple_Popup_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( Simple_Popup_Assets::EDITOR_SCRIPT_HANDLE );
		// Same stylesheet as the front end, so the editor preview stays close
		// to what visitors will actually see.
		wp_enqueue_style( Simple_Popup_Assets::PUBLIC_STYLE_HANDLE );
	}

	public static function register_rest_field() {
		register_rest_field(
			Simple_Popup_CPT::POST_TYPE,
			'simple_popup_translations',
			array(
				'get_callback' => array( __CLASS__, 'get_translations_field' ),
				'schema'       => array(
					'description' => __( 'Other language versions of this popup, for the editor sidebar.', 'simple-popup' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
				),
			)
		);
	}

	/**
	 * Builds the list of language versions of a popup, for the editor's
	 * "Languages" box: existing translations link to their edit screen,
	 * missing ones link to Polylang's "new translation" screen.
	 *
	 * @param array $post REST response post array (contains 'id').
	 * @return array[]
	 */
	public static function get_translations_field( $post ) {
		$post_id = (int) $post['id'];

		if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_is_translated_post_type' )
			|| ! pll_is_translated_post_type( Simple_Popup_CPT::POST_TYPE )
		) {
			return array();
		}

		$languages    = pll_languages_list( array( 'fields' => '' ) );
		$current_lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id ) : '';
		$translations = function_exists( 'pll_get_post_translations' ) ? pll_get_post_translations( $post_id ) : array();

		$result = array();

		foreach ( $languages as $language ) {
			$slug          = $language->slug;
			$translation_id = isset( $translations[ $slug ] ) ? (int) $translations[ $slug ] : 0;
			$is_current    = ( $slug === $current_lang );
			$entry         = array(
				'slug'       => $slug,
				'name'       => $language->name,
				'is_current' => $is_current,
				'exists'     => false,
				'active'     => null,
				'can_edit'   => false,
				'url'        => '',
			);

			if ( $translation_id && get_post( $translation_id ) ) {
				$entry['exists']   = true;
				$entry['active']   = (bool) get_post_meta( $translation_id, '_simple_popup_active', true );
				$entry['can_edit'] = current_user_can( 'edit_post', $translation_id );
				$entry['url']      = get_edit_post_link( $translation_id, 'raw' );
			} else {
				$entry['url'] = Simple_Popup_Admin_List::get_new_translation_link( $post_id, $language );
			}

			$result[] = $entry;
		}

		return $result;
	}
}
