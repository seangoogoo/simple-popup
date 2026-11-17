<?php
/**
 * Plugin Name:       Simple Popup
 * Description:       Editor-managed popup windows, with per-popup display rules, cookies and Polylang support.
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Version:           0.2.1
 * Author:            Jensen SIU
 * Author URI:        https://www.jensen.siu.net
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Update URI:        https://github.com/seangoogoo/simple-popup
 * Text Domain:       simple-popup
 *
 * @package           SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SIMPLE_POPUP_VERSION', '0.2.1' );
define( 'SIMPLE_POPUP_FILE', __FILE__ );
define( 'SIMPLE_POPUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_POPUP_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'simple_popup_load_textdomain', 1 );

/**
 * Loads the bundled `.mo` translations (the editor script's own JSON
 * translations are loaded separately, via `wp_set_script_translations()`).
 */
function simple_popup_load_textdomain() {
	load_plugin_textdomain( 'simple-popup', false, dirname( plugin_basename( SIMPLE_POPUP_FILE ) ) . '/languages' );
}

require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-assets.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-settings.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-cpt.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-editor.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-admin-list.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-frontend.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-polylang.php';

Simple_Popup_Assets::init();
Simple_Popup_Settings::init();
Simple_Popup_CPT::init();
Simple_Popup_Editor::init();
Simple_Popup_Admin_List::init();
Simple_Popup_Frontend::init();
Simple_Popup_Polylang::init();
