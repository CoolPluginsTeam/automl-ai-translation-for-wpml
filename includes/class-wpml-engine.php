<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class WPML_Engine {

    /* =========================
     * Load AutoPoly block rules
     * ========================= */
    public static function get_block_rules(): array {
        static $rules = null;

        if ( $rules !== null ) {
            return $rules;
        }

        $file = plugin_dir_path( __FILE__ ) . 'block-translation-rules/block-rules.json';
        if ( ! file_exists( $file ) ) {
            $rules = [];
        } else {
            $json  = json_decode( file_get_contents( $file ), true );
            $rules = $json['wpmlautoBlockParseRules'] ?? [];
            $rules = is_array( $rules ) ? $rules : [];
        }

        // Merge with custom block rules (user-enabled/disabled blocks)
        $custom_rules = get_option( 'wpml_at_custom_block_rules', array() );
        if ( is_array( $custom_rules ) && ! empty( $custom_rules ) ) {
            // Remove disabled blocks
            foreach ( $custom_rules as $block_name => $enabled ) {
                if ( ! $enabled && isset( $rules[ $block_name ] ) ) {
                    unset( $rules[ $block_name ] );
                }
            }
            
            // Note: Enabled blocks without rules will use innerHTML fallback in extraction
            // To add new blocks with custom rules, they need to be added to block-rules.json
        }

        // Merge with custom block translation rules (from custom post type editor)
        $custom_block_translation = get_option( 'wpml_at_custom_block_translation', array() );
        if ( is_array( $custom_block_translation ) && ! empty( $custom_block_translation ) ) {
            foreach ( $custom_block_translation as $block_name => $block_attributes ) {
                if ( ! isset( $rules[ $block_name ] ) ) {
                    // Create new block rule if it doesn't exist
                    $rules[ $block_name ] = array();
                }
                
                if ( ! isset( $rules[ $block_name ]['attributes'] ) ) {
                    $rules[ $block_name ]['attributes'] = array();
                }
                
                // Merge custom attributes with existing rules
                $rules[ $block_name ]['attributes'] = array_merge_recursive( $rules[ $block_name ]['attributes'], $block_attributes );
            }
        }

        return $rules;
    }

    /* =========================
     * Extract Gutenberg blocks
     * ========================= */
    public static function extract_gutenberg( string $content ): array {
        $blocks = parse_blocks( $content );
        $rows   = [];
        $rules  = self::get_block_rules();

        self::walk_blocks_extract( $blocks, $rules, $rows, 'b' );

        return [
            'editor'  => 'gutenberg',
            'payload' => $blocks,
            'rows'    => $rows,
        ];
    }

    private static function walk_blocks_extract( array $blocks, array $rules, array &$rows, string $path ) {
        foreach ( $blocks as $i => $block ) {
            // Skip non-array blocks (null blocks, etc.)
            if ( ! is_array( $block ) ) continue;
            
            // Skip null/empty blocks
            if ( empty( $block ) ) continue;

            $block_name = $block['blockName'] ?? '';
            $block_path = $path . ':' . $i;

            // Process blocks that have rules defined (matches JavaScript logic)
            if ( $block_name && isset( $rules[ $block_name ] ) ) {
                // Extract attributes based on block rules (matches getTranslateString logic)
                if ( isset( $rules[ $block_name ]['attributes'] ) ) {
                    self::extract_by_schema(
                        $block['attrs'] ?? [],
                        $rules[ $block_name ]['attributes'],
                        $rows,
                        $block_path . '|attrs',
                        $block_path
                    );
                }
            }

            // Extract innerContent entries separately (like Polylang does)
            // This preserves block structure better than extracting entire innerHTML
            if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
                // Check if content wasn't already extracted via attributes
                $already_extracted = false;
                foreach ( $rows as $existing_row ) {
                    if ( strpos( $existing_row['field_key'], $block_path ) === 0 ) {
                        // Content already extracted via attributes, skip innerContent
                        $already_extracted = true;
                        break;
                    }
                }
                
                if ( ! $already_extracted ) {
                    // Extract each innerContent entry separately (matches Polylang's filterBlockInnerContent)
                    foreach ( $block['innerContent'] as $idx => $content ) {
                        if ( is_string( $content ) && trim( $content ) !== '' ) {
                            // Check if it has actual text content (not just HTML tags)
                            $text_content = wp_strip_all_tags( $content );
                            if ( ! empty( $text_content ) && preg_match( '/[\p{L}\p{N}]/u', $text_content ) ) {
                                $rows[] = [
                                    'field_key' => $block_path . '|innerContent:' . $idx,
                                    'original'  => $content,
                                    'translate' => 1,
                                ];
                            }
                        }
                    }
                }
            }

            // Process inner blocks recursively (matches childBlockAttributesContent logic)
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                self::walk_blocks_extract(
                    $block['innerBlocks'],
                    $rules,
                    $rows,
                    $block_path . '.ib'
                );
            }
        }
    }

    /* =========================
     * Recursive schema extractor
     * Matches JavaScript FilterBlockNestedAttr and filterTranslateAttr logic
     * 
     * JavaScript flow:
     * 1. filterTranslateAttr: Object.values(filterAttr) -> Object.keys(data) -> saveTranslatedAttr
     * 2. saveTranslatedAttr: if true, extract value; else FilterBlockNestedAttr
     * 3. FilterBlockNestedAttr: handles nested objects/arrays -> childAttr/childAttrArray
     * ========================= */
    private static function extract_by_schema( $data, $schema, array &$rows, string $path, string $block_id = '' ) {
        if ( ! is_array( $data ) || ! is_array( $schema ) ) return;

        // Match JavaScript: Object.values(filterAttr) then Object.keys(data)
        // In PHP, we iterate schema keys which represents the structure
        foreach ( $schema as $key => $rule ) {
            if ( ! isset( $data[ $key ] ) ) continue;

            $current_path = $path . '.' . $key;

            // Simple text field (matches JavaScript: filterAttrObj === true)
            if ( $rule === true ) {
                $value = $data[ $key ];
                
                // Handle RichTextData-like structures (if needed)
                // In PHP, we just get the string value directly
                
                // Extract translatable content (matches JavaScript validation)
                if ( is_string( $value ) && trim( $value ) !== '' ) {
                    // Check if content has letters/numbers (matches JavaScript regex check)
                    // JavaScript: /[\p{L}\p{N}]/gu.test(blockAttrContent)
                    if ( preg_match( '/[\p{L}\p{N}]/u', $value ) ) {
                        $rows[] = [
                            'field_key' => $current_path,
                            'original'  => $value,
                            'translate' => 1,
                        ];
                    }
                }
            }
            // Nested object or repeater (matches FilterBlockNestedAttr logic)
            elseif ( is_array( $rule ) && is_array( $data[ $key ] ) ) {
                // Repeater/Array (matches childAttrArray logic)
                if ( array_is_list( $rule ) ) {
                    // JavaScript: Check if dynamicBlockAttr is null/undefined
                    $dynamic_data = $data[ $key ];
                    if ( $dynamic_data === null ) {
                        continue;
                    }

                    // JavaScript: Check prototype - Object.prototype or Array.prototype
                    // In PHP, we check if it's an associative array (object) or list (array)
                    if ( array_is_list( $dynamic_data ) ) {
                        // Process each item in the array with the schema from rule[0]
                        foreach ( $dynamic_data as $index => $item ) {
                            if ( is_array( $item ) ) {
                                self::extract_by_schema(
                                    $item,
                                    $rule[0],
                                    $rows,
                                    $current_path . ':' . $index,
                                    $block_id
                                );
                            } elseif ( is_string( $item ) && trim( $item ) !== '' ) {
                                // Handle string items in arrays
                                if ( preg_match( '/[\p{L}\p{N}]/u', $item ) ) {
                                    $rows[] = [
                                        'field_key' => $current_path . ':' . $index,
                                        'original'  => $item,
                                        'translate' => 1,
                                    ];
                                }
                            }
                        }
                    } else {
                        // If it's an object (associative array), treat as nested object
                        self::extract_by_schema(
                            $dynamic_data,
                            $rule[0],
                            $rows,
                            $current_path,
                            $block_id
                        );
                    }
                }
                // Nested object (matches childAttr logic)
                else {
                    self::extract_by_schema(
                        $data[ $key ],
                        $rule,
                        $rows,
                        $current_path,
                        $block_id
                    );
                }
            }
        }
    }

    /* =========================
     * Apply translations back
     * ========================= */
    public static function apply_translations( array &$blocks, array $rows ) {
        foreach ( $rows as $row ) {
            if ( empty( $row['translate'] ) || empty( $row['translated'] ) ) continue;

            $path = preg_split( '/[.:|]/', $row['field_key'] );
            self::set_by_path( $blocks, $path, $row['translated'] );
        }
    }

    private static function set_by_path( array &$data, array $path, $value ) {
        $ref =& $data;

        foreach ( $path as $segment ) {
            if ( ! isset( $ref[ $segment ] ) ) return;
            $ref =& $ref[ $segment ];
        }

        $ref = $value;
    }

    /* =========================
     * Elementor extraction
     * ========================= */
    public static function extract_elementor( int $post_id ): array {

        $json = get_post_meta( $post_id, '_elementor_data', true );
        if ( empty( $json ) ) {
            return [ 'editor' => 'elementor', 'payload' => null, 'rows' => [] ];
        }
    
        $data = is_string( $json ) ? json_decode( $json, true ) : $json;
        if ( ! is_array( $data ) ) {
            return [ 'editor' => 'elementor', 'payload' => null, 'rows' => [] ];
        }
    
        $rows = [];
        
        // Elementor data is an array of elements - iterate through each top-level element
        if ( array_is_list( $data ) ) {
            // Array of elements
            foreach ( $data as $index => $element ) {
                self::walk_elementor_extract( $element, $rows, [ $index ] );
            }
        } else {
            // Single element object
            self::walk_elementor_extract( $data, $rows, [] );
        }
        $response = [
            'editor'  => 'elementor',
            'payload' => $data,
            'rows'    => $rows,
        ];
        return $response;
    }
    

    public static function should_translate_key( string $key ): bool {

        $dynamic = [ 'title', 'description', 'editor', 'text', 'content', 'label', 'heading', 'subtitle', 'sub_title', 'caption', 'name', 'button', 'link', 'tab', 'accordion', 'toggle', 'testimonial', 'item', 'list', 'icon', 'alert', 'message', 'html' ];
        $static  = [
            'caption',
            'heading',
            'sub_heading',
            'testimonial_content',
            'testimonial_job',
            'testimonial_name',
            'name',
            'button_text',
            'placeholder',
            'tab_title',
            'accordion_title',
            'accordion_content',
            'toggle_title',
            'toggle_content',
            'html',
            'editor',
            'text',
            'content',
            'description',
            'label',
            'title_text',
            'description_text',
            'icon_box_title',
            'icon_box_description',
            'icon_box_content',
        ];
    
        $key_lc = strtolower( $key );
    
        foreach ( $dynamic as $sub ) {
            if ( str_contains( $key_lc, $sub ) ) {
                return true;
            }
        }
    
        return in_array( $key, $static, true );
    }
    
    public static function is_css_property( string $key ): bool {

        $key_lc = strtolower( $key );
        
        // First check: if key contains content-related words, it's likely content, not CSS
        $content_words = [ 'text', 'content', 'title', 'description', 'editor', 'html', 'label', 'caption', 'heading', 'button', 'message', 'alert', 'name', 'subtitle', 'tab', 'accordion', 'toggle' ];
        foreach ( $content_words as $word ) {
            if ( str_contains( $key_lc, $word ) ) {
                // But exclude pure CSS properties that end with CSS suffixes
                if ( preg_match( '/_(color|size|typography|width|height|margin|padding|spacing|align|weight|family|transform|decoration|shadow|radius|opacity|z_index|position|display|overflow)$/', $key_lc ) ) {
                    return true;
                }
                // If it contains content word but doesn't end with CSS suffix, it's content
                return false;
            }
        }
        
        // Second check: pure CSS properties and layout/structure properties
        $css_props = [
            'content_width', 'title_size', 'font_size', 'margin', 'padding',
            'background', 'border', 'color', 'text_align', 'font_weight',
            'font_family', 'line_height', 'letter_spacing', 'text_transform',
            'border_radius', 'box_shadow', 'opacity', 'width', 'height',
            'display', 'position', 'z_index', 'visibility', 'align',
            'max_width', 'content_typography_typography',
            'flex_justify_content', 'title_color', 'description_color',
            'size', 'unit', 'typography', 'spacing', 'gap', 'column', 'row',
            'grid', 'flex', 'justify', 'align_items', 'align_content', 'wrap',
            'direction', 'order', 'grow', 'shrink', 'basis', 'overflow',
            'min_width', 'min_height', 'max_height', 'min_width_tablet',
            'min_width_mobile', 'max_width_tablet', 'max_width_mobile',
            'structure', 'css_filters_css_filter', 'image_box_shadow_box_shadow_type',
            'header_size', 'background_background', 'background_size',
            'background_position', 'background_repeat', 'image_size',
            'align_mobile', 'align_tablet', 'flex_direction', 'flex_align_items'
        ];
    
        foreach ( $css_props as $css ) {
            if ( $key_lc === $css || substr( $key_lc, -strlen( '_' . $css ) ) === '_' . $css ) {
                return true;
            }
        }
    
        return false;
    }
    
    private static function walk_elementor_extract( $element, array &$rows, array $ids ) {

        if ( ! is_array( $element ) ) {
            return;
        }
    
        /* -------------------------------------------------
         * SETTINGS
         * ------------------------------------------------- */
        if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
    
            foreach ( $element['settings'] as $key => $value ) {
    
                if ( self::is_css_property( $key ) ) {
                    continue;
                }
    
                // Simple string
                if (
                    is_string( $value ) &&
                    trim( $value ) !== '' &&
                    self::should_translate_key( $key )
                ) {
                    // Strip HTML tags for checking, but keep original for translation
                    $text_content = wp_strip_all_tags( $value );
                    if ( ! empty( trim( $text_content ) ) ) {
                        $rows[] = [
                            'field_key' => implode( '.', array_merge( $ids, [ 'settings', $key ] ) ),
                            'original'  => $value,
                            'translate' => 1,
                        ];
                    }
                }
    
                // Repeater/Array handling
                if ( is_array( $value ) ) {
                    // Check if it's a list (repeater) or associative array
                    if ( array_is_list( $value ) ) {
                        // Repeater field - iterate through items
                        foreach ( $value as $index => $item ) {
                            if ( ! is_array( $item ) ) {
                                // If item is a string, check if it should be translated
                                if ( is_string( $item ) && trim( $item ) !== '' && self::should_translate_key( $key ) ) {
                                    $text_content = wp_strip_all_tags( $item );
                                    if ( ! empty( trim( $text_content ) ) ) {
                                        $rows[] = [
                                            'field_key' => implode( '.', array_merge( $ids, [ 'settings', $key, $index ] ) ),
                                            'original'  => $item,
                                            'translate' => 1,
                                        ];
                                    }
                                }
                                continue;
                            }
    
                            // Nested array - extract each field
                            foreach ( $item as $rep_key => $rep_val ) {
    
                                if ( self::is_css_property( $rep_key ) ) {
                                    continue;
                                }
    
                                if (
                                    is_string( $rep_val ) &&
                                    trim( $rep_val ) !== '' &&
                                    self::should_translate_key( $rep_key )
                                ) {
                                    $text_content = wp_strip_all_tags( $rep_val );
                                    if ( ! empty( trim( $text_content ) ) ) {
                                        $rows[] = [
                                            'field_key' => implode( '.', array_merge(
                                                $ids,
                                                [ 'settings', $key, $index, $rep_key ]
                                            ) ),
                                            'original'  => $rep_val,
                                            'translate' => 1,
                                        ];
                                    }
                                }
                                
                                // Handle nested arrays in repeaters
                                if ( is_array( $rep_val ) && array_is_list( $rep_val ) ) {
                                    foreach ( $rep_val as $nested_index => $nested_item ) {
                                        if ( is_string( $nested_item ) && trim( $nested_item ) !== '' && self::should_translate_key( $rep_key ) ) {
                                            $text_content = wp_strip_all_tags( $nested_item );
                                            if ( ! empty( trim( $text_content ) ) ) {
                                                $rows[] = [
                                                    'field_key' => implode( '.', array_merge(
                                                        $ids,
                                                        [ 'settings', $key, $index, $rep_key, $nested_index ]
                                                    ) ),
                                                    'original'  => $nested_item,
                                                    'translate' => 1,
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Associative array - might contain translatable content
                        foreach ( $value as $sub_key => $sub_val ) {
                            if ( self::is_css_property( $sub_key ) ) {
                                continue;
                            }
                            
                            if (
                                is_string( $sub_val ) &&
                                trim( $sub_val ) !== '' &&
                                self::should_translate_key( $sub_key )
                            ) {
                                $text_content = wp_strip_all_tags( $sub_val );
                                if ( ! empty( trim( $text_content ) ) ) {
                                    $rows[] = [
                                        'field_key' => implode( '.', array_merge( $ids, [ 'settings', $key, $sub_key ] ) ),
                                        'original'  => $sub_val,
                                        'translate' => 1,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    
        /* -------------------------------------------------
         * NESTED ELEMENTS
         * ------------------------------------------------- */
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $index => $child ) {
                self::walk_elementor_extract(
                    $child,
                    $rows,
                    array_merge( $ids, [ 'elements', $index ] )
                );
            }
        }
    }
    

    public static function apply_elementor( array &$data, array $rows ) {
        foreach ( $rows as $row ) {
            if ( empty( $row['translate'] ) || empty( $row['translated'] ) ) continue;

            $path = preg_split( '/[.:|]/', $row['field_key'] );
            self::set_by_path( $data, $path, $row['translated'] );
        }
    }
}
