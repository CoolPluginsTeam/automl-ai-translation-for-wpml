<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CP_WPML_Google_Auto_Translate_Ajax {

    const NONCE = 'cp_wpml_auto_translate_nonce';

    public static function init() {

        add_action(
            'wp_ajax_cp_wpml_google_auto_translate_get_post_contents',
            [ __CLASS__, 'get_post_contents' ]
        );

        add_action(
            'wp_ajax_cp_wpml_google_auto_translate_save_translation',
            [ __CLASS__, 'save_translation' ]
        );

        add_action( 
            'wp_ajax_cp_wpml_google_auto_translate_get_pending_languages', 
            [ __CLASS__, 'get_pending_languages' ] 
        );

    }

    public static function detect_editor( $post_id ) {

		// Elementor
		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			return 'elementor';
		}

		// Gutenberg
		if ( has_blocks( get_post_field( 'post_content', $post_id ) ) ) {
			return 'gutenberg';
		}

		return 'classic';
	}

    /* ======================================================
     * GET POST CONTENT
     * (Used by openTranslationTablePopup)
     * ====================================================== */
    public static function get_post_contents() {

        check_ajax_referer( self::NONCE, 'nonce' );

        $ids         = (array) ( $_POST['ids'] ?? [] );
        $target_lang = sanitize_text_field( $_POST['target_lang'] ?? '' );

        if ( empty( $ids ) ) {
            wp_send_json_error();
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
	 */
    public static function get_pending_languages() {
		// Check nonce but don't die on failure - return JSON error instead
		$nonce_check = check_ajax_referer( self::NONCE, 'nonce', false );
		if ( ! $nonce_check ) {
			wp_send_json_error( array( 'msg' => 'Security check failed. Please refresh the page and try again.' ) );
			return;
		}
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => 'No permission' ) );
		}

		$ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
		$ids = array_map( 'intval', $ids );

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
    
     public static function save_translation() {
        check_ajax_referer( self::NONCE, 'nonce' );
    
        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $target_lang = sanitize_text_field( $_POST['target_lang'] ?? '' );
        $strings     = $_POST['translated_strings'] ?? [];
    
        if ( ! $post_id || ! $target_lang || empty( $strings ) ) {
            wp_send_json_error([ 'msg' => 'Missing data' ]);
        }
    
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error([ 'msg' => 'Invalid post' ]);
        }
    
        /* ----------------------------------
         * 1. Detect editor correctly
         * ---------------------------------- */
        $is_elementor = get_post_meta( $post_id, '_elementor_data', true );
        $is_blocks    = has_blocks( $post->post_content );
    
        /* ----------------------------------
         * 1.5. Extract translated title from strings
         * ---------------------------------- */
        $translated_title = $post->post_title; // Default to original
        foreach ( $strings as $row ) {
            if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' && ! empty( $row['translated'] ) ) {
                $translated_title = sanitize_text_field( wp_strip_all_tags( $row['translated'] ) );
                break;
            }
        }
    
        /* ----------------------------------
         * 2. CREATE translated post (WPML-safe)
         * ---------------------------------- */
        $translated_post_id = wp_insert_post([
            'post_type'   => $post->post_type,
            'post_status' => 'draft',
            'post_title'  => $translated_title,
            'post_author' => get_current_user_id(),
        ]);
    
        if ( is_wp_error( $translated_post_id ) ) {
            wp_send_json_error([ 'msg' => 'Post creation failed' ]);
        }
    
        // Link with WPML (CORRECT WAY)
        do_action( 'wpml_set_element_language_details', [
            'element_id'           => $translated_post_id,
            'element_type'         => 'post_' . $post->post_type,
            'trid'                 => apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $post->post_type ),
            'language_code'        => $target_lang,
            'source_language_code' => apply_filters( 'wpml_post_language_details', null, $post_id )['language_code']
        ]);
    
        /* ----------------------------------
         * 3. ELEMENTOR (AutoPoly way)
         * ---------------------------------- */
        if ( $is_elementor ) {
            $data = json_decode( $is_elementor, true );
            
            if ( ! is_array( $data ) ) {
                wp_send_json_error([ 'msg' => 'Invalid Elementor data' ]);
            }
            
            foreach ( $strings as $row ) {
                // Skip title field - already handled above
                if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' ) {
                    continue;
                }

                // Extract final key name
                $parts = explode( ':', $row['field_key'] );
                $final_key = end( $parts );
            
                if ( ! self::is_translatable_elementor_key( $final_key ) ) {
                    continue;
                }

                if ( self::is_forbidden_elementor_key( $final_key ) ) {
                    continue;
                }
            
                self::replace_by_path(
                    $data,
                    $row['field_key'],
                    wp_kses_post( $row['translated'] )
                );
            }
    
            update_post_meta(
                $translated_post_id,
                '_elementor_data',
                wp_slash( wp_json_encode( $data ) )
            );
    
            update_post_meta( $translated_post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $translated_post_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $translated_post_id, '_elementor_version', get_post_meta( $post_id, '_elementor_version', true ) );
    
            wp_send_json_success([
                'msg' => 'Elementor translation saved successfully',
                'post_id' => $translated_post_id
            ]);
        }
    
        /* ----------------------------------
         * 4. GUTENBERG / UAGB / BLOCKS
         * ---------------------------------- */
        if ( $is_blocks ) {
            $blocks = parse_blocks( $post->post_content );
            $replacement_count = 0;
    
            foreach ( $strings as $row ) {
                // Skip title field - already handled above
                if ( isset( $row['field_key'] ) && $row['field_key'] === 'title' ) {
                    continue;
                }
                
                if ( ! empty( $row['field_key'] ) && ! empty( $row['translated'] ) ) {
                    self::replace_block_text( $blocks, $row['field_key'], $row['translated'] );
                    $replacement_count++;
                }
            }
    
            // IMPORTANT: serialize blocks with translated content
            $content = serialize_blocks( $blocks );
    
            if ( empty( $content ) || trim( $content ) === '' ) {
                wp_send_json_error([ 
                    'msg' => 'Generated content is empty',
                    'debug' => [
                        'blocks_count' => count( $blocks ),
                        'strings_count' => count( $strings ),
                        'replacements' => $replacement_count
                    ]
                ]);
            }
    
            wp_update_post([
                'ID'           => $translated_post_id,
                'post_content' => $content
            ]);
    
            wp_send_json_success([
                'msg' => 'Gutenberg translation saved successfully',
                'post_id' => $translated_post_id,
                'debug' => [
                    'replacements' => $replacement_count,
                    'content_length' => strlen( $content )
                ]
            ]);
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
                error_log( 'WPML Auto Translate: Classic editor replaced segment ' . $item['field_key'] );
            } else {
                error_log( 'WPML Auto Translate: Classic editor - no match for ' . $item['field_key'] );
            }
        }
        
        // Log replacement results
        error_log( "WPML Auto Translate: Classic editor made {$replacement_count} replacements out of " . count( $translations ) . " segments" );
        
        // If no replacements were made, log warning but still save
        if ( $replacement_count === 0 && count( $translations ) > 0 ) {
            error_log( 'WPML Auto Translate: Warning - No text replacements made for classic editor' );
        }
        
        // Ensure we have valid content
        if ( empty( trim( $content ) ) ) {
            error_log( 'WPML Auto Translate: Error - Generated content is empty, using original' );
            $content = $post->post_content;
        }
    
        // Update the post with translated content
        $update_result = wp_update_post([
            'ID'           => $translated_post_id,
            'post_content' => $content
        ], true );
        
        if ( is_wp_error( $update_result ) ) {
            error_log( 'WPML Auto Translate: Error updating classic editor post: ' . $update_result->get_error_message() );
            wp_send_json_error([
                'msg' => 'Failed to update post content: ' . $update_result->get_error_message()
            ]);
        }
    
        wp_send_json_success([
            'msg' => 'Classic editor translation saved successfully',
            'post_id' => $translated_post_id,
            'debug' => [
                'replacements' => $replacement_count,
                'total_segments' => count( $translations ),
                'content_length' => strlen( $content ),
                'original_length' => strlen( $post->post_content )
            ]
        ]);
    }

    private static function is_translatable_elementor_key( string $key ): bool {
        return in_array( $key, [
            'title',
            'editor',
            'text',
            'description',
            'content',
            'heading',
            'caption',
            'html',
            'button_text',
            'placeholder',
            'label',
            'sub_title',
            'tab_title',
            'accordion_title',
            'accordion_content',
            'toggle_title',
            'toggle_content',
        ], true );
    }

    private static function is_forbidden_elementor_key( string $key ): bool {
        return in_array( $key, [
            'align',
            'align_mobile',
            'align_tablet',
            'flex_direction',
            'flex_align_items',
            'background_background',
            'background_size',
            'background_position',
            'background_repeat',
            'image_size',
            'structure',
            'width',
            'height',
            'size',
            'unit',
            'color',
            'css_filters_css_filter',
            'image_box_shadow_box_shadow_type',
            'header_size',
        ], true );
    }
    
    
    private static function replace_block_text( array &$blocks, string $path, string $value ) {
        // Parse the path (e.g., "b:0|innerHTML" or "b:0.ib:1|attrs.heading")
        // Split on delimiters: . | :
        $parts = preg_split( '/[.:|]/', $path, -1, PREG_SPLIT_NO_EMPTY );
        
        // Remove the 'b' prefix (it's just a marker, not an actual array key)
        if ( ! empty( $parts ) && $parts[0] === 'b' ) {
            array_shift( $parts );
        }
        
        // Navigate through the block structure using the path
        $ref = &$blocks;
        $navigation_path = [];
        
        // Navigate to the parent (the block itself)
        $parts_count = count( $parts );
        for ( $i = 0; $i < $parts_count - 1; $i++ ) {
            $key = $parts[ $i ];
            
            // Handle special keys
            if ( $key === 'ib' ) {
                $key = 'innerBlocks';
            }
            
            // Check if key exists (could be numeric index or string key)
            if ( is_numeric( $key ) ) {
                $key = (int) $key;
            }
            
            $navigation_path[] = $key;
            
            if ( ! isset( $ref[ $key ] ) ) {
                error_log( 'WPML Auto Translate: Path not found - ' . $path . ' (failed at: ' . implode( '->', $navigation_path ) . ')' );
                return;
            }
            
            $ref = &$ref[ $key ];
        }
        
        // Now $ref points to the block itself
        // Get the final key (innerHTML, attrs, etc.)
        $final_key = $parts[ $parts_count - 1 ];
        
        // Special handling for innerHTML - also update innerContent
        if ( $final_key === 'innerHTML' ) {
            if ( ! isset( $ref['innerHTML'] ) ) {
                error_log( 'WPML Auto Translate: innerHTML not found at ' . $path );
                return;
            }
            
            $old_value = substr( $ref['innerHTML'], 0, 50 );
            
            // Update innerHTML
            $ref['innerHTML'] = $value;
            
            // CRITICAL: Also update innerContent array (this is what gets serialized!)
            if ( isset( $ref['innerContent'] ) && is_array( $ref['innerContent'] ) ) {
                // Replace the HTML in innerContent array
                foreach ( $ref['innerContent'] as $idx => $content ) {
                    if ( is_string( $content ) && trim( $content ) !== '' ) {
                        // Found HTML content, replace it
                        $ref['innerContent'][ $idx ] = $value;
                        break; // Only replace first non-empty string
                    }
                }
            }
            
            error_log( 'WPML Auto Translate: Replaced innerHTML + innerContent at ' . $path . ' | Old: ' . $old_value . '... | New: ' . substr( $value, 0, 50 ) . '...' );
        } else {
            // Regular path update (for attrs.content, etc.)
            if ( ! isset( $ref[ $final_key ] ) ) {
                error_log( 'WPML Auto Translate: Key "' . $final_key . '" not found at ' . $path );
                return;
            }
            
            $old_value = is_string( $ref[ $final_key ] ) ? substr( $ref[ $final_key ], 0, 50 ) : '(not string)';
            $ref[ $final_key ] = $value;
            
            error_log( 'WPML Auto Translate: Replaced at ' . $path . ' | Old: ' . $old_value . '... | New: ' . substr( $value, 0, 50 ) . '...' );
        }
    }

    private static function replace_by_path( array &$data, string $path, string $value ) {
        // Parse the path for Elementor (e.g., "e:0|settings:title" or "e|elements:0|settings:text")
        $keys = preg_split( '/[.:|]/', $path, -1, PREG_SPLIT_NO_EMPTY );
        
        // Remove the 'e' prefix (it's just a marker, not an actual array key)
        if ( ! empty( $keys ) && $keys[0] === 'e' ) {
            array_shift( $keys );
        }
        
        $ref = &$data;
    
        foreach ( $keys as $key ) {
            // Convert numeric strings to integers for proper array access
            if ( is_numeric( $key ) ) {
                $key = (int) $key;
            }
            
            if ( ! isset( $ref[ $key ] ) ) {
                return;
            }
            $ref = &$ref[ $key ];
        }
    
        $ref = $value;
    }
    
    
}
