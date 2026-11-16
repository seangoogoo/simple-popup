<?php
/**
 * Plugin Name:       Simple Popup
 * Description:       Editor-managed popup windows, with per-popup display rules, cookies and Polylang support.
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Version:           0.1.0
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

define( 'SIMPLE_POPUP_VERSION', '0.1.0' );
define( 'SIMPLE_POPUP_FILE', __FILE__ );
define( 'SIMPLE_POPUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_POPUP_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-assets.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-cpt.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-editor.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-admin-list.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-frontend.php';
require_once SIMPLE_POPUP_DIR . 'includes/class-simple-popup-polylang.php';

Simple_Popup_Assets::init();
Simple_Popup_CPT::init();
Simple_Popup_Editor::init();
Simple_Popup_Admin_List::init();
Simple_Popup_Frontend::init();
Simple_Popup_Polylang::init();
