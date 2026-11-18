<?php
/**
 * Updates from GitHub releases through the native "Update URI" mechanism (WordPress 5.8+).
 *
 * @package SimplePopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Simple_Popup_Updater {

	const REPO      = 'seangoogoo/simple-popup';
	const SLUG      = 'simple-popup';
	const ASSET     = 'simple-popup.zip';
	const CACHE_KEY = 'simple_popup_latest_release';

	/** @var string Plugin basename, e.g. "simple-popup/simple-popup.php". */
	private $plugin_file;

	public function __construct( $plugin_file ) {
		$this->plugin_file = plugin_basename( $plugin_file );
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
	}

	//* Latest GitHub release with a plugin zip, cached 12 h (1 h after a failure).
	//* "Check again" on Dashboard → Updates (force-check) bypasses the cache.
	private function get_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached && empty( $_GET['force-check'] ) ) {
			return $cached ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github.html+json' ),
			)
		);
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$release = null;

		if ( 200 === wp_remote_retrieve_response_code( $response ) && ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( self::ASSET === $asset['name'] ) {
					$version = ltrim( (string) $data['tag_name'], 'v' );
					$prefix  = 'https://github.com/' . self::REPO . '/releases/download/';
					if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) || 0 !== strpos( $asset['browser_download_url'], $prefix ) ) {
						break;
					}
					$release = array(
						'version'   => $version,
						'package'   => $asset['browser_download_url'],
						'url'       => $data['html_url'],
						'changelog' => isset( $data['body_html'] ) ? $data['body_html'] : '',
						'date'      => $data['published_at'],
					);
					break;
				}
			}
		}

		if ( $release ) {
			$release['changelog'] = $this->get_changelog_html( $data['tag_name'], $release['changelog'] );
		}

		set_site_transient( self::CACHE_KEY, $release ? $release : 0, $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $release;
	}

	//* Full CHANGELOG.md of the release, rendered to HTML by GitHub; falls back to the release notes on failure.
	private function get_changelog_html( $tag_name, $fallback ) {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/contents/CHANGELOG.md?ref=' . rawurlencode( $tag_name ),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github.html' ),
			)
		);
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $fallback;
		}
		$html = wp_remote_retrieve_body( $response );

		//* Drop the leading "Changelog" heading and its intro paragraph.
		$html = preg_replace(
			'#^.*?<div class="markdown-heading"[^>]*>\s*<h1[^>]*>.*?</h1>.*?</div>\s*<p[^>]*>.*?</p>\s*#s',
			'',
			$html,
			1
		);
		if ( null === $html ) {
			return $fallback;
		}

		//* Drop the "[Unreleased]" section only if it is empty (nothing but whitespace before the next heading).
		$html = preg_replace(
			'#<div class="markdown-heading"[^>]*>\s*<h2[^>]*>\[Unreleased\]</h2>.*?</div>\s*(?=<div class="markdown-heading"[^>]*>\s*<h2|</article>)#s',
			'',
			$html,
			1
		);
		if ( null === $html ) {
			return $fallback;
		}

		return $html;
	}

	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->plugin_file ) {
			return $update;
		}
		$release = $this->get_release();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'slug'    => self::SLUG,
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	//* "View details" modal on the Plugins page.
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}
		$plugin   = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_file, false, false );
		$features = '<p><strong>Key features</strong></p><ul>'
			. '<li>Popups managed from the block editor, as regular posts made of blocks</li>'
			. '<li>Triggers: first gesture, page load, or exit intent, with a configurable delay</li>'
			. '<li>Display frequency: once per visit, every N hours, or once ever, enforced with a per-popup cookie</li>'
			. '<li>Several popups can be active at once, with a diffusion plan, a pause between two, and an order</li>'
			. '<li>Polylang support: shared settings, per-language "Active" state and content</li>'
			. '<li>"Preview" and "Test the trigger" links to open a popup alone, without cookies, either immediately or with its real trigger and delay</li>'
			. '</ul>';
		return (object) array(
			'name'          => $plugin['Name'],
			'slug'          => self::SLUG,
			'external'      => true,
			'version'       => $release['version'],
			'author'        => $plugin['Author'],
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => $plugin['RequiresWP'],
			'requires_php'  => $plugin['RequiresPHP'],
			'last_updated'  => $release['date'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => '<p>' . esc_html( $plugin['Description'] ) . '</p>' . $features,
				'changelog'   => $release['changelog']
					. '<p><a href="' . esc_url( 'https://github.com/' . self::REPO . '/blob/main/CHANGELOG.md' ) . '">Full changelog</a></p>',
			),
		);
	}
}
