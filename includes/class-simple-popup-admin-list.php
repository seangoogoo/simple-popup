<?php
/**
 * Admin list screen for `simple_popup`: columns (including the per-language
 * "Languages" toggle column when Polylang is active and the "When"/"Order"
 * columns), state filter, the active/inactive toggle action, the "Test" row
 * action, and the "Diffusion plan" box.
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

		add_action( 'admin_notices', array( __CLASS__, 'render_diffusion_plan' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_diffusion_plan_styles' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'add_test_row_action' ), 10, 2 );

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

				$new_columns['simple_popup_when']  = __( 'When', 'simple-popup' );
				$new_columns['simple_popup_order'] = __( 'Order', 'simple-popup' );
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

			case 'simple_popup_when':
				echo esc_html( Simple_Popup_CPT::describe( $post_id ) );
				break;

			case 'simple_popup_order':
				echo esc_html( get_post_field( 'menu_order', $post_id ) );
				break;
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
	 * "Diffusion plan" box at the top of the list screen: one group per
	 * language with at least one published and active popup, listing them
	 * in the order a visitor actually meets them on a page (gesture/load
	 * sorted by delay, then exit; ties by Order then date), plus a reminder
	 * of the pause rule and a link to the Settings screen.
	 */
	public static function render_diffusion_plan() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . Simple_Popup_CPT::POST_TYPE !== $screen->id ) {
			return;
		}

		$active_ids = get_posts(
			array(
				'post_type'        => Simple_Popup_CPT::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'meta_key'         => '_simple_popup_active',
				'meta_value'       => '1',
				'suppress_filters' => false,
				'lang'             => '', // Across all languages, not just the admin's current one.
			)
		);

		if ( empty( $active_ids ) ) {
			return;
		}

		$groups = self::group_popups_by_language( $active_ids );
		$gap    = Simple_Popup_Settings::get_gap();
		?>
		<div class="notice simple-popup-diffusion-plan">
			<h2><?php esc_html_e( 'Diffusion plan', 'simple-popup' ); ?></h2>
			<?php foreach ( $groups as $group ) : ?>
				<div class="simple-popup-diffusion-plan__group">
					<?php if ( $group['label'] ) : ?>
						<p class="simple-popup-diffusion-plan__lang"><strong><?php echo esc_html( $group['label'] ); ?></strong></p>
					<?php endif; ?>
					<ol>
						<?php foreach ( $group['popups'] as $popup_id ) : ?>
							<li>
								<a href="<?php echo esc_url( get_edit_post_link( $popup_id ) ); ?>"><?php echo esc_html( get_the_title( $popup_id ) ); ?></a>
								&mdash; <?php echo esc_html( Simple_Popup_CPT::describe( $popup_id ) ); ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endforeach; ?>
			<p class="simple-popup-diffusion-plan__rule">
				<?php
				printf(
					/* translators: %d: pause in seconds. */
					esc_html(
						_n(
							'Never two popups at the same time; at least %d second between two. The delay is counted on a single page.',
							'Never two popups at the same time; at least %d seconds between two. The delay is counted on a single page.',
							$gap,
							'simple-popup'
						)
					),
					(int) $gap
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Simple_Popup_CPT::POST_TYPE . '&page=' . Simple_Popup_Settings::PAGE_SLUG ) ); ?>">
					<?php esc_html_e( 'Change the pause setting', 'simple-popup' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Groups popup IDs by language (one group, unlabeled, without a
	 * multilingual plugin), each group's popups sorted in the order a
	 * visitor meets them on a page.
	 *
	 * @param int[] $post_ids Published and active popup IDs.
	 * @return array<string, array{label: string, popups: int[]}>
	 */
	private static function group_popups_by_language( $post_ids ) {
		$groups = array();

		if ( self::is_multilingual() ) {
			foreach ( $post_ids as $post_id ) {
				$slug = pll_get_post_language( $post_id, 'slug' );
				$name = pll_get_post_language( $post_id, 'name' );
				$name = $name ? $name : __( 'Unknown language', 'simple-popup' );
				$key  = $slug ? $slug : '';

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'label'  => $name,
						'popups' => array(),
					);
				}

				$groups[ $key ]['popups'][] = $post_id;
			}
		} else {
			$groups[''] = array(
				'label'  => '',
				'popups' => $post_ids,
			);
		}

		foreach ( $groups as &$group ) {
			$group['popups'] = self::sort_popups_by_encounter_order( $group['popups'] );
		}
		unset( $group );

		return $groups;
	}

	/**
	 * Sorts popup IDs the way a visitor meets them on a page: `gesture`/
	 * `load` by delay (ascending), then `exit`; ties broken by Order
	 * (`menu_order`, ascending), then date (descending).
	 *
	 * @param int[] $post_ids Popup IDs, same language.
	 * @return int[]
	 */
	private static function sort_popups_by_encounter_order( $post_ids ) {
		$items = array();

		foreach ( $post_ids as $post_id ) {
			$is_exit = 'exit' === get_post_meta( $post_id, '_simple_popup_trigger', true );

			$items[] = array(
				'id'         => $post_id,
				'is_exit'    => $is_exit,
				'delay'      => $is_exit ? 0 : (int) get_post_meta( $post_id, '_simple_popup_delay', true ),
				'menu_order' => (int) get_post_field( 'menu_order', $post_id ),
				'date'       => get_post_field( 'post_date', $post_id ),
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['is_exit'] !== $b['is_exit'] ) {
					return $a['is_exit'] ? 1 : -1;
				}

				if ( ! $a['is_exit'] && $a['delay'] !== $b['delay'] ) {
					return $a['delay'] <=> $b['delay'];
				}

				if ( $a['menu_order'] !== $b['menu_order'] ) {
					return $a['menu_order'] <=> $b['menu_order'];
				}

				return strcmp( $b['date'], $a['date'] );
			}
		);

		return wp_list_pluck( $items, 'id' );
	}

	/**
	 * Minimal, WP-admin-native styling for the Diffusion plan box, scoped to
	 * the list screen only.
	 */
	public static function print_diffusion_plan_styles() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . Simple_Popup_CPT::POST_TYPE !== $screen->id ) {
			return;
		}
		?>
		<style>
			.simple-popup-diffusion-plan h2 {
				margin-top: 0.5em;
				font-size: 14px;
			}
			.simple-popup-diffusion-plan__group {
				margin-bottom: 0.5em;
			}
			.simple-popup-diffusion-plan__group ol {
				margin: 0.25em 0 0.5em 1.5em;
				list-style: decimal;
			}
			.simple-popup-diffusion-plan__rule {
				color: #646970;
			}
		</style>
		<?php
	}

	/**
	 * "Test" row action: opens the popup alone, immediately, on its
	 * language's home page, for any published popup or any one the current
	 * user can edit.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Row's post.
	 * @return array
	 */
	public static function add_test_row_action( $actions, $post ) {
		if ( Simple_Popup_CPT::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['simple_popup_test'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( Simple_Popup_Frontend::get_preview_url( $post->ID ) ),
			esc_html__( 'Test', 'simple-popup' )
		);

		return $actions;
	}
}
