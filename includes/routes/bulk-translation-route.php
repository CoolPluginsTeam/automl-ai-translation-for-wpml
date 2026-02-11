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
                        'source_language' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'action'          => array(
                            'type'     => 'string',
                            'required' => false,
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
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
        
            $params = $params->get_params();
        
            $service_slug = isset( $params['slug'] ) ? sanitize_key( $params['slug'] ) : '';
            if ( ! $service_slug ) {
                wp_send_json_error( 'Invalid service slug.' );
            }
        
            if ( ! wp_verify_nonce( $params['automl_wpml_nonce'] ?? '', 'automl_wpml_ai_translate_nonce' ) ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
        
            $strings_raw = $params['strings'] ?? '';
            $target_language = isset( $params['target_language'] ) ? sanitize_text_field( $params['target_language'] ) : '';
            $source_language = isset( $params['source_language'] ) ? sanitize_text_field( $params['source_language'] ) : 'en';
        
            if ( ! $target_language ) {
                wp_send_json_error( 'Invalid target language.' );
            }
        
            // Decode numeric-key => text map, e.g. {"0":"text","1":"text"}
            $strings = is_string( $strings_raw ) ? json_decode( $strings_raw, true ) : $strings_raw;
            if ( ! is_array( $strings ) ) {
                $strings = array();
            }
        
            // Get selected model for this provider from our option.
            $models   = get_option( \WPML_Auto_Translate_Addon::OPTION_TRANSLATION_MODELS, array() );
            $models = array('google' => 'gemini-2.5-flash');
            $model_id = isset( $models[ $service_slug ] ) ? $models[ $service_slug ] : '';
            if ( ! $model_id ) {
                wp_send_json_error( 'No AI model selected for this provider.' );
            }
        
            if ( ! class_exists( '\WordPress\AiClient\AiClient' ) || ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
                wp_send_json_error( 'AI SDK is not available.' );
            }
        
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if ( ! $registry->isProviderConfigured( $service_slug ) ) {
                wp_send_json_error( 'API key for this provider is not configured.' );
            }
        
            $provider_class = $registry->getProviderClassName( $service_slug );
        
            try {
                $model = $provider_class::model( $model_id );
            } catch ( \Throwable $e ) {
                wp_send_json_error( 'Invalid model: ' . $e->getMessage() );
            }
        
            // Build one prompt with JSON instructions (your existing $content template).
            $strings_for_prompt = json_encode( $strings );
            $content = sprintf(
                'Instruction 1: Translate visible text content semantically into %s language. Provide a proper meaning-based translation.
        Instruction 2: Do not translate or modify any content inside square brackets []. These are shortcodes or dynamic placeholders and must remain exactly as they are.
        Instruction 3: Preserve all HTML tags and their attributes such as class, id, data-*, etc. Do not alter any part of the HTML structure.
        Instruction 4: Return the translation in the format of a JSON object with the keys being numeric values (matching the source keys), and the values being the translated strings.
        Instruction 5: Do not escape double quotes with backslashes. Output must be valid JSON without extra slashes.
        Instruction 6: Translate the provided JSON array into %s language, regardless of whether the values are the same and Ensure the JSON is well-formed and complete.
        Instruction 7: Decode any &lt; and &gt; HTML entities back to < and > symbols in the output & preserve and maintain whitespace.
        Instruction 8: Return the output as a valid JSON object. Do not wrap the output in a string or markdown code block. Ensure the JSON is clean, parseable, and properly formatted. Please ensure that the output follows the format: {"key(numeric value)": "(translations of the strings in %s language)"} Strings are :- %s',
                $target_language,
                $target_language,
                $target_language,
                $strings_for_prompt
            );
        
           // $content is your long instruction + JSON string
            try {
                $builder = \WordPress\AI_Client\AI_Client::prompt();
                $raw     = $builder
                    ->using_model( $model )
                    ->with_text( $content )
                    ->generate_text();
            } catch ( \Throwable $e ) {
                wp_send_json_error( 'Error during text generation: ' . $e->getMessage() );
            } catch ( \Throwable $e ) {
                wp_send_json_error( 'Error during text generation: ' . $e->getMessage() );
            }
           	// Clean the text
						$cleanText = preg_replace( '/(^```json\n|```$)/', '', $raw );

						$cleanText = str_replace( '<ATFPP_NEW_L>', '\n', $cleanText );
						$cleanText = str_replace( '<ATFPP_NEW_R>', '\r', $cleanText );

						// Replace the double backslashes with a single backslash
						$final_text = preg_replace( '/\\\\{2,}([\'"n])/', '\\\$1', $cleanText );

						$translated_text = json_decode( $final_text, true );
            if ( ! is_array( $translated_text ) ) {
                wp_send_json_error( 'AI response is not valid JSON.' );
            }
        
            // Frontend expects: { success: true, data: { translate_data: { "0": "...", "1": "..." } } }
            wp_send_json_success(
                array(
                    'translate_data' => $translated_text,
                )
            );
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

			global $polylang;

			$slug_translation_option = get_option( 'automl_wpml_slug_translation_option', 'title_translate' );

			// check language exists or not
			$translate_lang = json_decode( $params['lang'] );

			$post_ids           = json_decode( $params['ids'] );
			$posts_translate    = array();

			require_once WPML_AT_PLUGIN_DIR . 'includes/wpml/get-package-content.php';

			foreach($post_ids as $post_id) {
				$source_lang = WPML_AT_Helper::get_post_source_language($post_id, get_post_type($post_id));
				$get_package_content = new Get_Package_Content($post_id, $source_lang);
				$translatable_strings = $get_package_content->get_translatable_strings();
			}
		}

		private function fetch_translation_data( $post_id, &$Object, $target_language, $slug_translation, $allowed_meta_fields, $post_meta_sync, $pll_langs_slugs, &$gutenberg_block = false,) {
			global $polylang;

			$postId    = intval( $post_id );
			$post_data = get_post( $postId );
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