<?php
/**
 * Registers the editor and public scripts/styles so both the admin and the
 * front end can enqueue the same handles.
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Popup_Assets {

	const PUBLIC_SCRIPT_HANDLE = 'simple-popup-public';
	const PUBLIC_STYLE_HANDLE  = 'simple-popup-public';
	const EDITOR_SCRIPT_HANDLE = 'simple-popup-editor';

	/**
	 * Hooks registration on `init`, early enough for both the front end and
	 * the admin to enqueue the handles afterwards.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		self::register_public_assets();
		self::register_editor_assets();
	}

	/**
	 * Reads a `@wordpress/scripts` generated `*.asset.php` file.
	 *
	 * @param string $relative_path Path to the asset file, relative to the plugin root.
	 * @return array{dependencies: string[], version: string}
	 */
	private static function read_asset_file( $relative_path ) {
		$path = SIMPLE_POPUP_DIR . $relative_path;

		if ( file_exists( $path ) ) {
			$asset = include $path;

			if ( is_array( $asset ) ) {
				return $asset;
			}
		}

		return array(
			'dependencies' => array(),
			'version'      => SIMPLE_POPUP_VERSION,
		);
	}

	private static function register_public_assets() {
		$asset = self::read_asset_file( 'build/public.asset.php' );

		wp_register_script(
			self::PUBLIC_SCRIPT_HANDLE,
			SIMPLE_POPUP_URL . 'build/public.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			self::PUBLIC_STYLE_HANDLE,
			SIMPLE_POPUP_URL . 'build/style-public.css',
			array(),
			$asset['version']
		);
	}

	private static function register_editor_assets() {
		$asset = self::read_asset_file( 'build/editor.asset.php' );

		wp_register_script(
			self::EDITOR_SCRIPT_HANDLE,
			SIMPLE_POPUP_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( self::EDITOR_SCRIPT_HANDLE, 'simple-popup', SIMPLE_POPUP_DIR . 'languages' );
	}
}
