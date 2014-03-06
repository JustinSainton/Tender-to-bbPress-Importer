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
	private $api;


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
		if ( ! defined( 'BBP_TENDER_PLUGIN_FILE' ) ) {
			define( 'BBP_TENDER_PLUGIN_FILE', __FILE__ );
		}

		/* Sets constants for API key and base for local work, ignored by GitHub */
		if ( file_exists( BBP_TENDER_PLUGIN_DIR . 'inc/client.local.php' ) ) {
			require_once BBP_TENDER_PLUGIN_DIR . 'inc/client.local.php';
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
		add_filter( 'http_request_args', array( self::$instance->api, 'http_request_headers' ), 10, 2 );
	}

	public static function insert_topic( array $data ) {
		$topic_data['post_parent']  = self::find_forum( $data ); // forum ID
		$topic_data['post_author']  = self::find_user( $data ),
		$topic_data['post_content'] = '';
		$topic_data['post_title']   = '';

		$topic_meta['forum_id'] = $topic_data['post_parent'];

		return bbp_insert_topic( $topic_data, $topic_meta );
	}

	public static function insert_reply( array $data ) {

		$reply_data['post_parent']  = $data['topic_id']; // topic ID
		$reply_data['post_author']  = self::find_user( $data['email'] ),
		$reply_data['post_content'] = $data['content'];
		$reply_data['post_title']   = '';

		$topic_meta['topic_id'] = $reply_data['post_parent'];
		$topic_meta['forum_id'] = $data['forum_id'];

		return bp_insert_reply( $reply_data, $reply_meta );
	}

	public static function maybe_set_as_private( $reply_id ) {
		if ( ! class_exists( 'BBP_Private_Replies' ) ) {
			return;
		}

		/* Cause private topics to be marked as private */
		update_post_meta( $reply_id, '_bbp_reply_is_private', '1' );
	}

	public static function maybe_set_as_resolved( $topic_id ) {

		if ( ! function_exists( 'edd_bbp_d_setup' ) ) {
			return;
		}

		/* Cause all topics to be marked as resolved */
		update_post_meta( $topic_id, '_bbps_topic_status', '2' );
	}

	public static function find_user( $data ) {
		$default = bbp_get_current_user_id();

	}

	public static function find_forum( $data ) {

	}

	public static function process_api_response() {

		$page     = isset( $_GET['page'] ) ? (string) absint( $_GET['page'] ) : '1';
		$response = self::$instance->api->get_discussions( array( 'page' => $page ) );

		if ( ! is_object( $response ) ) {
			return false;
		}

		/* Sets options for the the total amount of discussions being processed as well as what the most discussion was. */
		if ( '1' === $page ) {
			$comment_id = array_pop( explode( '/', $response->discussions[0]->href ) );
			update_option( '_bbpress_tender_import_total_count', $response->total );
			update_option( '_bbpress_tender_import_since'      , $comment_id );
		}

		foreach ( $response->discussions as $discussions ) {
			self::process_discussion( $discussion );
		}
	}
	
	/* TODO: bbPress treats Topics as, basically, Reply #1, while Tender treats them as, well, replies.  Accommodate that. */
	public static function process_discussion( $discussion ) {

		$data = array();

		$topic_id = self::insert_topic( $data );
		
		self::maybe_set_as_resolved( $topic_id );
	}

	public static function admin_notice() {
		
		?>
			<script type="text/javascript">
			window.location = 'index.php?bbpress_tender_page=<?php echo absint( $page ); ?>';
			</script>
		<?php
	}

}

/* Get the class instance */
add_action( 'plugins_loaded', array( 'bbPress_Tender_Importer', 'get_instance' ) );