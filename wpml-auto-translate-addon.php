<?php
/**
 * Plugin Name: WPML Google Auto Translate Addon (Google Translate + Preview)
 * Description: Adds "Translate with Google" bulk and per-row actions to WPML Translation Dashboard, using Google Translate for saving translations and Google website widget for preview.
 * Version: 1.0.0
 * Author: Cool Plugins
 * Text Domain: wpml-auto-translate-addon
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'WPML_AT_VERSION' ) ) {
	define( 'WPML_AT_VERSION', '1.0.0' );
}
if ( ! defined( 'WPML_AT_PLUGIN_DIR' ) ) {
	define( 'WPML_AT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPML_AT_PLUGIN_URL' ) ) {
	define( 'WPML_AT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPML_AT_PLUGIN_BASENAME' ) ) {
	define( 'WPML_AT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Main plugin class.
 */
final class WPML_Auto_Translate_Addon {

	/**
	 * Plugin instance.
	 *
	 * @var WPML_Auto_Translate_Addon
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return WPML_Auto_Translate_Addon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			'includes/class-wpml-at-helper.php',
			'includes/class-wpml-engine.php',
			'admin/class-wpml-at-admin.php',
			'admin/class-wpml-at-widget.php',
			'admin/class-wpml-at-supported-blocks.php',
			'admin/class-wpml-at-custom-block-post.php',
			'includes/class-cp-wpml-google-auto-translate-ajax.php',
		);

		foreach ( $files as $file ) {
			$file_path = WPML_AT_PLUGIN_DIR . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}

		// Load string translation AJAX only when WPML String Translation is active.
				require_once WPML_AT_PLUGIN_DIR . 'includes/class-wpml-at-strings-ajax.php';
	}
	

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	private function init() {
		// Check if WPML is active.
		if ( ! $this->is_wpml_active() ) {
			add_action( 'admin_notices', array( $this, 'wpml_missing_notice' ) );
			return;
		}

		// Initialize AJAX handlers.
		if ( class_exists( 'CP_WPML_Google_Auto_Translate_Ajax' ) ) {
			CP_WPML_Google_Auto_Translate_Ajax::init();
		}
		if ( class_exists( 'WPML_AT_Strings_Ajax' ) ) {
			add_action( 'wp_loaded', array( 'WPML_AT_Strings_Ajax', 'init' ), 20 );
		}

		// Initialize admin classes.
		if ( is_admin() ) {
			if ( class_exists( 'WPML_AT_Admin' ) ) {
				new WPML_AT_Admin();
			}
			if ( class_exists( 'WPML_AT_Widget' ) ) {
				new WPML_AT_Widget();
			}
			if ( class_exists( 'WPML_AT_Supported_Blocks' ) ) {
				WPML_AT_Supported_Blocks::get_instance();
			}
			if ( class_exists( 'WPML_AT_Custom_Block_Post' ) ) {
				WPML_AT_Custom_Block_Post::get_instance();
			}
		}
	}

	/**
	 * Check if WPML is active.
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
	}

	/**
	 * Display notice if WPML is not active.
	 *
	 * @return void
	 */
	public function wpml_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'WPML Auto Translate Addon:', 'wpml-auto-translate-addon' ); ?></strong>
				<?php esc_html_e( 'This plugin requires WPML to be installed and activated.', 'wpml-auto-translate-addon' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Initialize the plugin.
 */
function wpml_auto_translate_addon() {
	return WPML_Auto_Translate_Addon::get_instance();
}

// Start the plugin.
wpml_auto_translate_addon();
