<?php

namespace AUTOML_WPML\Includes\String_Translation;

if ( ! defined( 'ABSPATH' ) ) exit;

use AUTOML_WPML\Helper\Helper;
use WPML_AT_Helper;
/**
 * Register_Assets
 *
 * @package AUTOML_WPML\Includes\String_Translation
 */
class Register_Assets {
	public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

		/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets($hook)
	{
		// Sanitize and validate page parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic, not processing form data.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		$is_string_translation     = ! empty($page) && strpos($page, 'wpml-string-translation/menu/string-translation.php') !== false;
		$needs_ai_services         = $is_string_translation;
		$available_ai_services     = array();

		// Enqueue translation dashboard/post list scripts.
		if ( $needs_ai_services ) {

			if ( $needs_ai_services ) {
				// Build from saved credentials so button shows "Add API Key" when key is missing/empty.
				$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
				if ( is_array( $credentials ) ) {
					foreach ( array( 'openai', 'google' ) as $provider_id ) {
						if ( ! empty( $credentials[ $provider_id ] ) && is_string( $credentials[ $provider_id ] ) ) {
							$available_ai_services[] = $provider_id;
						}
					}
				}
			}
			}

			$languages = WPML_AT_Helper::get_wpml_languages();
			$default_language = WPML_AT_Helper::get_default_language();
			$lang_object = array();

			foreach ($languages as $lang) {
				$lang_object[$lang['code']] = array('name' => $lang['name'], 'flag' => $lang['flag_url']);
			}
			wp_localize_script(
				'cp-wpml-auto-translate-admin',
				'CP_WPML_AUTO_TRANSLATE',
				array(
					'ajax'      => esc_url(admin_url('admin-ajax.php')),
					'nonce'     => wp_create_nonce('cp_wpml_auto_translate_nonce'),
					'languages' => $languages,
					'admin_url' => esc_url(admin_url()),
					'i18n'      => array(
						'errorPageId'      => esc_html__('Could not detect page ID for this row.', 'automl-ai-translation-for-wpml'),
						'errorNoSelection' => esc_html__('Please select at least one post to translate.', 'automl-ai-translation-for-wpml'),
						'errorNoLanguage'  => esc_html__('Please select a target language.', 'automl-ai-translation-for-wpml'),
						'errorInvalidData' => esc_html__('Invalid post ID or language.', 'automl-ai-translation-for-wpml'),
						'errorNoStrings'   => esc_html__('No translation strings found.', 'automl-ai-translation-for-wpml'),
						'errorAjax'        => esc_html__('AJAX error while loading content.', 'automl-ai-translation-for-wpml'),
						'errorAjaxSave'    => esc_html__('AJAX error while saving.', 'automl-ai-translation-for-wpml'),
						'errorUnknown'     => esc_html__('Unknown error occurred.', 'automl-ai-translation-for-wpml'),
					),
				)
			);

			// Enqueue bulk translate build files on string translation page.
			if ($is_string_translation) {

				$asset_file = include WPML_AT_PLUGIN_DIR . 'assets/bulk-string-translate/index.asset.php';

				wp_enqueue_script(
					'wpml-at-bulk-translate',
					WPML_AT_PLUGIN_URL . 'assets/bulk-string-translate/index.js',
					$asset_file['dependencies'],
					$asset_file['version'],
					true
				);

				wp_enqueue_style(
					'wpml-at-bulk-translate',
					WPML_AT_PLUGIN_URL . 'assets/bulk-string-translate/index.css',
					array(),
					$asset_file['version']
				);

				// Localize script with necessary data for string translation
				wp_localize_script(
					'wpml-at-bulk-translate',
					'automl_wpml_bulk_translate_object',
					array(
						'taxonomy_page'          => '',
						'languageObject'         => $lang_object,
						'ajax'                   => esc_url( admin_url( 'admin-ajax.php' ) ),
						'nonce'                  => wp_create_nonce( 'cp_wpml_auto_translate_nonce' ),
						'default_language_slug'  => $default_language,
						'bulkTranslateRouteUrl' => get_rest_url(null, 'automl-bulk-translate'),
						'bulkTranslatePrivateKey' => wp_create_nonce('automl_wpml_bulk_translate_entries_nonce'),
						'automl_wpml_url'           => esc_url(WPML_AT_PLUGIN_URL),
						'AIServices' => $available_ai_services,
						'admin_url' => admin_url(),
						'ai_translate_route_url' => get_rest_url(null, 'automl-bulk-translate'),
						'ai_translate_route_nonce' => wp_create_nonce('wp_rest'),
						'ai_translate_nonce' => wp_create_nonce('automl_wpml_ai_translate_nonce'),
						'get_glossary_validate' => wp_create_nonce('automl_wpml_get_glossary_private'),
					)
				);
			}
		}
}

new Register_Assets();