<?php
/**
 * Plugin Name: AutoML - AI Translation for WPML
 * Description: Adds "Translate with Google" bulk and per-row actions to WPML Translation Dashboard, using Google Translate for saving translations and Google website widget for preview.
 * Version: 1.0.0
 * Author: Cool Plugins
 * Text Domain: automl-ai-translation-for-wpml
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
$automl_wpml_autoload = WPML_AT_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $automl_wpml_autoload ) ) {
	require_once $automl_wpml_autoload;
}

use WordPress\AI_Client\AI_Client;

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
	public function __construct() {
		$this->load_dependencies();
		$this->init();
		add_action( 'init', array( AI_Client::class, 'init' ) );
	
		add_action( 'admin_init', array( $this, 'register_ai_model_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_wpml_auto_dashboard_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'hide_wp_ai_client_menu' ), 99 );
	}

	public function register_ai_model_setting() {
		register_setting(
			'wp-ai-client-settings',              // option group (matches settings_fields in settings.php)
			'wpml_at_ai_translation_models',      // option name
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$out = array();
					foreach ( $value as $provider_id => $model_id ) {
						if ( is_string( $provider_id ) && is_string( $model_id ) && $provider_id !== '' && $model_id !== '' ) {
							$out[ sanitize_key( $provider_id ) ] = sanitize_text_field( $model_id );
						}
					}
					return $out;
				},
			)
		);
	}

	public function register_wpml_auto_dashboard_menu() {
		global $menu;
	
		// Fallback parent slug if we can't detect WPML explicitly.
		$parent_slug = 'sitepress-multilingual-cms/menu/languages.php';
	
		// Try to find the actual WPML top-level slug from $menu.
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				// $item[0] is menu title, $item[2] is menu slug.
				if ( isset( $item[0], $item[2] ) && stripos( $item[0], 'WPML' ) !== false ) {
					$parent_slug = $item[2];
					break;
				}
			}
		}
	
		add_submenu_page(
			$parent_slug, // parent (WPML) menu slug
			__( 'WPML Auto Translate', 'automl-ai-translation-for-wpml' ), // page title
			__( 'WPML Auto Translate', 'automl-ai-translation-for-wpml' ),      // menu title
			'manage_options',       	                                  // capability
			'wpml-auto-dashboard',                                    // menu slug
			array( \WPML_Auto_Dashboard::get_instance(), 'wpml_auto_render_dashboard_page' ) // callback
		);
	}


	public function hide_wp_ai_client_menu() {
		// Remove "AI Credentials" submenu under Settings.
		remove_submenu_page( 'options-general.php', 'wp-ai-client' );
	
		// In case wp-ai-client ever added a top-level menu (defensive).
		remove_menu_page( 'wp-ai-client' );
	}
	
	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			'helper/helper.php',
			'helper/sanitized-content.php',
			'includes/wpml/builder/gutenberg/update-block-config.php',
			'includes/wpml/get-package-content.php',
			'includes/wpml/builder/content-update-base.php',
			'includes/wpml/builder/elementor/elementor-update.php',
			'includes/wpml/builder/gutenberg/gutenberg-update.php',
			'includes/wpml/create-translated-post.php',
			'includes/bulk-translation/bulk-translation.php',
			'includes/string-translation/string-translation.php',
			'includes/bulk-translation/register-assets.php',
			'includes/string-translation/register-assets.php',
			'includes/class-wpml-at-helper.php',
			'admin/class-wpml-at-admin.php',
			'includes/class-wpml-at-strings-ajax.php',
			'includes/routes/bulk-translation-route.php',
			'admin/class-wpml-auto-dashboard.php',
			'admin/cpt_dashboard/cpt_dashboard.php',
			'modules/wizard/load.php',
		);

		foreach ( $files as $file ) {
			$file_path = WPML_AT_PLUGIN_DIR . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
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
		if ( class_exists( WPML_AT_Strings_Ajax::class ) ) {
			WPML_AT_Strings_Ajax::init();
		}
        if ( class_exists( Bulk_Translation_Route::class ) ) {
			new Bulk_Translation_Route( 'automl-bulk-translate' );
		}
		
		// Initialize admin classes.
		if ( is_admin() ) {
			if ( class_exists( 'WPML_AT_Admin' ) ) {
				new WPML_AT_Admin();
			}
			if ( class_exists( 'WPML_Auto_Dashboard' ) ) {
				WPML_Auto_Dashboard::get_instance();
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
				<strong><?php esc_html_e( 'WPML Auto Translate Addon:', 'automl-ai-translation-for-wpml' ); ?></strong>
				<?php esc_html_e( 'This plugin requires WPML to be installed and activated.', 'automl-ai-translation-for-wpml' ); ?>
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

register_activation_hook( __FILE__, array( \AUTOML_WPML\Modules\Wizard\WPML_AT_Wizard::class, 'start_wizard' ) );

// Start the plugin.
wpml_auto_translate_addon();
