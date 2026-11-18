<?php
/**
 * Block editor integration: enqueues the settings panel script/style,
 * exposes the global pause setting to it, and registers the REST fields it
 * reads (`translations`, `siblings` for conflict detection, `preview_url`,
 * `trigger_test_url`).
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

		wp_add_inline_script(
			Simple_Popup_Assets::EDITOR_SCRIPT_HANDLE,
			'window.simplePopupEditor = ' . wp_json_encode( array( 'gap' => Simple_Popup_Settings::get_gap() ) ) . ';',
			'before'
		);
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

		register_rest_field(
			Simple_Popup_CPT::POST_TYPE,
			'simple_popup_siblings',
			array(
				'get_callback' => array( __CLASS__, 'get_siblings_field' ),
				'schema'       => array(
					'description' => __( 'Other published and active popups in the same language, for conflict detection in the editor sidebar.', 'simple-popup' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
				),
			)
		);

		register_rest_field(
			Simple_Popup_CPT::POST_TYPE,
			'simple_popup_preview_url',
			array(
				'get_callback' => array( __CLASS__, 'get_preview_url_field' ),
				'schema'       => array(
					'description' => __( 'URL that opens this popup alone, immediately, without reading or writing its cookie.', 'simple-popup' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			)
		);

		register_rest_field(
			Simple_Popup_CPT::POST_TYPE,
			'simple_popup_trigger_test_url',
			array(
				'get_callback' => array( __CLASS__, 'get_trigger_test_url_field' ),
				'schema'       => array(
					'description' => __( 'URL that opens this popup alone with its real trigger and delay, without reading or writing its cookie.', 'simple-popup' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			)
		);
	}

	/**
	 * Builds the list of other published and active popups in the same
	 * language as `$post`, for the editor's conflict notices: same
	 * trigger/delay as another one, or a delay close enough to fall inside
	 * the global pause.
	 *
	 * @param array           $post       REST response post array (contains 'id').
	 * @param string          $field_name Unused.
	 * @param WP_REST_Request $request    Current REST request; used to skip the query outside `edit` context.
	 * @return array[]
	 */
	public static function get_siblings_field( $post, $field_name, $request ) {
		if ( 'edit' !== $request['context'] ) {
			return array();
		}

		$post_id = (int) $post['id'];

		$args = array(
			'post_type'        => Simple_Popup_CPT::POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'post__not_in'     => array( $post_id ),
			'meta_key'         => '_simple_popup_active',
			'meta_value'       => '1',
			'suppress_filters' => false,
		);

		if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_is_translated_post_type' )
			&& pll_is_translated_post_type( Simple_Popup_CPT::POST_TYPE )
		) {
			$language = pll_get_post_language( $post_id );

			if ( $language ) {
				$args['lang'] = $language;
			}
		}

		$siblings = get_posts( $args );
		$result   = array();

		foreach ( $siblings as $sibling ) {
			$result[] = array(
				'id'      => $sibling->ID,
				'title'   => get_the_title( $sibling ),
				'trigger' => get_post_meta( $sibling->ID, '_simple_popup_trigger', true ),
				'delay'   => (int) get_post_meta( $sibling->ID, '_simple_popup_delay', true ),
			);
		}

		return $result;
	}

	/**
	 * @param array           $post       REST response post array (contains 'id').
	 * @param string          $field_name Unused.
	 * @param WP_REST_Request $request    Current REST request.
	 * @return string
	 */
	public static function get_preview_url_field( $post, $field_name, $request ) {
		if ( 'edit' !== $request['context'] ) {
			return '';
		}

		return Simple_Popup_Frontend::get_preview_url( (int) $post['id'] );
	}

	/**
	 * @param array           $post       REST response post array (contains 'id').
	 * @param string          $field_name Unused.
	 * @param WP_REST_Request $request    Current REST request.
	 * @return string
	 */
	public static function get_trigger_test_url_field( $post, $field_name, $request ) {
		if ( 'edit' !== $request['context'] ) {
			return '';
		}

		return Simple_Popup_Frontend::get_preview_url( (int) $post['id'], 'trigger' );
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
