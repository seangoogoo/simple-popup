<?php
/**
 * Registers the `simple_popup` post type and its settings post meta.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_CPT {

	const POST_TYPE = 'simple_popup';

	/**
	 * Meta keys that are specific to each language version of a popup.
	 */
	const OWN_META_KEYS = array( '_simple_popup_active' );

	/**
	 * Meta keys shared by every language version of the same popup
	 * (kept in sync by Simple_Popup_Polylang).
	 */
	const SHARED_META_KEYS = array(
		'_simple_popup_trigger',
		'_simple_popup_delay',
		'_simple_popup_frequency',
		'_simple_popup_frequency_value',
		'_simple_popup_conversion_selector',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Popups', 'simple-popup' ),
			'singular_name'      => __( 'Popup', 'simple-popup' ),
			'add_new'            => __( 'Add New', 'simple-popup' ),
			'add_new_item'       => __( 'Add New Popup', 'simple-popup' ),
			'edit_item'          => __( 'Edit Popup', 'simple-popup' ),
			'new_item'           => __( 'New Popup', 'simple-popup' ),
			'view_item'          => __( 'View Popup', 'simple-popup' ),
			'search_items'       => __( 'Search Popups', 'simple-popup' ),
			'not_found'          => __( 'No popups found', 'simple-popup' ),
			'not_found_in_trash' => __( 'No popups found in Trash', 'simple-popup' ),
			'all_items'          => __( 'All Popups', 'simple-popup' ),
			'menu_name'          => __( 'Popups', 'simple-popup' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-megaphone',
				'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields', 'page-attributes' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_active',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_trigger',
			array(
				'single'            => true,
				'type'              => 'string',
				'default'           => 'gesture',
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array( 'gesture', 'load', 'exit' ),
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_trigger' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_delay',
			array(
				'single'            => true,
				'type'              => 'integer',
				'default'           => 4,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_int' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_frequency',
			array(
				'single'            => true,
				'type'              => 'string',
				'default'           => 'visit',
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array( 'visit', 'hours', 'once' ),
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_frequency' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_frequency_value',
			array(
				'single'            => true,
				'type'              => 'integer',
				'default'           => 30,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_frequency_value' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_simple_popup_conversion_selector',
			array(
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);
	}

	public static function sanitize_bool( $value ) {
		return (bool) rest_sanitize_boolean( $value );
	}

	public static function sanitize_trigger( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, array( 'gesture', 'load', 'exit' ), true ) ? $value : 'gesture';
	}

	public static function sanitize_frequency( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, array( 'visit', 'hours', 'once' ), true ) ? $value : 'visit';
	}

	public static function sanitize_non_negative_int( $value ) {
		return max( 0, (int) $value );
	}

	/**
	 * Frequency value (hours between displays, or minutes of inactivity):
	 * `0` is accepted by the input but has no sensible runtime meaning (the
	 * public script falls back to its own default), so enforce a minimum
	 * of 1 here instead of describing a value that is never actually used.
	 */
	public static function sanitize_frequency_value( $value ) {
		return max( 1, (int) $value );
	}

	public static function auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Human sentence describing when and how often a popup opens, used by
	 * the admin list "When" column, the "Diffusion plan" box, and mirrored
	 * in the editor sidebar (see src/editor/index.js).
	 *
	 * @param int $post_id Popup post ID.
	 * @return string
	 */
	public static function describe( $post_id ) {
		$trigger         = get_post_meta( $post_id, '_simple_popup_trigger', true );
		$delay           = (int) get_post_meta( $post_id, '_simple_popup_delay', true );
		$frequency       = get_post_meta( $post_id, '_simple_popup_frequency', true );
		$frequency_value = (int) get_post_meta( $post_id, '_simple_popup_frequency_value', true );

		switch ( $trigger ) {
			case 'load':
				$moment = sprintf(
					/* translators: %d: number of seconds. */
					_n( 'Opens %d second after the page loads', 'Opens %d seconds after the page loads', $delay, 'simple-popup' ),
					$delay
				);
				break;

			case 'exit':
				$moment = __( 'Opens when the mouse leaves the page (desktop only)', 'simple-popup' );
				break;

			case 'gesture':
			default:
				$moment = sprintf(
					/* translators: %d: number of seconds. */
					_n( 'Opens on the first gesture after %d second', 'Opens on the first gesture after %d seconds', $delay, 'simple-popup' ),
					$delay
				);
				break;
		}

		switch ( $frequency ) {
			case 'once':
				$freq = __( 'only once', 'simple-popup' );
				break;

			case 'hours':
				$freq = sprintf(
					/* translators: %d: number of hours. */
					_n( 'at most once every %d hour', 'at most once every %d hours', $frequency_value, 'simple-popup' ),
					$frequency_value
				);
				break;

			case 'visit':
			default:
				$freq = sprintf(
					/* translators: %d: number of minutes of inactivity. */
					__( 'once per visit (%d min. of inactivity)', 'simple-popup' ),
					$frequency_value
				);
				break;
		}

		return sprintf(
			/* translators: 1: when the popup opens, 2: how often it opens. */
			__( '%1$s, %2$s.', 'simple-popup' ),
			$moment,
			$freq
		);
	}
}
