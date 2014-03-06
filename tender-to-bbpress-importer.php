<?php
/**
 * Tender to bbPress Discussions Importer
 *
 * A very beta discussion importer from Tender into bbPress.
 *
 * @package   bbp-tender-import
 * @author    Justin Sainton <justin@zao.is>
 * @license   GPL-2.0+
 * @link      http://www.zao.is/
 *
 * @wordpress-plugin
 * Plugin Name: Tender to bbPress Discussions Importer
 * Plugin URI:  http://www.zao.is/creating/tender-bbpress-importer/
 * Description: Connects with Tender's neat little JSON API to batch import discussions.
 * Version:     0.7
 * Author:      Justin Sainton
 * Author URI:  http://zao.is/
 * Text Domain: bbp-tender-import
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 */

/* If this file is called directly, abort. */
if ( ! defined( 'WPINC' ) ) {
	die;
}

class bbPress_Tender_Importer {

	/**
	 * @var Tender_bbPress_Importer The one true Tender_bbPress_Importer
	 * @since 0.7
	 */
	private static $instance;

	/**
	 * @var Tender API The one true Tender API Object
	 * @since 0.7
	 */
	private static $api;


	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof bbPress_Tender_Importer ) ) {
			self::$instance = new bbPress_Tender_Importer;
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->load_textdomain();
			self::$instance->api = new WP_Tender_API();

			self::$instance->setup_actions();
			self::$instance->setup_filters();
		}

		return self::$instance;
	}

	public static function setup_constants() {

		/* Sets constants for API key and base for local work, ignored by GitHub */
		if ( file_exists( BBP_TENDER_PLUGIN_DIR . 'inc/client.local.php' ) ) {
			require_once BBP_TENDER_PLUGIN_DIR . 'inc/client.local.php';
		}

		// Plugin version
		if ( ! defined( 'BBP_TENDER_VERSION' ) ) {
			define( 'BBP_TENDER_VERSION', '0.7' );
		}

		// Plugin Folder Path
		if ( ! defined( 'BBP_TENDER_PLUGIN_DIR' ) ) {
			define( 'BBP_TENDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL
		if ( ! defined( 'BBP_TENDER_PLUGIN_URL' ) ) {
			define( 'BBP_TENDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File
		if ( ! defined( 'BBP_TENDER_FILE' ) ) {
			define( 'BBP_TENDER_FILE', __FILE__ );
		}

	}

	public static function includes() {
		require_once BBP_TENDER_PLUGIN_DIR . 'inc/tender-api-bindings.php';
	}

	public static function load_textdomain() {
		// Set filter for plugin's languages directory
		$bbp_tender_lang_dir = dirname( plugin_basename( BBP_TENDER_PLUGIN_FILE ) ) . '/languages/';
		$bbp_tender_lang_dir = apply_filters( 'bbp_tender_languages_directory', $bbp_tender_lang_dir  );

		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale',  get_locale(), 'bbp-tender-import' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'bbp-tender-import', $locale );

		// Setup paths to current locale file
		$mofile_local  = $bbp_tender_lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bbp-tender-import/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/bbp-tender-import folder
			load_textdomain( 'bbp-tender-import', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/bbp-tender-import/languages/ folder
			load_textdomain( 'bbp-tender-import', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'bbp-tender-import', false, $bbp_tender_lang_dir );
		}
	}

	public static function setup_actions() {
		
	}

	public static function setup_filters() {

		/* Sets required headers for Tender HTTP requests */
		add_filter( 'http_request_args', array( self::$instance->api, 'http_request_headers' ) );

	}

}

/* Get the class instance */
add_action( 'plugins_loaded', array( 'Tender_bbPress_Importer', 'get_instance' ) );