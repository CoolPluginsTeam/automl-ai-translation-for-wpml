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
            return $rules;
        }

        $json  = json_decode( file_get_contents( $file ), true );
        $rules = $json['WPML_AT_BlockParseRules'] ?? [];

        return is_array( $rules ) ? $rules : [];
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
            if ( ! is_array( $block ) ) continue;

            $block_name = $block['blockName'] ?? '';
            $block_path = $path . ':' . $i;

            // 1. Extract ATTRS (rule-based)
            if ( $block_name && isset( $rules[ $block_name ]['attributes'] ) ) {
                self::extract_by_schema(
                    $block['attrs'] ?? [],
                    $rules[ $block_name ]['attributes'],
                    $rows,
                    $block_path . '|attrs'
                );
            }

            // 2. Extract innerHTML (core blocks, headings, paragraphs)
            if (
                ! empty( $block['innerHTML'] ) &&
                is_string( $block['innerHTML'] )
            ) {
                $html = trim( $block['innerHTML'] );

                // Ignore empty wrappers like <p></p>
                if ( wp_strip_all_tags( $html ) !== '' ) {
                    $rows[] = [
                        'field_key' => $block_path . '|innerHTML',
                        'original'  => $html,
                        'translate' => 1,
                    ];
                }
            }


            if ( ! empty( $block['innerBlocks'] ) ) {
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
     * (this is AutoPoly magic)
     * ========================= */
    private static function extract_by_schema( $data, $schema, array &$rows, string $path ) {
        if ( ! is_array( $data ) || ! is_array( $schema ) ) return;

        foreach ( $schema as $key => $rule ) {
            if ( ! isset( $data[ $key ] ) ) continue;

            $current_path = $path . '.' . $key;

            // Simple text field
            if ( $rule === true && is_string( $data[ $key ] ) && trim( $data[ $key ] ) !== '' ) {
                $rows[] = [
                    'field_key' => $current_path,
                    'original'  => $data[ $key ],
                    'translate' => 1,
                ];
            }

            // Nested object or repeater
            elseif ( is_array( $rule ) && is_array( $data[ $key ] ) ) {

                // Repeater
                if ( array_is_list( $rule ) ) {
                    foreach ( $data[ $key ] as $index => $item ) {
                        self::extract_by_schema(
                            $item,
                            $rule[0],
                            $rows,
                            $current_path . ':' . $index
                        );
                    }
                }
                // Object
                else {
                    self::extract_by_schema(
                        $data[ $key ],
                        $rule,
                        $rows,
                        $current_path
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
