<?php
/**
 * Admin list screen for `simple_popup`: columns (including the per-language
 * "Languages" toggle column when Polylang is active), state filter, and the
 * active/inactive toggle action.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Admin_List {

	public static function init() {
		add_filter( 'manage_' . Simple_Popup_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_columns' ) );
		add_action( 'manage_' . Simple_Popup_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_state_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_by_state' ) );

		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_multiple_active_notice' ) );

		add_action( 'admin_action_simple_popup_toggle_active', array( __CLASS__, 'handle_toggle_active' ) );
	}

	private static function is_multilingual() {
		return function_exists( 'pll_is_translated_post_type' ) && pll_is_translated_post_type( Simple_Popup_CPT::POST_TYPE );
	}

	public static function add_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				if ( self::is_multilingual() ) {
					$new_columns['simple_popup_languages'] = __( 'Languages', 'simple-popup' );
				} else {
					$new_columns['simple_popup_active'] = __( 'Active', 'simple-popup' );
				}

				$new_columns['simple_popup_trigger']   = __( 'Trigger', 'simple-popup' );
				$new_columns['simple_popup_delay']     = __( 'Delay', 'simple-popup' );
				$new_columns['simple_popup_frequency'] = __( 'Frequency', 'simple-popup' );
			}
		}

		return $new_columns;
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'simple_popup_active':
				echo self::get_toggle_link_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_toggle_link_html().
				break;

			case 'simple_popup_languages':
				echo self::get_languages_column_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_languages_column_html().
				break;

			case 'simple_popup_trigger':
				$trigger = get_post_meta( $post_id, '_simple_popup_trigger', true );
				echo esc_html( self::trigger_label( $trigger ) );
				break;

			case 'simple_popup_delay':
				$delay = (int) get_post_meta( $post_id, '_simple_popup_delay', true );
				echo esc_html(
					sprintf(
						/* translators: %d: number of seconds. */
						_n( '%d second', '%d seconds', $delay, 'simple-popup' ),
						$delay
					)
				);
				break;

			case 'simple_popup_frequency':
				$frequency = get_post_meta( $post_id, '_simple_popup_frequency', true );
				$value     = (int) get_post_meta( $post_id, '_simple_popup_frequency_value', true );
				echo esc_html( self::frequency_label( $frequency, $value ) );
				break;
		}
	}

	private static function trigger_label( $trigger ) {
		$labels = array(
			'gesture' => __( 'First gesture', 'simple-popup' ),
			'load'    => __( 'Page load', 'simple-popup' ),
			'exit'    => __( 'Exit intent', 'simple-popup' ),
		);

		return isset( $labels[ $trigger ] ) ? $labels[ $trigger ] : $trigger;
	}

	private static function frequency_label( $frequency, $value ) {
		switch ( $frequency ) {
			case 'hours':
				/* translators: %d: number of hours. */
				return sprintf( _n( 'Once every %d hour', 'Once every %d hours', $value, 'simple-popup' ), $value );

			case 'once':
				return __( 'Once', 'simple-popup' );

			case 'visit':
			default:
				/* translators: %d: number of minutes. */
				return sprintf( __( 'Once per visit (%d min.)', 'simple-popup' ), $value );
		}
	}

	/**
	 * Single Active/Inactive toggle, used when no multilingual plugin is active.
	 */
	private static function get_toggle_link_html( $post_id ) {
		$active = (bool) get_post_meta( $post_id, '_simple_popup_active', true );
		$label  = $active ? __( 'Active', 'simple-popup' ) : __( 'Inactive', 'simple-popup' );
		$dot    = $active ? '&#9679;' : '&#9675;';

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return sprintf( '<span>%s %s</span>', $dot, esc_html( $label ) );
		}

		return sprintf(
			'<a href="%1$s">%2$s %3$s</a>',
			esc_url( self::get_toggle_url( $post_id ) ),
			$dot,
			esc_html( $label )
		);
	}

	/**
	 * "Languages" column. When the list is filtered to one language (one row
	 * per popup), shows every language version with its active state (toggle
	 * link when editable) or a "+ Add" link. When all languages are listed,
	 * each version already has its own row: only the row's own language is
	 * shown, so a toggle visibly changes one row only.
	 */
	private static function get_languages_column_html( $post_id ) {
		if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_get_post_translations' ) ) {
			return '';
		}

		$languages    = pll_languages_list( array( 'fields' => '' ) );
		$translations = pll_get_post_translations( $post_id );
		$parts        = array();
		$row_only     = function_exists( 'PLL' ) && property_exists( PLL(), 'filter_lang' ) && empty( PLL()->filter_lang );
		$row_language = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id ) : '';

		foreach ( $languages as $language ) {
			if ( $row_only && $language->slug !== $row_language ) {
				continue;
			}

			$slug           = strtoupper( $language->slug );
			$translation_id = isset( $translations[ $language->slug ] ) ? (int) $translations[ $language->slug ] : 0;

			if ( $translation_id && get_post( $translation_id ) ) {
				$active = (bool) get_post_meta( $translation_id, '_simple_popup_active', true );
				$label  = $active ? __( 'Active', 'simple-popup' ) : __( 'Inactive', 'simple-popup' );
				$dot    = $active ? '&#9679;' : '&#9675;';

				if ( current_user_can( 'edit_post', $translation_id ) ) {
					$parts[] = sprintf(
						'<a href="%1$s" class="simple-popup-lang-toggle">%2$s %3$s %4$s</a>',
						esc_url( self::get_toggle_url( $translation_id ) ),
						esc_html( $slug ),
						$dot,
						esc_html( $label )
					);
				} else {
					$parts[] = sprintf( '%1$s %2$s %3$s', esc_html( $slug ), $dot, esc_html( $label ) );
				}
			} else {
				$new_link = self::get_new_translation_link( $post_id, $language );

				if ( $new_link ) {
					$parts[] = sprintf(
						'<a href="%1$s">%2$s %3$s</a>',
						esc_url( $new_link ),
						esc_html( $slug ),
						esc_html__( '+ Add', 'simple-popup' )
					);
				}
			}
		}

		return implode( ' &middot; ', $parts );
	}

	/**
	 * Builds the Polylang "create a new translation" link for a post (same
	 * request shape Polylang itself reads on `post-new.php`: `post_type`,
	 * `from_post`, `new_lang`, nonced with the `new-post-translation` action).
	 *
	 * @param int    $post_id  Source post ID.
	 * @param object $language Polylang language object (has a `slug` property).
	 * @return string Empty string if the current user can't create a translation.
	 */
	public static function get_new_translation_link( $post_id, $language ) {
		$post_type_object = get_post_type_object( Simple_Popup_CPT::POST_TYPE );

		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'post_type' => Simple_Popup_CPT::POST_TYPE,
				'from_post' => $post_id,
				'new_lang'  => $language->slug,
			),
			admin_url( 'post-new.php' )
		);

		return wp_nonce_url( $url, 'new-post-translation' );
	}

	private static function get_toggle_url( $post_id ) {
		$url = add_query_arg(
			array(
				'action'      => 'simple_popup_toggle_active',
				'post'        => $post_id,
				'redirect_to' => rawurlencode( self::current_list_url() ),
			),
			admin_url( 'admin.php' )
		);

		return wp_nonce_url( $url, 'simple_popup_toggle_active_' . $post_id );
	}

	/**
	 * URL of the list screen being rendered (with its filters, e.g. the
	 * Polylang language), so a toggle returns to the same view. The HTTP
	 * referer would point to the page visited before the list.
	 */
	private static function current_list_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return admin_url( 'edit.php?post_type=' . Simple_Popup_CPT::POST_TYPE );
		}

		$url = set_url_scheme( 'http://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by wp_safe_redirect() on use.

		return remove_query_arg( array( '_wpnonce', '_wp_http_referer' ), $url );
	}

	public static function handle_toggle_active() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( ! $post_id || Simple_Popup_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Invalid popup.', 'simple-popup' ) );
		}

		check_admin_referer( 'simple_popup_toggle_active_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this popup.', 'simple-popup' ) );
		}

		$active = (bool) get_post_meta( $post_id, '_simple_popup_active', true );
		update_post_meta( $post_id, '_simple_popup_active', ! $active );

		$redirect_to = isset( $_GET['redirect_to'] ) ? rawurldecode( wp_unslash( $_GET['redirect_to'] ) ) : '';
		wp_safe_redirect( $redirect_to ? $redirect_to : admin_url( 'edit.php?post_type=' . Simple_Popup_CPT::POST_TYPE ) );
		exit;
	}

	public static function render_state_filter( $post_type ) {
		if ( Simple_Popup_CPT::POST_TYPE !== $post_type ) {
			return;
		}

		$current = isset( $_GET['simple_popup_state'] ) ? sanitize_key( $_GET['simple_popup_state'] ) : '';
		?>
		<select name="simple_popup_state">
			<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'All states', 'simple-popup' ); ?></option>
			<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active', 'simple-popup' ); ?></option>
			<option value="inactive" <?php selected( $current, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'simple-popup' ); ?></option>
		</select>
		<?php
	}

	public static function filter_by_state( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( Simple_Popup_CPT::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$state = isset( $_GET['simple_popup_state'] ) ? sanitize_key( $_GET['simple_popup_state'] ) : '';

		if ( 'active' === $state ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_simple_popup_active',
						'value' => '1',
					),
				)
			);
		} elseif ( 'inactive' === $state ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_simple_popup_active',
						'value'   => '1',
						'compare' => '!=',
					),
					array(
						'key'     => '_simple_popup_active',
						'compare' => 'NOT EXISTS',
					),
				)
			);
		}
	}

	/**
	 * Warns, on the list screen, when more than one popup is active in the
	 * same language (or overall, without a multilingual plugin): visitors
	 * will only ever see one of them per page view, so this is probably
	 * unintended.
	 */
	public static function maybe_render_multiple_active_notice() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . Simple_Popup_CPT::POST_TYPE !== $screen->id ) {
			return;
		}

		$active_ids = get_posts(
			array(
				'post_type'       => Simple_Popup_CPT::POST_TYPE,
				'post_status'     => 'publish',
				'posts_per_page'  => -1,
				'fields'          => 'ids',
				'meta_key'        => '_simple_popup_active',
				'meta_value'      => '1',
				'suppress_filters' => false,
				'lang'            => '', // Count across all languages, not just the admin's current one.
			)
		);

		if ( count( $active_ids ) < 2 ) {
			return;
		}

		if ( self::is_multilingual() ) {
			$by_language = array();

			foreach ( $active_ids as $post_id ) {
				$lang = pll_get_post_language( $post_id, 'name' );
				$lang = $lang ? $lang : __( 'Unknown language', 'simple-popup' );

				if ( ! isset( $by_language[ $lang ] ) ) {
					$by_language[ $lang ] = 0;
				}

				++$by_language[ $lang ];
			}

			$messages = array();

			foreach ( $by_language as $lang => $count ) {
				if ( $count > 1 ) {
					$messages[] = sprintf(
						/* translators: 1: number of active popups, 2: language name. */
						_n( '%1$d popup is active in %2$s.', '%1$d popups are active in %2$s.', $count, 'simple-popup' ),
						$count,
						$lang
					);
				}
			}

			if ( empty( $messages ) ) {
				return;
			}

			$text = __( 'Visitors only ever see one popup per page view.', 'simple-popup' ) . ' ' . implode( ' ', $messages );
		} else {
			$text = sprintf(
				/* translators: %d: number of active popups. */
				__( '%d popups are currently active. Visitors only ever see one popup per page view.', 'simple-popup' ),
				count( $active_ids )
			);
		}

		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $text ) );
	}
}
