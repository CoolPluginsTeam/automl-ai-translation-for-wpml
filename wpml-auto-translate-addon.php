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
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPML_AT_VERSION', '1.0.0' );
define( 'WPML_AT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPML_AT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPML_AT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

use AUTOML_WPML\Includes\Routes\Bulk_Translation_Route;
use AUTOML_WPML\Helper\Helper;

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
	 */
	private function load_dependencies() {
		require_once WPML_AT_PLUGIN_DIR . 'helper/helper.php';

		require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/get-package-content.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/builder/content-update-base.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/builder/elementor-widgets-update.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/builder/gutenberg-blocks-update.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/create-translated-post.php';
		
		require_once WPML_AT_PLUGIN_DIR . 'includes/bulk-translation/bulk-translation.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/bulk-translation/register-assets.php';
		
		require_once WPML_AT_PLUGIN_DIR . 'includes/routes/bulk-translation-route.php';

		require_once WPML_AT_PLUGIN_DIR . 'includes/class-wpml-at-helper.php';
		require_once WPML_AT_PLUGIN_DIR . 'includes/class-wpml-at-ajax.php';
		require_once WPML_AT_PLUGIN_DIR . 'admin/class-wpml-at-admin.php';
		require_once WPML_AT_PLUGIN_DIR . 'admin/class-wpml-at-widget.php';
	}

	/**
	 * Initialize plugin.
	 */
	private function init() {
		// Check if WPML is active.
		if ( ! $this->is_wpml_active() ) {
			add_action( 'admin_notices', array( $this, 'wpml_missing_notice' ) );
			return;
		}

		// Initialize components.
		new Bulk_Translation_Route('automl-bulk-translation');
		new WPML_AT_Helper();
		new WPML_AT_Ajax();
		new WPML_AT_Admin();
		new WPML_AT_Widget();
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
	 */
	public function wpml_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'WPML Auto Translate Addon requires WPML to be installed and activated.', 'wpml-auto-translate-addon' ); ?></p>
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
