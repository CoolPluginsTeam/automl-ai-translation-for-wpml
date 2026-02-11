<?php

namespace AUTOML_WPML\Includes\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;
use AUTOML_WPML\Includes\Wpml\Get_Package_Content;

if ( ! class_exists( 'Bulk_Translation_Route' ) ) :
	/**
	 * Bulk_Translation_Route
	 *
	 * @package AUTOML_WPML\AI_Translate\Services\API\Helpers
	 */
	class Bulk_Translation_Route {
		/**
		 * The base name of the route.
		 *
		 * @var string
		 */
		private $base_name;

		/**
		 * Constructor
		 *
		 * @param string $base_name The base name of the route.
		 */
		public function __construct( $base_name ) {
			$this->base_name = $base_name;
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the routes
		 */
		public function register_routes() {
			register_rest_route(
				$this->base_name,
				'/(?P<slug>[\w-]+)/translate-text',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'ai_translation' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'slug'            => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'automl_wpml_nonce'      => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_automl_wpml_ai_translate_nonce' ),
						),
						'strings'         => array(
							'type'     => 'string',
							'required' => true,
						),
						'target_language' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);

			register_rest_route(
				$this->base_name,
				'/(?P<slug>[\w-]+)/bulk-translate-entries',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'bulk_translate_entries' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'ids'        => array(
							'type'     => 'string',
							'required' => true,
						),
						'lang'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'privateKey' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_automl_wpml_bulk_nonce' ),
						),
					),
				)
			);

			register_rest_route(
				$this->base_name,
				'/(?P<post_id>[\w-]+)/create-translate-post',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_translate_post' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'privateKey'      => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_automl_wpml_create_post_nonce' ),
						),
						'post_id'         => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'target_language' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'editor_type'     => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'source_language' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'post_title'      => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'post_content'    => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				)
			);
		}

		public function permission_only_admins( $request ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
			}

			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'automl-ai-translation-for-wpml' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
			}
			return true;
		}

		public function validate_automl_wpml_ai_translate_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automl_wpml_ai_translate_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'Invalid security token sent.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
		}

		public function validate_automl_wpml_bulk_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automl_wpml_bulk_translate_entries_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'You are not authorized to perform this action.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
		}

		public function validate_automl_wpml_create_post_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automl_wpml_create_translate_post_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'You are not authorized to perform this action.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
		}

		/**
		 * AI Translation
		 *
		 * @param WP_REST_Request $params The request parameters.
		 * @return WP_REST_Response The response.
		 */
		public function ai_translation( $params ) {
			// Check if the user is logged in and has the necessary capabilities
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			// Get the parameters from the request
			$params = $params->get_params();

			// Get the service slug
			$service_slug = $params['slug'];

			// Verify the nonce
			if ( ! wp_verify_nonce( $params['automl_wpml_nonce'], 'automl_wpml_ai_translate_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			// Check if the user has the necessary capabilities
			if ( wp_get_current_user()->has_cap( 'edit_posts' ) ) {
				// Check if the service is available
				if ( automl_wpml_ai_services()->is_service_available( $params['slug'] ) ) {

					// Get the strings
					$strings = $params['strings'];

					// Convert strings to array if it's a JSON string
					$string_array = is_string( $strings ) ? json_decode( $strings, true ) : $strings;

					if ( strpos( $strings, '&lt;' ) !== false && strpos( $strings, '&gt;' ) !== false ) {
						$strings = html_entity_decode( $strings );
					}

					// Get the target language
					$target_language = $params['target_language'];

					// Use the source language from params
					$source_language = $params['source_language'];

					// Get the custom prompt
					$custom_prompt = get_option( 'automl_wpml_context_aware', '' );

					$ai_request_timeout = get_option( 'automl_wpml_ai_request_timeout', 120 );

					// Only return the translation in the format of a JSON object with the keys being numeric values (matching the source keys), and the values being the translated strings
					$content = sprintf(
						'Instruction 1: Translate visible text content semantically into %s language. Provide a proper meaning-based translation.  
				    Instruction 2: Do not translate or modify any content inside square brackets []. These are shortcodes or dynamic placeholders and must remain exactly as they are.
				    Instruction 3: Preserve all HTML tags and their attributes such as class, id, data-*, etc. Do not alter any part of the HTML structure.
				    Instruction 4: Return the translation in the format of a JSON object with the keys being numeric values (matching the source keys), and the values being the translated strings.
				    Instruction 5: Do not escape double quotes with backslashes. Output must be valid JSON without extra slashes.
				    Instruction 6: Translate the provided JSON array into %s language, regardless of whether the values are the same and Ensure the JSON is well-formed and complete.
                    Instruction 7: Decode any &lt; and &gt; HTML entities back to < and > symbols in the output & preserve and maintain whitespace.
				    Instruction 8: Return the output as a valid JSON object. Do not wrap the output in a string or markdown code block. Ensure the JSON is clean, parseable, and properly formatted. Please ensure that the output follows the format: {"key(numeric value)": "(translations of the strings in %s language)}" Strings are :- %s',
						$target_language,
						$target_language,
						$target_language,
						json_encode( $strings )
					);

					// Try to generate the text
					try {
						
					} catch ( Exception $e ) {
						wp_send_json_error( 'Error during text generation: ' . $e->getMessage() );
					}
				} else {
					wp_send_json_error(
						sprintf(
							'%s service is not available.',
							$service_slug === 'google' ? 'GeminiAI' : ucfirst( $service_slug )
						)
					);
				}
			}

				wp_send_json_error( 'You are not authorized to perform this action.' );
		}

		public function bulk_translate_entries( $params ) {
			// Check if the user is logged in and has the necessary capabilities
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			// Verify the nonce
			if ( ! wp_verify_nonce( $params['privateKey'], 'automl_wpml_bulk_translate_entries_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if(!isset($params['lang']) || empty($params['lang'])) {
				wp_send_json_error( 'Empty target language Select at least one language' );
			}

			if(!isset($params['ids']) || empty($params['ids'])) {
				wp_send_json_error( 'Empty post IDs Select at least one post to translate' );
			}
			
			$active_languages = apply_filters( 'wpml_active_languages', null, null );
			
			$active_languages_slugs = array_column($active_languages, 'code');
			
			$post_ids           = json_decode( $params['ids'] );
			$post_ids = array_map('absint', $post_ids);
			$target_language = json_decode($params['lang']);
			$target_language = array_map('sanitize_text_field', $target_language);

			$valid_target_languages = array_intersect($target_language, $active_languages_slugs);

			require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/get-package-content.php';

			$automl_wpml_content_translation=array();

			foreach($post_ids as $post_id) {
				$source_lang = WPML_AT_Helper::get_post_source_language($post_id, get_post_type($post_id));
				$get_package_content = new Get_Package_Content($post_id, $source_lang);
				$translatable_strings = $get_package_content->get_translatable_strings();

				$automl_wpml_content_translation[$post_id] = array();

				if(isset($translatable_strings['contents']) && !empty($translatable_strings['contents'])) {
					$automl_wpml_content_translation[$post_id]['contents'] = $translatable_strings['contents'];
				}

				if(isset($translatable_strings['title']) && !empty($translatable_strings['title'])) {
					$automl_wpml_content_translation[$post_id]['title'] = $translatable_strings['title'];
				}

				$automl_wpml_post_element_type = apply_filters('wpml_element_type', get_post_type($post_id));

				// Get the translation group ID (trid) of the post
				$automl_wpml_trid = apply_filters('wpml_element_trid', null, $post_id);

				// Get all translations of the element using the trid and element type
				$automl_wpml_translations = apply_filters('wpml_get_element_translations', null, $automl_wpml_trid, $automl_wpml_post_element_type);

				$automl_wpml_post_translated_languages=array_column($automl_wpml_translations, 'language_code');

				$untranslated_languages=array_diff($target_language, $automl_wpml_post_translated_languages);

				if(count($untranslated_languages) > 0) {
					$automl_wpml_content_translation[$post_id]['languages'] = $untranslated_languages;
				}
			}

			wp_send_json_success($automl_wpml_content_translation);
		}

		public function create_translate_post( $params ) {
			$re_translate = $this->validate_retranslation( $params->get_params() );

			if ( ! isset( $params['source_language'] ) || empty( $params['source_language'] ) ) {
				wp_send_json_error( 'Invalid source language' );
			}
			if ( ! isset( $params['post_id'] ) || ! isset( $params['target_language'] ) || ( ! isset( $params['post_title'] ) && ! isset( $params['post_content'] ) && ! $re_translate ) ) {
				wp_send_json_error( 'Invalid request' );
			}
			if ( ! isset( $params['target_language'] ) && empty( $params['target_language'] ) ) {
				wp_send_json_error( 'Invalid target language' );
			}
			if ( ! wp_verify_nonce( $params['privateKey'], 'automl_wpml_create_translate_post_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			$params = $params->get_params();

			$post_id         = intval( sanitize_text_field( $params['post_id'] ) );
			$target_language = sanitize_text_field( $params['target_language'] );
			$editor_type     = sanitize_text_field( $params['editor_type'] );
			$source_language = sanitize_text_field( $params['source_language'] );

			$slug = isset( $params['post_name'] ) && ! empty( $params['post_name'] ) ? sanitize_text_field( $params['post_name'] ) : false;

			$excerpt = isset( $params['post_excerpt'] ) ? sanitize_text_field( $params['post_excerpt'] ) : '';

			$content = isset( $params['post_content'] ) ? $params['post_content'] : '';

			$meta_fields = isset( $params['post_meta_fields'] ) ? $params['post_meta_fields'] : '';

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			define( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION', true );
		}
	}
endif;
