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

	/**
	 * @var A simple user cache, so we're not hitting the DB multiple times when searching for users.
	 * @since 0.7
	 */
	private static $user_cache = array();

	/**
	 * @var A simple user cache, so we're not hitting the DB multiple times when searching for forums.
	 * @since 0.7
	 */
	private static $forums_cache = array();


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
		add_action( 'admin_notices', array( self::$instance, 'admin_notice' ) );
		add_action( 'admin_init'   , array( self::$instance, 'shutdown' ) );	
	}

	public static function setup_filters() {
		/* Sets required headers for Tender HTTP requests */
		add_filter( 'http_request_args', array( self::$instance->api, 'http_request_headers' ), 10, 2 );
	}

	public static function insert_topic( array $data ) {
		$topic_data = $topic_meta = array();

		$topic_data['post_parent']  = self::find_forum( $data['link'] ); // forum ID
		$topic_data['post_author']  = self::find_user( $data['email'] );
		$topic_data['post_content'] = ''; // We circle back to this in the first reply.
		$topic_data['post_title']   = $data['title'];

		$topic_meta['forum_id'] = $topic_data['post_parent'];

		return bbp_insert_topic( $topic_data, $topic_meta );
	}

	public static function insert_reply( array $data ) {
		$reply_data = $reply_meta = array(); 

		$reply_data['post_parent']  = $data['topic_id']; // topic ID
		$reply_data['post_author']  = self::find_user( $data['email'] );
		$reply_data['post_content'] = $data['content'];
		$reply_data['post_title']   = $data['title'];

		$topic_meta['topic_id'] = $reply_data['post_parent'];
		$topic_meta['forum_id'] = get_post_field( 'post_parent', $data['topic_id'], 'db' );

		return bp_insert_reply( $reply_data, $reply_meta );
	}

	public static function maybe_set_as_private( $reply_id ) {
		if ( ! class_exists( 'BBP_Private_Replies' ) ) {
			return null;
		}

		/* Cause private topics to be marked as private */
		return update_post_meta( $reply_id, '_bbp_reply_is_private', '1' );
	}

	public static function maybe_set_as_resolved( $topic_id ) {

		if ( ! function_exists( 'edd_bbp_d_setup' ) ) {
			return null;
		}

		/* Cause all topics to be marked as resolved */
		return update_post_meta( $topic_id, '_bbps_topic_status', '2' );
	}

	public static function find_user( $email ) {

		if ( isset( self::$user_cache[ $email ] ) ) {
			return self::$user_cache[ $email ];
		}

		$user = get_user_by( 'email', $email );

		$user_id = $user ? $user->ID : bbp_get_current_user_id();
		
		/* Creates a user cache, so we're not hitting the database bunches of times for the same user. */
		self::$user_cache[ $email ] = $user_id;

		return $user_id;
	}

	public static function find_forum( $link ) {
		$bits  = explode( '/', $link );
		$slug  = $bits[4];
		$forum = str_replace( '-wordpress-theme', '', $slug );

		if ( isset( $forums_cache[ $forum ] ) ) {
			return $forums_cache[ $forum ];
		}

		$forum_id = get_posts( 
					array( 
						'posts_per_page' => 1, 
						'post_type'      => bbp_get_forum_post_type(), 
						'name'           => $forum 
					) 
				);

		if ( ! empty( $forum_id ) ) {
			self::$forums_cache[ $forum ] = $forum_id->ID;
			return $forum_id->ID;
		} else {
			return false;
		}
	}

	public static function process_api_response() {

		$page     = isset( $_GET['bbpress_tender_page'] ) ? (string) absint( $_GET['bbpress_tender_page'] ) : '1';
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

		$i = 1;

		foreach ( $response->discussions as $discussion ) {
			self::process_discussion( $discussion, $i );
			$i++;
		}
	}

	public static function process_discussion( $discussion, $incrementor ) {
		$data = array();

		$data['link']  = esc_url_raw( $discussion->html_href );
		$data['email'] = is_email( $discussion->author_email ) ? sanitize_email( $discussion->author_email ) : '';
		$data['title'] = sanitize_text_field( $discussion->title );

		$topic_id = self::insert_topic( $data );
		
		self::maybe_set_as_resolved( $topic_id );

		self::process_replies( $topic_id, $discussion, $incrementor );
	}

	public static function process_replies( $topic_id, $discussion, $incrementor ) {
		$comment_count = $discussion->comments_count;

		$id  = array_pop( explode( '/', $discussion->href ) );
		$url = 'discussions/' . absint( $id ) . '/comments{?page}';

		$response = self::$instance->api->_request( $url );

		if ( ! is_object( $response ) ) {
			return false;
		}

		for ( $i = 0; $i < $comment_count; $i++ ) {

			/* If we're on the first comment, set as the content for the topic ID and continue */
			if ( 0 === $i ) {
				wp_update_post( array( 'ID' => $topic_id, 'post_content' => wp_kses_post( $response->comments[ $i ]->body ) ) );
				continue;
			}

			/* If we've just inserted the last reply of the last discussion, and we're on the last discussion in the batch, let's redirect safely to our admin page. */
			if ( 30 == $incrementor && $comment_count == $i ) {
				$page = absint( $discussion->offset + 1 );
				wp_safe_redirect( admin_url( 'index.php?bbpress_tender_page=' . $page ) );
				exit;
			}

			/* We're not on the first comment any more, we should insert a reply */
			$data = array();
			
			$data['topic_id'] = $topic_id; // topic ID
			$data['email']    = is_email( $response->comments[ $i ]->author_email ) ? sanitize_email( $response->comments[ $i ]->author_email ) : '';
			$data['content']  = wp_kses_post( $response->comments[ $i ]->body );
			$data['title']    = apply_filters( 'bbpress_tender_import_reply_title',  'Reply To: ' . sanitize_text_field( $response->title ), $response, $topic_id );

			$reply_id = self::insert_reply( $data );

			if ( ! (bool) $response->public ) {
				self::maybe_set_as_private( $reply_id );
			}
		}

	}

	public static function admin_notice() {
		/* This variable will be what we have just done. */
		$page = isset( $_GET['bbpress_tender_page'] ) ? $_GET['bbpress_tender_page'] : '';

		$totals = get_option( '_bbpress_tender_import_total_count' );
		$since  = get_option( '_bbpress_tender_import_since' );

		$message = '';

		if ( empty( $page ) ) {
			$message = __( 'Looks like you are running the Tender > bbPress Importer for the first time - awesome! <a href="index.php?bbpress_tender_page=1">Get started!</a>' );
		} else {
			$message = __( 'The importer is running. There are a total of ' . number_format_i18n( absint( $totals ) ) . ' discussions to import.  We are currently working on #' . ( $page * 30 ) . '.  This page will automatically refresh for the next batch.' );
		}
	?>
			<div id="notice" class="updated">
				<p><?php echo $message; ?></p>
			</div>
			<?php if ( ! empty( $page ) ) : ?>
				<script type="text/javascript">
					setTimeout( function() { window.location = 'index.php?bbpress_tender_page=<?php echo absint( $page + 1 ); ?>'; }, 5000 );
				</script>
			<?php endif; ?>
		<?php
	}

	public static function shutdown() {

		/* We only want to run this on legit admin requests */
		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		/* Don't run if we don't have a numeric QV set. */
		if ( ! isset( $_GET['bbpress_tender_page'] ) || ( isset( $_GET['bbpress_tender_page'] ) && ! is_numeric( $_GET['bbpress_tender_page'] ) ) ) {
			return;
		}

		self::process_api_response();
	}

}

/* Get the class instance */
add_action( 'plugins_loaded', array( 'bbPress_Tender_Importer', 'get_instance' ) );