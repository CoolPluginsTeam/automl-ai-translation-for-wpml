<?php
/**
 * AJAX handlers for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler class.
 */
class CP_WPML_Google_Auto_Translate_Ajax {

	/**
	 * Nonce key for AJAX requests.
	 *
	 * @var string
	 */
	const NONCE = 'cp_wpml_auto_translate_nonce';

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'wp_ajax_cp_wpml_google_auto_translate_get_post_contents',
			array( __CLASS__, 'get_post_contents' )
		);

		add_action(
			'wp_ajax_cp_wpml_google_auto_translate_save_translation',
			array( __CLASS__, 'save_translation' )
		);

		add_action(
			'wp_ajax_cp_wpml_google_auto_translate_get_pending_languages',
			array( __CLASS__, 'get_pending_languages' )
		);
	}

	/**
	 * Detect the editor type for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Editor type: 'elementor', 'gutenberg', or 'classic'.
	 */
	public static function detect_editor( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 'classic';
		}

		// Elementor.
		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			return 'elementor';
		}

		// Gutenberg.
		$content = get_post_field( 'post_content', $post_id );
		if ( $content && has_blocks( $content ) ) {
			return 'gutenberg';
		}

		return 'classic';
	}

	/**
	 * Get post contents for translation.
	 * Used by openTranslationTablePopup.
	 *
	 * @return void
	 */
	public static function get_post_contents() {
		check_ajax_referer( self::NONCE, 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Insufficient permissions.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		// Sanitize and validate input.
		$ids         = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'No post IDs provided.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

        $response = [];

        foreach ( $ids as $post_id ) {

            $post_id = absint( $post_id );
            $post    = get_post( $post_id );

            if ( ! $post ) {
                continue;
            }

            /* ---------------------------------
             * Detect editor
             * --------------------------------- */
            $editor = self::detect_editor( $post_id );

            if ( $editor === 'elementor' ) {
                $extracted = WPML_Engine::extract_elementor( $post_id );
            } else if ( $editor === 'gutenberg' ) {
                $extracted = WPML_Engine::extract_gutenberg( $post->post_content );
                
                // Add title as translatable string (matches JavaScript GutenbergBlockSaveSource logic)
                if ( ! empty( $post->post_title ) && trim( $post->post_title ) !== '' ) {
                    array_unshift( $extracted['rows'], [
                        'field_key' => 'title',
                        'original'  => $post->post_title,
                        'translate' => 1,
                    ] );
                }
            } else {
                // Classic Editor - extract text while preserving HTML structure
                $extracted = [
                    'editor'  => 'classic',
                    'payload' => $post->post_content,
                    'rows'    => []
                ];
                
                // Extract translatable text segments while preserving HTML structure
                if ( ! empty( $post->post_content ) ) {
                    $content = $post->post_content;
                    
                    // Use DOMDocument to extract only text content from HTML elements
                    libxml_use_internal_errors( true );
                    $dom = new DOMDocument();
                    $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
                    libxml_clear_errors();
                    
                    $xpath = new DOMXPath( $dom );
                    // Extract text from common content elements, excluding scripts, styles
                    $text_nodes = $xpath->query( '//p//text() | //h1//text() | //h2//text() | //h3//text() | //h4//text() | //h5//text() | //h6//text() | //li//text() | //td//text() | //th//text() | //div//text()[normalize-space()]' );
                    
                    $text_segments = [];
                    if ( $text_nodes && $text_nodes->length > 0 ) {
                        foreach ( $text_nodes as $index => $node ) {
                            $text = trim( $node->nodeValue );
                            // Only include text segments with actual content (not just whitespace)
                            if ( ! empty( $text ) && strlen( $text ) > 2 ) {
                                $text_segments[] = [
                                    'index' => $index,
                                    'text' => $text
                                ];
                                
                                $extracted['rows'][] = [
                                    'field_key' => 'text_segment_' . $index,
                                    'original'  => $text,
                                    'translate' => 1,
                                ];
                            }
                        }
                    }
                    
                    // Store the text segments in payload for reconstruction
                    $extracted['payload'] = [
                        'original_content' => $post->post_content,
                        'segments' => $text_segments
                    ];
                    
                    // Fallback: If no text extracted, use simple text extraction
                    if ( empty( $extracted['rows'] ) ) {
                        $text_only = wp_strip_all_tags( $content );
                        if ( ! empty( trim( $text_only ) ) ) {
                            $extracted['rows'][] = [
                                'field_key' => 'text_content',
                                'original'  => $text_only,
                                'translate' => 1,
                            ];
                            $extracted['payload'] = [
                                'original_content' => $post->post_content,
                                'text_only' => true
                            ];
                        }
                    }
                }
            }

            /* ---------------------------------
             * Format strings for JS table
             * --------------------------------- */
            $strings = [];

            foreach ( $extracted['rows'] as $row ) {
                $strings[] = [
                    'text'       => $row['original'],
                    'html'       => $row['original'],
                    'field_key'  => $row['field_key'],
                    'field_name' => $row['field_key'],
                    'format'     => 'html',
                ];
            }

            $response[ $post_id ] = [
                'editor_type'     => $editor,
                'title'           => $post->post_title,
                'strings'         => $strings,
                'original_content'=> $extracted['payload'], // IMPORTANT
                'package'         => null, // JS expects it but doesn’t require it
            ];
        }

        wp_send_json_success( $response );
    }

	/**
	 * Get pending languages (languages without existing translations) for selected posts.
	 *
	 * @return void
	 */
	public static function get_pending_languages() {
		// Check nonce but don't die on failure - return JSON error instead.
		$nonce_check = check_ajax_referer( self::NONCE, 'nonce', false );
		if ( ! $nonce_check ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Insufficient permissions.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		// Sanitize and validate input.
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'msg' => 'No IDs provided' ) );
		}

		$all_languages = WPML_AT_Helper::get_wpml_languages();
		// Get source language from first post.
		$source_lang = 'en';
		if ( ! empty( $ids ) ) {
			$first_post = get_post( $ids[0] );
			if ( $first_post ) {
				$source_lang = WPML_AT_Helper::get_post_source_language( $ids[0], $first_post->post_type );
			}
		}
		// Get languages that already have translations for all selected posts.
		$languages_with_translations = array();
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$translations = WPML_AT_Helper::get_post_translations( $post_id, $post->post_type );

			if ( ! empty( $translations ) && is_array( $translations ) ) {
				foreach ( $translations as $lang_code => $translation ) {
					$info = WPML_AT_Helper::extract_translation_info( $translation );

					if ( $info['code'] && $info['code'] !== $source_lang && $info['element_id'] ) {
						if ( ! isset( $languages_with_translations[ $info['code'] ] ) ) {
							$languages_with_translations[ $info['code'] ] = 0;
						}
						$languages_with_translations[ $info['code'] ]++;
					}
				}
			}
		}

		// Filter out languages that have translations for ALL selected posts.
		$pending_languages = array();
		$total_posts       = count( $ids );
		foreach ( $all_languages as $lang ) {
			// Skip source language.
			if ( $lang['code'] === $source_lang ) {
				continue;
			}

			// Only include if translation doesn't exist for all posts.
			if ( ! isset( $languages_with_translations[ $lang['code'] ] ) ||
				$languages_with_translations[ $lang['code'] ] < $total_posts ) {
				$pending_languages[] = $lang;
			}
		}

		wp_send_json_success(
			array(
				'languages'   => $pending_languages,
				'source_lang' => $source_lang,
			)
		);
	}

    /**
     * ======================================================
     * SAVE TRANSLATED CONTENT (AUTOPOLY-COMPATIBLE)
     * ======================================================
     */
    
	/**
	 * Save translated content.
	 *
	 * @return void
	 */
	public static function save_translation() {
		check_ajax_referer( self::NONCE, 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Insufficient permissions.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		// Sanitize and validate input.
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array data is sanitized individually when processed (field_key sanitized, translated sanitized with wp_kses_post).
		$strings     = isset( $_POST['translated_strings'] ) ? (array) $_POST['translated_strings'] : array();

		if ( ! $post_id || ! $target_lang || empty( $strings ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Missing required data.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Invalid post.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}

		// Check if user can edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'You do not have permission to edit this post.', 'automl-ai-translation-for-wpml' ) ) );
			return;
		}
    
        /* ----------------------------------
         * 1. Detect editor correctly
         * ---------------------------------- */
        $is_elementor = get_post_meta( $post_id, '_elementor_data', true );
        $is_blocks    = has_blocks( $post->post_content );
    
		/* ----------------------------------
		 * 1.5. Extract translated title from strings
		 * ---------------------------------- */
		$translated_title = $post->post_title; // Default to original.
		foreach ( $strings as $row ) {
			if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' && ! empty( $row['translated'] ) ) {
				$translated_title = sanitize_text_field( wp_strip_all_tags( $row['translated'] ) );
				break;
			}
		}
    
		/* ----------------------------------
		 * 2. CREATE/UPDATE translated post (WPML-safe)
		 * ---------------------------------- */
		// Check if translation already exists.
		$translated_post_id = WPML_AT_Helper::get_existing_translation_id( $post_id, $post->post_type, $target_lang );

		if ( $translated_post_id ) {
			// Update existing translation.
			$update_result = wp_update_post(
				array(
					'ID'         => $translated_post_id,
					'post_title' => $translated_title,
				),
				true
			);

			if ( is_wp_error( $update_result ) ) {
				wp_send_json_error( array( 'msg' => esc_html__( 'Failed to update translation post.', 'automl-ai-translation-for-wpml' ) ) );
				return;
			}
		} else {
			// Create new translation.
			$translated_post_id = wp_insert_post(
				array(
					'post_type'   => $post->post_type,
					'post_status' => 'draft',
					'post_title'  => $translated_title,
					'post_author' => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $translated_post_id ) ) {
				wp_send_json_error( array( 'msg' => esc_html__( 'Post creation failed.', 'automl-ai-translation-for-wpml' ) ) );
				return;
			}

			// Link with WPML (CORRECT WAY).
			$source_lang_details = apply_filters( 'wpml_post_language_details', null, $post_id );
			$source_lang_code    = isset( $source_lang_details['language_code'] ) ? $source_lang_details['language_code'] : null;

			do_action(
				'wpml_set_element_language_details',
				array(
					'element_id'           => $translated_post_id,
					'element_type'         => 'post_' . $post->post_type,
					'trid'                 => apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $post->post_type ),
					'language_code'        => $target_lang,
					'source_language_code' => $source_lang_code,
				)
			);
		}
    
		/* ----------------------------------
		 * 3. ELEMENTOR (AutoPoly way)
		 * ---------------------------------- */
		if ( $is_elementor ) {
			// Decode Elementor data - handle both string and already decoded formats.
			if ( is_string( $is_elementor ) ) {
				$data = json_decode( $is_elementor, true );
			} else {
				$data = $is_elementor;
			}

			if ( ! is_array( $data ) ) {
				wp_send_json_error( array( 'msg' => esc_html__( 'Invalid Elementor data.', 'automl-ai-translation-for-wpml' ) ) );
				return;
			}
            
            $replacement_count = 0;
            foreach ( $strings as $row ) {
                // Skip title field - already handled above
                if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' ) {
                    continue;
                }

                if ( empty( $row['field_key'] ) || empty( $row['translated'] ) ) {
                    continue;
                }

                // Extract final key name - field keys use dots (e.g., "0.settings.title" or "0.elements.0.settings.text")
                $parts = preg_split( '/[.:|]/', $row['field_key'], -1, PREG_SPLIT_NO_EMPTY );
                if ( empty( $parts ) ) {
                    continue;
                }
                $final_key = end( $parts );
            
                if ( ! self::is_translatable_elementor_key( $final_key ) ) {
                    continue;
                }

                if ( self::is_forbidden_elementor_key( $final_key ) ) {
                    continue;
                }
            
                // Decode HTML entities from Google Translate response
                $decoded_translated = html_entity_decode( $row['translated'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                
                // Try to replace the value at the path
                $replaced = self::replace_by_path(
                    $data,
                    $row['field_key'],
                    wp_kses_post( $decoded_translated )
                );
                
                if ( $replaced ) {
                    $replacement_count++;
                }
            }
    
            // Encode back to JSON format
            $encoded_data = wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
            
            update_post_meta(
                $translated_post_id,
                '_elementor_data',
                $encoded_data
            );
    
            update_post_meta( $translated_post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $translated_post_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $translated_post_id, '_elementor_version', get_post_meta( $post_id, '_elementor_version', true ) );
    
			wp_send_json_success(
				array(
					'msg'     => esc_html__( 'Elementor translation saved successfully.', 'automl-ai-translation-for-wpml' ),
					'post_id' => $translated_post_id,
					'debug'   => array(
						'replacements'   => $replacement_count,
						'total_strings' => count( $strings ),
					),
				)
			);
			return;
		}
    
		/* ----------------------------------
		 * 4. GUTENBERG / UAGB / BLOCKS
		 * ---------------------------------- */
		if ( $is_blocks ) {
			// IMPORTANT: Always use ORIGINAL post content as base for applying translations
			// because field_keys (e.g., "b:0|attrs.content") are extracted from the original structure
			// Using translated post content would cause path mismatches.
			$base_post_content = $post->post_content ?? '';

			if ( empty( $base_post_content ) ) {
				wp_send_json_error(
					array(
						'msg' => esc_html__( 'Original post content is empty.', 'automl-ai-translation-for-wpml' ),
					)
				);
				return;
			}
            
            // Parse blocks from ORIGINAL post content (field_keys match this structure)
            $original_blocks = parse_blocks( $base_post_content );
            $replacement_count = 0;
    
            // Build a map of translations by field_key for efficient lookup
            $translation_map = [];
            foreach ( $strings as $row ) {
                // Skip title field - already handled above
                if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' ) {
                    continue;
                }
                
                if ( ! empty( $row['field_key'] ) && ! empty( $row['translated'] ) ) {
                    // Decode HTML entities from Google Translate response
                    $decoded_translated = html_entity_decode( $row['translated'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                    $translation_map[ $row['field_key'] ] = [
                        'translated' => $decoded_translated,
                        'original' => isset( $row['original'] ) ? $row['original'] : ''
                    ];
                }
            }
    
            // Use apply_block_translations to replace content in existing blocks
            // This preserves all block structure and content, only replacing translated fields
            $translated_blocks = $original_blocks;
            self::apply_block_translations( $translated_blocks, $translation_map, $replacement_count, 'b' );
            
            // Serialize the translated blocks
            $translated_content = serialize_blocks( $translated_blocks );
            
            // Update the translated post content directly
            wp_update_post([
                'ID' => $translated_post_id,
                'post_content' => $translated_content
            ]);
    
			wp_send_json_success(
				array(
					'msg'                => esc_html__( 'Gutenberg blocks translated and saved.', 'automl-ai-translation-for-wpml' ),
					'post_id'            => $translated_post_id,
					'translated_post_id' => $translated_post_id,
					'is_blocks'          => true,
				)
			);
			return;
		}
    
        /* ----------------------------------
         * 5. CLASSIC EDITOR 
         * ---------------------------------- */
        // Get the original content to preserve HTML structure
        $content = $post->post_content;
        $replacement_count = 0;
        
        // Build translation map
        $translations = [];
        foreach ( $strings as $row ) {
            // Skip title field - already handled above
            if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' ) {
                continue;
            }
            
            if ( ! empty( $row['original'] ) && ! empty( $row['translated'] ) ) {
                $translations[] = [
                    'original' => $row['original'],
                    'translated' => $row['translated'],
                    'field_key' => $row['field_key']
                ];
            }
        }
        
        // Replace text segments in the HTML while preserving structure
        foreach ( $translations as $item ) {
            $original_text = $item['original'];
            $translated_text = $item['translated'];
            
            // Clean up any Google Translate artifacts from translated text
            $translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            
            // Try exact text replacement
            $search_count = 0;
            $content = preg_replace(
                '/' . preg_quote( $original_text, '/' ) . '/u',
                $translated_text,
                $content,
                1, // Only replace first occurrence
                $search_count
            );
            
            if ( $search_count > 0 ) {
                $replacement_count++;
            } else {
            }
        }
        
        // Log replacement results
        
        // If no replacements were made, log warning but still save
        if ( $replacement_count === 0 && count( $translations ) > 0 ) {
        }
        
        // Ensure we have valid content
        if ( empty( trim( $content ) ) ) {
            $content = $post->post_content;
        }
    
        // Update the post with translated content
        $update_result = wp_update_post([
            'ID'           => $translated_post_id,
            'post_content' => $content
        ], true );
        
        if ( is_wp_error( $update_result ) ) {
            wp_send_json_error([
                'msg' => 'Failed to update post content: ' . $update_result->get_error_message()
            ]);
        }
    
		wp_send_json_success(
			array(
				'msg'     => esc_html__( 'Classic editor translation saved successfully.', 'automl-ai-translation-for-wpml' ),
				'post_id' => $translated_post_id,
				'debug'   => array(
					'replacements'    => $replacement_count,
					'total_segments' => count( $translations ),
					'content_length' => strlen( $content ),
					'original_length' => strlen( $post->post_content ),
				),
			)
		);
	}

	/**
	 * Check if an Elementor key should be translated.
	 *
	 * @param string $key Key name.
	 * @return bool True if translatable.
	 */
	private static function is_translatable_elementor_key( string $key ): bool {
		// Use the same function as extraction to ensure consistency.
		return WPML_Engine::should_translate_key( $key );
	}

	/**
	 * Check if an Elementor key is a CSS property (forbidden for translation).
	 *
	 * @param string $key Key name.
	 * @return bool True if CSS property.
	 */
	private static function is_forbidden_elementor_key( string $key ): bool {
		// Use the same function as extraction to ensure consistency.
		return WPML_Engine::is_css_property( $key );
	}
    /**
     * Apply translations to blocks recursively (similar to Polylang's translate_blocks approach)
     * This method properly preserves block structure including innerContent arrays
     * 
     * @param array &$blocks Array of blocks to translate
     * @param array $translation_map Map of field_key => translated content
     * @param int &$replacement_count Counter for replacements made
     * @param string $block_path_prefix Current block path prefix (e.g., "b:0" or "b:0.ib:1")
     */
    private static function apply_block_translations( array &$blocks, array $translation_map, int &$replacement_count, string $block_path_prefix = 'b' ) {
        foreach ( $blocks as $k => &$block ) {
            // Skip null or empty blocks
            if ( ! is_array( $block ) || empty( $block ) ) {
                continue;
            }
            
            // Build current block path (e.g., "b:0" or "b:0.ib:1")
            $current_block_path = $block_path_prefix === 'b' ? "b:{$k}" : "{$block_path_prefix}.ib:{$k}";
            
            // Process each translation - only apply if field_key matches this block path
            foreach ( $translation_map as $field_key => $translation_data ) {
                // Check if this field_key belongs to the current block path
                // Must match exactly: field_key should start with current_block_path followed by '|'
                // This handles both direct matches (b:0|innerHTML) and nested matches (b:0.ib:0|innerHTML)
                $path_with_pipe = $current_block_path . '|';
                if ( strpos( $field_key, $path_with_pipe ) === 0 ) {
                    $translated_value = $translation_data['translated'];
                    $original_value = $translation_data['original'];
                    
                    // Apply translation using the field_key path
                    if ( self::replace_block_text( $block, $field_key, $translated_value, $original_value ) ) {
                        $replacement_count++;
                    } else {
                    }
                }
            }
            
            // Recursively process innerBlocks (same as Polylang plugin)
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                self::apply_block_translations( $block['innerBlocks'], $translation_map, $replacement_count, $current_block_path );
            }
        }
    }
    
    /**
     * Replace text in a single block based on field_key path
     * Properly handles innerHTML and innerContent synchronization
     * Preserves HTML structure when original had HTML but translated doesn't
     * 
     * @param array &$block Block to modify
     * @param string $path Field key path (e.g., "b:0|innerHTML" or "b:0|attrs.content")
     * @param string $value Translated value
     * @param string $original Original value (for matching)
     * @return bool True if replacement was made
     */
    private static function replace_block_text( array &$block, string $path, string $value, string $original = '' ) {
        // Parse the path (e.g., "b:0|innerHTML" or "b:0|attrs.content" or "b:0|attrs.tabs:0.title")
        // Path format: [block_path]|[attrs_path]
        // Where attrs_path can be: "innerHTML" or "attrs.content" or "attrs.tabs:0.title"
        
        // Split on pipe to separate block path from attribute path
        $path_parts = explode( '|', $path, 2 );
        
        if ( count( $path_parts ) !== 2 ) {
            return false; // Invalid path format
        }
        
        $attr_path = $path_parts[1];  // e.g., "innerHTML" or "innerContent:0" or "attrs.content" or "attrs.tabs:0.title"
        
        // $block is already the target block (passed by reference from apply_block_translations)
        $ref = &$block;
        
        // Handle innerContent:index entries (Polylang approach - extract each innerContent entry separately)
        if ( strpos( $attr_path, 'innerContent:' ) === 0 ) {
            $index = (int) substr( $attr_path, 14 ); // Extract index after "innerContent:"
            
            // CRITICAL: Preserve original innerContent structure exactly
            // Don't modify array length or structure - only update the specific entry
            if ( ! isset( $ref['innerContent'] ) || ! is_array( $ref['innerContent'] ) ) {
                // If innerContent doesn't exist, create it but preserve structure
                $ref['innerContent'] = [];
            }
            
            // Preserve original array structure - only extend if necessary, don't shrink
            $original_length = count( $ref['innerContent'] );
            if ( $index >= $original_length ) {
                // Extend array to include this index (preserve null placeholders)
                for ( $i = $original_length; $i <= $index; $i++ ) {
                    $ref['innerContent'][ $i ] = null;
                }
            }
            
            // Get original innerContent value for syncing
            $original_inner_content = isset( $ref['innerContent'][ $index ] ) ? $ref['innerContent'][ $index ] : '';
            $original_text = wp_strip_all_tags( $original_inner_content );
            $translated_text = wp_strip_all_tags( $value );
            
            // CRITICAL: Also sync block attributes when innerContent is updated
            // This prevents block validation errors (blocks like UAGB buttons store text in both attrs and innerContent)
            // Matches Polylang's approach: when innerContent is updated, attributes should also be updated
            if ( ! empty( trim( $original_text ) ) && ! empty( trim( $translated_text ) ) && isset( $ref['attrs'] ) && is_array( $ref['attrs'] ) ) {
                self::sync_attributes_with_inner_content( $ref['attrs'], trim( $original_text ), trim( $translated_text ) );
            }
            
            // Replace ONLY the specific innerContent entry (preserve null placeholders)
            // $value already contains the translated HTML, so use it directly
            $ref['innerContent'][ $index ] = $value;
            
            return true;
        }
        
        // Handle innerHTML - Fallback for backwards compatibility
        // Note: We now extract innerContent entries separately, so this is rarely used
        if ( $attr_path === 'innerHTML' ) {
            if ( ! isset( $ref['innerHTML'] ) ) {
                return false;
            }
            
            $original_inner_content = isset( $ref['innerContent'] ) && is_array( $ref['innerContent'] ) ? $ref['innerContent'] : null;
            
            // Update innerContent entries
            if ( $original_inner_content !== null && count( $original_inner_content ) > 0 ) {
                // Preserve the exact structure (including null placeholders for inner blocks)
                $ref['innerContent'] = $original_inner_content;
                
                // Find and replace string entries in innerContent
                $string_indices = [];
                foreach ( $ref['innerContent'] as $idx => $content ) {
                    if ( is_string( $content ) && trim( $content ) !== '' ) {
                        $string_indices[] = $idx;
                    }
                }
                
                if ( ! empty( $string_indices ) ) {
                    // Replace the first string entry with translated value
                    $ref['innerContent'][ $string_indices[0] ] = $value;
                } else {
                    // No string entries found - create new structure
                    $ref['innerContent'] = [ $value ];
                }
            } else {
                // If innerContent doesn't exist, create it from innerHTML (WordPress convention)
                $ref['innerContent'] = [ $value ];
            }
            
            return true;
        }
        
        // Handle attribute paths (attrs.content, attrs.tabs:0.title, etc.)
        if ( strpos( $attr_path, 'attrs.' ) === 0 ) {
            $attr_path_without_prefix = substr( $attr_path, 6 ); // Remove "attrs."
            
            // Check if this is a table cell content
            // Path format: body:0.cells:0.content, head:0.cells:0.content, or foot:0.cells:0.content
            // For table cells, escape HTML tags so they display as literal text instead of being rendered
            if ( preg_match( '/^(body|head|foot):\d+\.cells:\d+\.content$/', $attr_path_without_prefix ) ) {
                $value = esc_html( $value );
            }
            
            $attr_path = $attr_path_without_prefix;
        }
        
        // Ensure attrs array exists
        if ( ! isset( $ref['attrs'] ) || ! is_array( $ref['attrs'] ) ) {
            $ref['attrs'] = [];
        }
        
        $attr_ref = &$ref['attrs'];
        
        // Simple attribute (e.g., "content" without nesting)
        if ( strpos( $attr_path, ':') === false && strpos( $attr_path, '.' ) === false ) {            
            // Store original value before updating
            $original_attr_value = isset( $attr_ref[ $attr_path ] ) ? $attr_ref[ $attr_path ] : '';
            $original_text = wp_strip_all_tags( $original_attr_value );
            $translated_text = wp_strip_all_tags( $value );
            
            // If original attribute value contains HTML, preserve HTML structure when updating
            // This handles HTML-type attributes (like label with type: "html")
            if ( preg_match( '/<[^>]+>/', $original_attr_value ) && ! preg_match( '/<[^>]+>/', $value ) ) {
                // Original has HTML but translated value is plain text - replace text within HTML
                $new_value = self::replace_text_outside_html_tags( $original_attr_value, trim( $original_text ), trim( $translated_text ) );
                $attr_ref[ $attr_path ] = $new_value;
            } else {
                // No HTML in original, or translated value already has HTML - use translated value directly
                $attr_ref[ $attr_path ] = $value;
            }
            
            
            // CRITICAL: Also sync innerContent when attributes are updated
            // This prevents block validation errors (blocks like UAGB buttons store text in both attrs and innerContent)
            // Matches Polylang's approach: when attributes are updated, innerContent should also be updated
            if ( ! empty( trim( $original_text ) ) && ! empty( trim( $translated_text ) ) ) {
                self::sync_inner_content_with_attributes( $ref, trim( $original_text ), trim( $translated_text ) );
            }
            
            return true;
        }
        
        // Parse attribute path, handling array indices (e.g., "tabs:0.title")
        // Use regex to split by '.' but preserve "key:index" patterns
        preg_match_all( '/(\w+)(?::(\d+))?/', $attr_path, $matches, PREG_SET_ORDER );
        
        $attr_parts = [];
        foreach ( $matches as $match ) {
            $key = $match[1];
            $index = isset( $match[2] ) && $match[2] !== '' ? (int) $match[2] : null;
            
            if ( $index !== null ) {
                $attr_parts[] = [ 'key' => $key, 'index' => $index ];
            } else {
                $attr_parts[] = [ 'key' => $key, 'index' => null ];
            }
        }
        
        if ( empty( $attr_parts ) ) {
            return false;
        }
        
        // Navigate through attribute path
        $parts_count = count( $attr_parts );
        for ( $i = 0; $i < $parts_count - 1; $i++ ) {
            $part = $attr_parts[ $i ];
            $key = $part['key'];
            $index = $part['index'];
            
            // Navigate to the key first
            if ( ! isset( $attr_ref[ $key ] ) ) {
                // Key doesn't exist - create it as array if we have an index
                if ( $index !== null ) {
                    $attr_ref[ $key ] = [];
                } else {
                    return false; // Can't create non-array key
                }
            }
            
            $attr_ref = &$attr_ref[ $key ];
            
            // If there's an array index, navigate into it
            if ( $index !== null ) {
                if ( ! is_array( $attr_ref ) ) {
                    return false; // Can't use array index on non-array
                }
                
                if ( ! isset( $attr_ref[ $index ] ) ) {
                    return false; // Array index doesn't exist
                }
                
                $attr_ref = &$attr_ref[ $index ];
            }
        }
        
        // Set the final value
        $final_part = $attr_parts[ $parts_count - 1 ];
        $final_key = $final_part['key'];
        $final_index = $final_part['index'];
        
        if ( $final_index !== null ) {
            // Final part is an array index (e.g., "tabs:0" as final)
            if ( ! isset( $attr_ref[ $final_key ] ) || ! is_array( $attr_ref[ $final_key ] ) ) {
                return false;
            }
            if ( isset( $attr_ref[ $final_key ][ $final_index ] ) ) {
                // Store original value before updating
                $original_attr_value = $attr_ref[ $final_key ][ $final_index ];
                $original_text = wp_strip_all_tags( $original_attr_value );
                $translated_text = wp_strip_all_tags( $value );
                
                // If original attribute value contains HTML, preserve HTML structure when updating
                // This handles HTML-type attributes (like label with type: "html")
                if ( preg_match( '/<[^>]+>/', $original_attr_value ) && ! preg_match( '/<[^>]+>/', $value ) ) {
                    // Original has HTML but translated value is plain text - replace text within HTML
                    $attr_ref[ $final_key ][ $final_index ] = self::replace_text_outside_html_tags( $original_attr_value, trim( $original_text ), trim( $translated_text ) );
                } else {
                    // No HTML in original, or translated value already has HTML - use translated value directly
                    $attr_ref[ $final_key ][ $final_index ] = $value;
                }
                
                // CRITICAL: Also sync innerContent when attributes are updated
                if ( ! empty( trim( $original_text ) ) && ! empty( trim( $translated_text ) ) ) {
                    self::sync_inner_content_with_attributes( $ref, trim( $original_text ), trim( $translated_text ) );
                }
                
                return true;
            }
            return false;
        } else {
            // Regular key - set the value
            // Store original value before updating
            $original_attr_value = isset( $attr_ref[ $final_key ] ) ? $attr_ref[ $final_key ] : '';
            $original_text = wp_strip_all_tags( $original_attr_value );
            $translated_text = wp_strip_all_tags( $value );
            
            // If original attribute value contains HTML, preserve HTML structure when updating
            // This handles HTML-type attributes (like label with type: "html")
            if ( preg_match( '/<[^>]+>/', $original_attr_value ) && ! preg_match( '/<[^>]+>/', $value ) ) {
                // Original has HTML but translated value is plain text - replace text within HTML
                $attr_ref[ $final_key ] = self::replace_text_outside_html_tags( $original_attr_value, trim( $original_text ), trim( $translated_text ) );
            } else {
                // No HTML in original, or translated value already has HTML - use translated value directly
                $attr_ref[ $final_key ] = $value;
            }
            
            // CRITICAL: Also sync innerContent when attributes are updated
            // This prevents block validation errors (blocks like UAGB buttons store text in both attrs and innerContent)
            // Matches Polylang's approach: when attributes are updated, innerContent should also be updated
            if ( ! empty( trim( $original_text ) ) && ! empty( trim( $translated_text ) ) ) {
                self::sync_inner_content_with_attributes( $ref, trim( $original_text ), trim( $translated_text ) );
            }
            
            return true;
        }
    }

    private static function replace_by_path( array &$data, string $path, string $value ) {
        // Parse the path for Elementor (e.g., "0.settings.title" or "0.elements.0.settings.text")
        $keys = preg_split( '/[.:|]/', $path, -1, PREG_SPLIT_NO_EMPTY );
        
        // Remove the 'e' prefix if present (it's just a marker, not an actual array key)
        if ( ! empty( $keys ) && $keys[0] === 'e' ) {
            array_shift( $keys );
        }
        
        if ( empty( $keys ) ) {
            return false;
        }
        
        $ref = &$data;
    
        foreach ( $keys as $key ) {
            // Convert numeric strings to integers for proper array access
            if ( is_numeric( $key ) ) {
                $key = (int) $key;
            }
            
            if ( ! isset( $ref[ $key ] ) ) {
                return false;
            }
            $ref = &$ref[ $key ];
        }
    
        $ref = $value;
        return true;
    }
    
    /**
     * Sync innerContent with block attributes when attributes are updated
     * This is the reverse of sync_attributes_with_inner_content
     * Prevents block validation errors by keeping innerContent in sync with attributes
     * Matches Polylang's approach: when attributes are updated, innerContent should also be updated
     * 
     * @param array &$block Block to update
     * @param string $original_text Original plain text (from attribute)
     * @param string $translated_text Translated text
     * @return void
     */
    private static function sync_inner_content_with_attributes( array &$block, string $original_text, string $translated_text ) {
        if ( empty( $original_text ) || empty( $translated_text ) ) {
            return;
        }
        
        // Normalize text for comparison
        $original_text = trim( $original_text );
        $translated_text = trim( $translated_text );
        
        // Update innerHTML if it exists and contains the original text
        // Use partial matching since innerHTML may contain multiple attributes concatenated
        if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
            $inner_html_text = wp_strip_all_tags( $block['innerHTML'] );
            $inner_html_text_trimmed = trim( $inner_html_text );
            
            // Check if innerHTML contains the original text (partial match, not exact)
            if ( strpos( $inner_html_text_trimmed, $original_text ) !== false ) {
                // Replace text within HTML using PHP-compatible method
                if ( preg_match( '/<[^>]+>/', $block['innerHTML'] ) ) {
                    $old_inner_html = $block['innerHTML'];
                    $block['innerHTML'] = self::replace_text_outside_html_tags( $block['innerHTML'], $original_text, $translated_text );
                } else {
                    // No HTML tags, use simple string replacement
                    $old_inner_html = $block['innerHTML'];
                    $block['innerHTML'] = str_replace( $original_text, $translated_text, $block['innerHTML'] );
                }
            }
        }
        
        // Update innerContent array entries
        // Use partial matching since innerContent may contain multiple attributes concatenated
        if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
            foreach ( $block['innerContent'] as $idx => &$content ) {
                if ( is_string( $content ) && trim( $content ) !== '' ) {
                    $content_text = wp_strip_all_tags( $content );
                    $content_text_trimmed = trim( $content_text );
                    
                    // Check if innerContent contains the original text (partial match, not exact)
                    if ( strpos( $content_text_trimmed, $original_text ) !== false ) {
                        // Replace text within HTML using PHP-compatible method
                        if ( preg_match( '/<[^>]+>/', $content ) ) {
                            $old_content = $content;
                            $content = self::replace_text_outside_html_tags( $content, $original_text, $translated_text );
                        } else {
                            // No HTML tags, use simple string replacement
                            $old_content = $content;
                            $content = str_replace( $original_text, $translated_text, $content );
                        }
                    } else {
                    }
                }
            }
        }
    }
    
    /**
     * Replace text outside HTML tags (PHP-compatible version of Polylang's regex)
     * PHP doesn't support variable-length lookbehind, so we use a simpler regex approach
     * 
     * @param string $html HTML content with text to replace
     * @param string $original_text Original text to find
     * @param string $replacement_text Replacement text
     * @return string HTML with text replaced outside tags
     */
    private static function replace_text_outside_html_tags( string $html, string $original_text, string $replacement_text ): string {
        if ( empty( trim( $original_text ) ) || empty( trim( $replacement_text ) ) ) {
            return $html;
        }
        
        // Normalize whitespace for comparison
        $original_text = trim( $original_text );
        $replacement_text = trim( $replacement_text );
        
        // Escape special regex characters
        $escaped_original = preg_quote( $original_text, '/' );
        
        // PHP-compatible pattern: Match text that is NOT inside HTML tags
        // Pattern: Match text that comes after > or at start, and before < or at end
        // This avoids variable-length lookbehind by using a simpler approach
        $pattern = '/(?<=>|^)([^<]*?)' . $escaped_original . '([^<]*?)(?=<|$)/u';
        
        $result = preg_replace_callback( $pattern, function( $matches ) use ( $replacement_text, $original_text ) {
            // Replace only the original text, preserve surrounding text
            return $matches[1] . $replacement_text . $matches[2];
        }, $html, 1 ); // Limit to 1 replacement to match Polylang behavior
        
        // If replacement failed, return original
        return $result !== null ? $result : $html;
    }
    
    /**
     * Sync block attributes with innerContent when innerContent is updated
     * Matches Polylang's updateCustomBlockInnerHtml approach for custom blocks
     * This prevents block validation errors for blocks that store text in both attrs and innerContent
     * 
     * @param array &$attrs Block attributes to search and update
     * @param string $original_text Original plain text (from innerContent)
     * @param string $translated_text Translated text
     * @return void
     */
    private static function sync_attributes_with_inner_content( array &$attrs, string $original_text, string $translated_text ) {
        if ( empty( $original_text ) || empty( $translated_text ) ) {
            return;
        }
        
        // Normalize text for comparison (trim whitespace)
        $original_text = trim( $original_text );
        $translated_text = trim( $translated_text );
        
        foreach ( $attrs as $key => &$attr_value ) {
            if ( is_string( $attr_value ) ) {
                // Extract plain text from attribute
                $attr_text = wp_strip_all_tags( $attr_value );
                $attr_text = trim( $attr_text );
                
                // If attribute text matches original innerContent text, update it
                if ( $attr_text === $original_text ) {
                    // Replace text in attribute (preserve HTML if present)
                    if ( preg_match( '/<[^>]+>/', $attr_value ) ) {
                        // Has HTML - replace text within HTML using PHP-compatible method
                        $old_value = $attr_value;
                        $attr_value = self::replace_text_outside_html_tags( $attr_value, $original_text, $translated_text );
                    } else {
                        // No HTML - direct replacement
                        $old_value = $attr_value;
                        $attr_value = $translated_text;
                    }
                }
            } elseif ( is_array( $attr_value ) ) {
                // Recursively check nested arrays (for complex attributes)
                self::sync_attributes_with_inner_content( $attr_value, $original_text, $translated_text );
            }
        }
    }
    
}

