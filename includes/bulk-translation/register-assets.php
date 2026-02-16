<?php

namespace AUTOML_WPML\Includes\Bulk_Translation;

if ( ! defined( 'ABSPATH' ) ) exit;

use AUTOML_WPML\Helper\Helper;
use WPML_AT_Helper;
/**
 * Register_Assets
 *
 * @package AUTOML_WPML\Includes\Bulk_Translation
 */
class Register_Assets {
	public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_bulk_translate_assets'));
	}

	public function enqueue_bulk_translate_assets() {
        $current_screen = function_exists('get_current_screen') ? get_current_screen() : false;

        if(!$current_screen){
            return;
        }
        
        if(!class_exists(Helper::class) || !Helper::tranlastable_post_type($current_screen)){
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
        $post_status=isset($_GET['post_status']) ? sanitize_text_field(wp_unslash($_GET['post_status'])) : '';

        if('trash' === $post_status){
            return;
        }

        $post_label=__("Pages", "automl-ai-translation-for-wpml");
        $taxonomy_page=false;

        if(isset($current_screen->post_type)){
            $post_type = $current_screen->post_type;

            if(isset(get_post_type_object($post_type)->label) && !empty(get_post_type_object($post_type)->label)){
                $post_label = get_post_type_object($post_type)->label;
            }

            if(isset($current_screen->taxonomy) && !empty($current_screen->taxonomy)){
                $taxonomy_page=$current_screen->taxonomy;    
                $taxonomy_object = get_taxonomy($current_screen->taxonomy);

                if(isset($taxonomy_object->label) && !empty($taxonomy_object->label)){
                    $post_label = $taxonomy_object->label;

                    if(isset($taxonomy_object->labels->singular_name) && !empty($taxonomy_object->labels->singular_name)){
                        $post_label = $taxonomy_object->labels->singular_name;
                    }
                }
            }
        }
        
        $slug_translation_option = get_option('automl_wpml_slug_translation_option','title_translate');

        $editor_script_asset = include WPML_AT_PLUGIN_DIR . 'assets/bulk-translate/index.asset.php';
        
        $rtl=function_exists('is_rtl') ? is_rtl() : false;
        $css_file=$rtl ? 'index-rtl.css' : 'index.css';
      
        wp_enqueue_script('automl-wpml-bulk-translate', WPML_AT_PLUGIN_URL . 'assets/bulk-translate/index.js', $editor_script_asset['dependencies'], $editor_script_asset['version'], true);
        wp_enqueue_style('automl-wpml-bulk-translate', WPML_AT_PLUGIN_URL . 'assets/bulk-translate/'.$css_file, array(), $editor_script_asset['version']);

        $languages = WPML_AT_Helper::get_wpml_languages();

        $lang_object = array();

        $default_language=WPML_AT_Helper::get_default_language();
		$default_language_slug=$default_language;

        foreach ($languages as $lang) {
            $lang_object[$lang['code']] = array('name' => $lang['name'], 'flag' => $lang['flag_url'], 'locale' => $lang['locale']);
        }

        $available_ai_services = array();

		// Use transient to avoid isProviderConfigured() HTTP requests on every string translation page load.
        if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
            $cache_key = 'automl_wpml_configured_providers';
            $cached    = get_transient( $cache_key );
            if ( false !== $cached && is_array( $cached ) ) {
                $available_ai_services = $cached;
            } else {
                $registry     = \WordPress\AiClient\AiClient::defaultRegistry();
                $provider_ids = $registry->getRegisteredProviderIds();
                foreach ( $provider_ids as $provider_id ) {
                    if ( $registry->isProviderConfigured( $provider_id ) ) {
                        $available_ai_services[] = $provider_id;
                    }
                }
                set_transient( $cache_key, $available_ai_services, 24 * HOUR_IN_SECONDS );
            }
        }

        $extra_data = array();

        $extra_data['postMetaSync'] = 'false';

        $ai_max_tokens=get_option('automl_wpml_ai_request_token_per_request', 500);
        $ai_batch_size=get_option('automl_wpml_ai_request_batch_size', 5);

        wp_localize_script(
            'automl-wpml-bulk-translate',
            'automl_wpml_bulk_translate_object',
            array_merge(array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'languageObject' => $lang_object,
                'nonce' => wp_create_nonce('wp_rest'),
                'bulkTranslateRouteUrl' => get_rest_url(null, 'automl-bulk-translate'),
                'bulkTranslatePrivateKey' => wp_create_nonce('automl_wpml_bulk_translate_entries_nonce'),
                'automl_wpml_url'           => esc_url(WPML_AT_PLUGIN_URL),
                'AIServices' => $available_ai_services,
                'admin_url' => admin_url(),
                'ai_translate_route_nonce' => wp_create_nonce('wp_rest'),
                'ai_translate_nonce' => wp_create_nonce('automl_wpml_ai_translate_nonce'),
				'get_glossary_validate' => wp_create_nonce('automl_wpml_get_glossary_private'),
                'post_label' => $post_label,
                'update_translate_data' => 'automl_wpml_update_translate_data',
                'slug_translation_option' => $slug_translation_option,
                'taxonomy_page' => $taxonomy_page,
                'AIRequestMaxTokens' => $ai_max_tokens,
                'AIRequestBatchSize' => $ai_batch_size,
                'automl_wpml_glossary_nonce' => wp_create_nonce('automl_wpml_glossary_nonce'),
                'default_language_slug' => $default_language_slug,
            ), $extra_data)
        );  
	}
}

new Register_Assets();