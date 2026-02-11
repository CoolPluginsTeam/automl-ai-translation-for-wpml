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
$autoload = WPML_AT_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

use WordPress\AI_Client\AI_Client;

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
	
		// Add our model selector section to the wp-ai-client settings page.
		add_action( 'load-settings_page_wp-ai-client', array( $this, 'register_model_selector_section' ) );
	}
	
	/**
	 * Register the model selector section and setting on the wp-ai-client page.
	 */
	public function register_model_selector_section() {
		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return;
		}
	
		// Register our option with the same option group wp-ai-client uses.
		// Group is 'wp-ai-client-settings' (from API_Credentials_Manager::OPTION_GROUP).
		if ( ! isset( get_registered_settings()[ self::OPTION_TRANSLATION_MODELS ] ) ) {
			register_setting(
				'wp-ai-client-settings',
				self::OPTION_TRANSLATION_MODELS,
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
	
		add_settings_section(
			'wp-ai-client-model-selector',
			__( 'Translation Models (WPML Addon)', 'wpml-auto-translate-addon' ),
			array( $this, 'render_model_selector_section' ),
			'wp-ai-client'
		);
	}
	public const OPTION_TRANSLATION_MODELS = 'wpml_at_ai_translation_models';
	
	/**
	 * Render per-provider model dropdowns using the AI SDK registry.
	 */
	public function render_model_selector_section() {
		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			echo '<p class="description">' . esc_html__( 'AI SDK is not available.', 'wpml-auto-translate-addon' ) . '</p>';
			return;
		}
	
		$registry      = \WordPress\AiClient\AiClient::defaultRegistry();
		$provider_ids  = $registry->getRegisteredProviderIds();
		$saved         = get_option( self::OPTION_TRANSLATION_MODELS, array() );
		$option_name   = self::OPTION_TRANSLATION_MODELS;
	
		echo '<p class="description">' . esc_html__( 'Select which model to use for translation per provider. Save your API keys first, then choose a model and click “Save Changes”.', 'wpml-auto-translate-addon' ) . '</p>';
	
		foreach ( $provider_ids as $provider_id ) {
			if ( ! $registry->isProviderConfigured( $provider_id ) ) {
				continue;
			}
	
			$class_name        = $registry->getProviderClassName( $provider_id );
			$provider_metadata = $class_name::metadata();
			$provider_name     = $provider_metadata->getName();
	
			try {
				$directory = $class_name::modelMetadataDirectory();
				$models    = $directory->listModelMetadata();
			} catch ( \Throwable $e ) {
				echo '<p><strong>' . esc_html( $provider_name ) . '</strong>: <em>' . esc_html__( 'Could not load models.', 'wpml-auto-translate-addon' ) . ' ' . esc_html( $e->getMessage() ) . '</em></p>';
				continue;
			}
	
			if ( empty( $models ) ) {
				echo '<p><strong>' . esc_html( $provider_name ) . '</strong>: <em>' . esc_html__( 'No models returned.', 'wpml-auto-translate-addon' ) . '</em></p>';
				continue;
			}
	
			$current    = isset( $saved[ $provider_id ] ) ? $saved[ $provider_id ] : '';
			$field_name = $option_name . '[' . esc_attr( $provider_id ) . ']';
	
			echo '<p style="margin-bottom:0.5em;"><label for="wpml-at-model-' . esc_attr( $provider_id ) . '"><strong>' . esc_html( $provider_name ) . '</strong></label></p>';
			echo '<select id="wpml-at-model-' . esc_attr( $provider_id ) . '" name="' . esc_attr( $field_name ) . '" style="min-width:220px;max-width:100%;">';
			echo '<option value="">' . esc_html__( '— Select model for translation —', 'wpml-auto-translate-addon' ) . '</option>';
	
			foreach ( $models as $model ) {
				$id   = $model->getId();
				$name = $model->getName();
				$label = ( $name !== $id ) ? $name . ' (' . $id . ')' : $id;
				echo '<option value="' . esc_attr( $id ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $label ) . '</option>';
			}
	
			echo '</select>';
		}
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
			'includes/class-wpml-at-strings-ajax.php',
			'includes/class-cp-wpml-google-auto-translate-ajax.php',
			'includes/routes/bulk-translation-route.php',
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
		if ( class_exists( 'CP_WPML_Google_Auto_Translate_Ajax' ) ) {
			CP_WPML_Google_Auto_Translate_Ajax::init();
		}
		if ( class_exists( 'WPML_AT_Strings_Ajax' ) ) {
			WPML_AT_Strings_Ajax::init();
		}
        if ( class_exists( '\AUTOML_WPML\Includes\Routes\Bulk_Translation_Route' ) ) {
			new \AUTOML_WPML\Includes\Routes\Bulk_Translation_Route( 'automl-wpml-translate' );
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
