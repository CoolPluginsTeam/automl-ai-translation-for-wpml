<?php

namespace AUTOML_WPML\Includes\Bulk_Translation;

if ( ! defined( 'ABSPATH' ) ) exit;

use AUTOML_WPML\Helper\Helper;
use WPML_Auto_Cpt_Dashboard;

/**
 * Bulk_Translation
 *
 * @package AUTOML_WPML\Includes\Bulk_Translation
 */
class Bulk_Translation {
	/**
         * Single instance of the class
         *
         * @var self
         */
        private static $instance;

        public static function get_instance()
        {
            if(!isset(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct()
        {
            add_action('current_screen', array($this, 'bulk_translate_btn'));
            add_action('wp_ajax_automl_wpml_update_translate_data', array($this, 'update_translate_data'));
        }

        public function update_translate_data(){
            if ( ! check_ajax_referer( 'automl_wpml_update_translate_data', 'translate_data_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'automl-ai-translation-for-wpml' ) );
				wp_die( '0', 400 );
			}

			$post_id = isset($_POST['post_id']) ? absint(sanitize_text_field($_POST['post_id'])) : 0;
			$editor_type = isset($_POST['editorType']) ? sanitize_text_field($_POST['editorType']) : '';
			$extra_data=isset($_POST['extraData']) ? json_decode(wp_unslash($_POST['extraData']), true) : [];
			$bulk_translate=isset($_POST['bulk_translate']) && 'true' === $_POST['bulk_translate'] ? true : false;

			// Require capability based on context
			if ( $post_id > 0 ) {
				if ( ! current_user_can('edit_post', $post_id) ) {
					wp_send_json_error( __( 'Unauthorized to edit post', 'automl-ai-translation-for-wpml' ), 403 );
					wp_die( '0', 403 );
				}
			} else {
				if ( ! current_user_can('edit_posts') ) {
					wp_send_json_error( __( 'Unauthorized', 'automl-ai-translation-for-wpml' ), 403 );
					wp_die( '0', 403 );
				}
			}

			$provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
			$total_string_count = isset($_POST['totalStringCount']) ? absint($_POST['totalStringCount']) : 0;
			$total_word_count = isset($_POST['totalWordCount']) ? absint($_POST['totalWordCount']) : 0;
			$total_char_count = isset($_POST['totalCharacterCount']) ? absint($_POST['totalCharacterCount']) : 0;
			$date = isset($_POST['date']) ? date('Y-m-d H:i:s', strtotime(sanitize_text_field($_POST['date']))) : '';
			$source_string_count = isset($_POST['sourceStringCount']) ? absint($_POST['sourceStringCount']) : 0;
			$source_word_count = isset($_POST['sourceWordCount']) ? absint($_POST['sourceWordCount']) : 0;
			$source_char_count = isset($_POST['sourceCharacterCount']) ? absint($_POST['sourceCharacterCount']) : 0;
			$source_lang = isset($_POST['sourceLang']) ? sanitize_text_field($_POST['sourceLang']) : '';
			$target_lang = isset($_POST['targetLang']) ? sanitize_text_field($_POST['targetLang']) : '';
			$time_taken = isset($_POST['timeTaken']) ? absint($_POST['timeTaken']) : 0;

			if (class_exists('WPML_Auto_Cpt_Dashboard')) {
				$translation_data = array(
					'post_id' => $post_id,
					'service_provider' => $provider,
					'source_language' => $source_lang,
					'target_language' => $target_lang,
					'time_taken' => $time_taken,
					'string_count' => $total_string_count,
					'word_count' => $total_word_count,
					'character_count' => $total_char_count,
					'source_string_count' => $source_string_count,
					'source_word_count' => $source_word_count,
					'source_character_count' => $source_char_count,
					'editor_type' => $editor_type,
					'date_time' => $date,
					'version_type' => 'pro'
				);

				if(!empty($extra_data) && is_array($extra_data) && count($extra_data) > 0){
					foreach($extra_data as $key => $value){
						if(!isset($translation_data[$key]) && !empty($value) && !empty($key)){
							$translation_data[sanitize_text_field($key)] = sanitize_text_field($value);
						}
					}
				}

				WPML_Auto_Cpt_Dashboard::store_options(
					'automl_wpml',
					'post_id', 
					'update',
					$translation_data
				);

				wp_send_json_success(array(
					'message' => __('Translation data updated successfully', 'automl-ai-translation-for-wpml')
				));
			} else {
				wp_send_json_error(array(
					'message' => __('WPML_Auto_Cpt_Dashboard class not found', 'automl-ai-translation-for-wpml') 
				));
			}
			exit;
        }

        public function bulk_translate_btn($screen)
        {
            if(!class_exists(Helper::class) || !Helper::tranlastable_post_type($screen)){
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
            $post_status=isset($_GET['post_status']) ? sanitize_text_field(wp_unslash($_GET['post_status'])) : '';
            
            if('trash' === $post_status){
                return;
            }

            add_filter( "views_{$screen->id}", array($this, 'automl_wpml_bulk_translate_button') );

            add_action('admin_footer', array($this, 'bulk_translate_container'));
        }

        public function automl_wpml_bulk_translate_button($views)
        {
            echo "<button class='button automl-wpml-bulk-translate-btn' style='display:none;'>Bulk Translate</button>";

            return $views;
        }

        public function bulk_translate_container()
        {
            echo "<div id='automl-wpml-bulk-translate-wrapper'></div>";
        }
}

Bulk_Translation::get_instance();

