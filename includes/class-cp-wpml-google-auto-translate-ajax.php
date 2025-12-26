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
         * 2. CREATE translated post (WPML-safe)
         * ---------------------------------- */
        $translated_post_id = wp_insert_post([
            'post_type'   => $post->post_type,
            'post_status' => 'draft',
            'post_title'  => $post->post_title,
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
            foreach ( $strings as $row ) {

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
    
            wp_send_json_success();
        }
    
        /* ----------------------------------
         * 4. GUTENBERG / UAGB / BLOCKS
         * ---------------------------------- */
        if ( $is_blocks ) {
            $blocks = parse_blocks( $post->post_content );
    
            foreach ( $strings as $row ) {
                self::replace_block_text( $blocks, $row['field_key'], $row['translated'] );
            }
    
            // IMPORTANT: serialize ORIGINAL structure
            $content = serialize_blocks( $blocks );
    
            wp_update_post([
                'ID'           => $translated_post_id,
                'post_content' => $content
            ]);
    
            wp_send_json_success();
        }
    
        /* ----------------------------------
         * 5. CLASSIC EDITOR FALLBACK
         * ---------------------------------- */
        $content = $post->post_content;
    
        foreach ( $strings as $row ) {
            $content = str_replace( $row['original'], $row['translated'], $content );
        }
    
        wp_update_post([
            'ID'           => $translated_post_id,
            'post_content' => $content
        ]);
    
        wp_send_json_success();
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
        foreach ( $blocks as &$block ) {
    
            if ( isset( $block['attrs'] ) ) {
                foreach ( $block['attrs'] as $k => $v ) {
                    if ( is_string( $v ) && $v === $path ) {
                        $block['attrs'][ $k ] = $value;
                    }
                }
            }
    
            if ( ! empty( $block['innerBlocks'] ) ) {
                self::replace_block_text( $block['innerBlocks'], $path, $value );
            }
        }
    }

    private static function replace_by_path( array &$data, string $path, string $value ) {
        $keys = preg_split( '/\.|\[|\]/', $path, -1, PREG_SPLIT_NO_EMPTY );
        $ref  = &$data;
    
        foreach ( $keys as $key ) {
            if ( ! isset( $ref[ $key ] ) ) {
                return;
            }
            $ref = &$ref[ $key ];
        }
    
        $ref = $value;
    }
    
    
}
