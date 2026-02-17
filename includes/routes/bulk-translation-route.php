<?php

namespace AUTOML_WPML\Includes\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;
use AUTOML_WPML\Includes\Wpml\Get_Package_Content;
use AUTOML_WPML\Includes\Wpml\Create_Translated_Post;

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
				'/(?P<slug>[\w-]+)/pending-posts-ids',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'get_pending_posts_ids' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'privateKey' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_pending_posts_ids_request' ),
						),
						'ids' => array(
							'type'     => 'string',
							'required' => true,
						),
						'lang' => array(
							'type'     => 'string',
							'required' => true,
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

		public function validate_pending_posts_ids_request( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automl_wpml_pending_posts_ids_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'Invalid security token sent.', 'automl-ai-translation-for-wpml' ), array( 'status' => 403 ) );
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
            $models   = get_option( 'wpml_at_ai_translation_models', array() );
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
					->using_provider( $service_slug )
                    ->with_text( $content )
                    ->generate_text();
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

		public function get_pending_posts_ids( $params ) {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! wp_verify_nonce( $params['privateKey'], 'automl_wpml_pending_posts_ids_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! isset( $params['lang'] ) || empty( $params['lang'] ) ) {
				wp_send_json_error( 'Empty target language Select at least one language' );
			}
			
			if ( ! isset( $params['ids'] ) || empty( $params['ids'] ) ) {
				wp_send_json_error( 'Empty post IDs Select at least one post to translate' );
			}

			$post_ids        = json_decode( $params['ids'] );
			$post_ids        = array_map( 'absint', $post_ids );
			$target_language = json_decode( $params['lang'] );
			$target_language = array_map( 'sanitize_text_field', $target_language );

			$active_languages = apply_filters( 'wpml_active_languages', null, null );

			$active_languages_slugs = array_column( $active_languages, 'code' );
			$valid_target_languages = array_intersect( $target_language, $active_languages_slugs );

			$pending_posts_ids = array();

			foreach ( $post_ids as $post_id ) {
				$automl_wpml_post_element_type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) );
				$automl_wpml_trid = apply_filters( 'wpml_element_trid', null, $post_id);

				$automl_wpml_translations = apply_filters( 'wpml_get_element_translations', null, $automl_wpml_trid, $automl_wpml_post_element_type );
				
				$parent_post_set=false;

				foreach ( $automl_wpml_translations as $automl_wpml_translation ) {
					if ( $automl_wpml_translation->element_id && array_key_exists( $automl_wpml_translation->element_id, $pending_posts_ids ) ) {
						$parent_post_set = true;
						break;
					}
				}

				if ( ! $parent_post_set ) {
					$automl_wpml_post_translated_languages = array_column( $automl_wpml_translations, 'language_code' );

					$untranslated_languages = array_diff( $valid_target_languages, $automl_wpml_post_translated_languages );

					$pending_posts_ids[ $post_id ] = array('languages' => array_values($untranslated_languages), 'title' => get_the_title( $post_id ));
				}
			}

			wp_send_json_success( $pending_posts_ids );
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

			if ( ! isset( $params['lang'] ) || empty( $params['lang'] ) ) {
				wp_send_json_error( 'Empty target language Select at least one language' );
			}

			if ( ! isset( $params['ids'] ) || empty( $params['ids'] ) ) {
				wp_send_json_error( 'Empty post IDs Select at least one post to translate' );
			}

			$active_languages = apply_filters( 'wpml_active_languages', null, null );

			$active_languages_slugs = array_column( $active_languages, 'code' );

			$post_ids        = json_decode( $params['ids'] );
			$post_ids        = array_map( 'absint', $post_ids );
			$target_language = json_decode( $params['lang'] );
			$target_language = array_map( 'sanitize_text_field', $target_language );

			$valid_target_languages = array_intersect( $target_language, $active_languages_slugs );

			$automl_wpml_content_translation = array();

			if ( ! defined( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) ) {
				define( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION', true );
			}

			foreach ( $post_ids as $post_id ) {
				$post_data = get_post( $post_id );

				if ( ! $post_data ) {
					continue;
				}

				$source_lang          = WPML_AT_Helper::get_post_source_language( $post_id, get_post_type( $post_id ) );
				$get_package_content  = new Get_Package_Content( $post_id, $source_lang );
				$translatable_strings = $get_package_content->get_translatable_strings();

				if ( ! isset( $automl_wpml_content_translation['posts'] ) ) {
					$automl_wpml_content_translation['posts']                    = array();
					$automl_wpml_content_translation['CreateTranslatePostNonce'] = wp_create_nonce( 'automl_wpml_create_translate_post_nonce' );
				}

				$editor_type = has_blocks( $post_data->post_content ) ? 'block' : 'classic';

				$automl_wpml_content_translation['posts'][ $post_id ] = array(
					'sourceLanguage' => $source_lang,
					'title'          => $post_data->post_title,
					'post_link'      => html_entity_decode( get_edit_post_link( $post_id ) ),
				);

				$automl_wpml_content_translation['posts'][ $post_id ]['editor_type'] = $this->get_editor_type( $post_id, $editor_type );

				if ( isset( $translatable_strings['contents'] ) && ! empty( $translatable_strings['contents'] ) ) {
					$automl_wpml_content_translation['posts'][ $post_id ]['content'] = $translatable_strings['contents'];
				}

				if ( isset( $translatable_strings['title'] ) && ! empty( $translatable_strings['title'] ) ) {
					$automl_wpml_content_translation['posts'][ $post_id ]['title'] = $translatable_strings['title'];
				}

				$automl_wpml_post_element_type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) );

				// Get the translation group ID (trid) of the post
				$automl_wpml_trid = apply_filters( 'wpml_element_trid', null, $post_id );

				// Get all translations of the element using the trid and element type
				$automl_wpml_translations = apply_filters( 'wpml_get_element_translations', null, $automl_wpml_trid, $automl_wpml_post_element_type );

				$automl_wpml_post_translated_languages = array_column( $automl_wpml_translations, 'language_code' );

				$untranslated_languages = array_diff( $valid_target_languages, $automl_wpml_post_translated_languages );

				if ( count( $untranslated_languages ) > 0 ) {
					$automl_wpml_content_translation['posts'][ $post_id ]['languages'] = array_values($untranslated_languages);
				}
			}

			wp_send_json_success( $automl_wpml_content_translation );
		}

		public function create_translate_post( $params ) {

			if ( ! isset( $params['source_language'] ) || empty( $params['source_language'] ) ) {
				wp_send_json_error( 'Invalid source language' );
			}
			if ( ! isset( $params['post_id'] ) || ! isset( $params['target_language'] ) || ( ! isset( $params['post_title'] ) && ! isset( $params['post_content'] ) ) ) {
				wp_send_json_error( 'Invalid request' );
			}
			if ( ! isset( $params['target_language'] ) && empty( $params['target_language'] ) ) {
				wp_send_json_error( 'Invalid target language' );
			}
			if ( ! wp_verify_nonce( $params['privateKey'], 'automl_wpml_create_translate_post_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			$params = $params->get_params();

			$post_id = intval( sanitize_text_field( $params['post_id'] ) );

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! isset( $params['post_title'] ) || empty( $params['post_title'] ) && ! isset( $params['post_content'] ) || empty( $params['post_content'] ) ) {
				wp_send_json_error( 'No post title or post content found' );
			}

			if ( ! defined( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST' ) ) {
				define( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST', true );
			}

			$target_language = sanitize_text_field( $params['target_language'] );
			$editor_type     = sanitize_text_field( $params['editor_type'] );
			$source_language = sanitize_text_field( $params['source_language'] );
			$post_title      = isset( $params['post_title'] ) ? sanitize_text_field( $params['post_title'] ) : '';
			$post_excerpt    = isset( $params['post_excerpt'] ) ? wp_kses_post( $params['post_excerpt'] ) : '';
			$post_content    = isset( $params['post_content'] ) ? json_decode( $params['post_content'], true ) : '';
			$post_content = is_array($post_content) ? $post_content : array();

			$editor_type = isset( $editor_type ) && 'block' === $editor_type ? 'Gutenberg' : $editor_type;

			$create_translated_post = new Create_Translated_Post( $post_id, $post_content, $post_title, $post_excerpt, $source_language, $target_language, $editor_type );

			$translated_post_id = $create_translated_post->create_post();

			if ( is_wp_error( $translated_post_id ) ) {
				wp_send_json_error( $translated_post_id->get_error_message() );
			}

			$post_link      = html_entity_decode( get_the_permalink( $translated_post_id ) );
			$post_title     = html_entity_decode( get_the_title( $translated_post_id ) );
			$post_edit_link = html_entity_decode( get_edit_post_link( $translated_post_id ) );
				
			wp_send_json_success(
				array(
					'post_id'                     => $translated_post_id,
					'target_language'             => $target_language,
					'post_link'                   => $post_link,
					'post_title'                  => $post_title,
					'post_edit_link'              => $post_edit_link,
					'update_translate_data_nonce' => wp_create_nonce( 'automl_wpml_update_translate_data' ),
				)
			);
		}

		public function get_editor_type( int $post_id, $default = 'block' ): string {
			$editor = $default;

			if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && defined( 'ELEMENTOR_VERSION' ) ) {
				$editor = 'Elementor';
			} elseif ( 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ) && defined( 'ET_CORE' ) ) {
				$editor = 'Divi';
			}

			return $editor;
		}
	}
endif;
