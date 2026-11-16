<?php
/**
 * Polylang compatibility: makes the post type translatable and keeps the
 * settings that are shared by all language versions of a popup in sync.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Polylang {

	/**
	 * Post IDs currently being written to by {@see sync_meta()}, so the
	 * `updated_post_meta`/`added_post_meta` actions it triggers on the
	 * translations don't propagate back and forth forever.
	 *
	 * @var int[]
	 */
	private static $syncing = array();

	public static function init() {
		add_filter( 'pll_get_post_types', array( __CLASS__, 'register_translatable_post_type' ), 10, 2 );
		add_filter( 'pll_copy_post_metas', array( __CLASS__, 'copy_shared_metas_on_new_translation' ), 10, 3 );

		add_action( 'added_post_meta', array( __CLASS__, 'sync_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'sync_meta' ), 10, 4 );
	}

	/**
	 * Adds `simple_popup` to the Polylang translatable post types.
	 *
	 * @param string[] $post_types  List of post type names (as array keys and values).
	 * @param bool     $is_settings True when displaying the list in the Polylang settings screen.
	 * @return string[]
	 */
	public static function register_translatable_post_type( $post_types, $is_settings ) {
		/**
		 * Filters whether the `simple_popup` post type should be registered as
		 * translatable with Polylang. Defaults to true.
		 *
		 * @param bool $translatable
		 */
		if ( apply_filters( 'simple_popup_translatable', true ) ) {
			$post_types[ Simple_Popup_CPT::POST_TYPE ] = Simple_Popup_CPT::POST_TYPE;
		}

		return $post_types;
	}

	/**
	 * Makes sure a newly created translation starts with the same shared
	 * settings as the popup it was translated from (the "Active" state is
	 * deliberately left out: it stays specific to each language).
	 *
	 * @param string[] $keys Meta keys already scheduled for copy/sync.
	 * @param bool     $sync True when synchronizing an existing translation, false when creating a new one.
	 * @param int      $from Source post ID.
	 * @return string[]
	 */
	public static function copy_shared_metas_on_new_translation( $keys, $sync, $from ) {
		if ( Simple_Popup_CPT::POST_TYPE !== get_post_type( $from ) ) {
			return $keys;
		}

		return array_merge( $keys, Simple_Popup_CPT::SHARED_META_KEYS );
	}

	/**
	 * Copies a shared setting to every other language version of the same
	 * popup whenever it is saved (REST or classic meta update).
	 *
	 * @param int    $meta_id    Meta ID (unused).
	 * @param int    $post_id    Post the meta was saved on.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 */
	public static function sync_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, Simple_Popup_CPT::SHARED_META_KEYS, true ) ) {
			return;
		}

		if ( in_array( (int) $post_id, self::$syncing, true ) ) {
			// Already propagating this exact change: avoid an update loop.
			return;
		}

		if ( Simple_Popup_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! function_exists( 'pll_get_post_translations' ) || ! function_exists( 'pll_is_translated_post_type' ) ) {
			return;
		}

		if ( ! pll_is_translated_post_type( Simple_Popup_CPT::POST_TYPE ) ) {
			return;
		}

		$translations = pll_get_post_translations( $post_id );

		if ( count( $translations ) <= 1 ) {
			return;
		}

		self::$syncing = array_values( array_map( 'intval', $translations ) );

		foreach ( $translations as $translation_id ) {
			$translation_id = (int) $translation_id;

			if ( $translation_id === (int) $post_id ) {
				continue;
			}

			if ( (string) get_post_meta( $translation_id, $meta_key, true ) !== (string) $meta_value ) {
				update_post_meta( $translation_id, $meta_key, $meta_value );
			}
		}

		self::$syncing = array();
	}
}
