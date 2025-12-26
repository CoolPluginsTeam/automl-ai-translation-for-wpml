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

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return [ 'editor' => 'elementor', 'payload' => null, 'rows' => [] ];
        }

        $rows = [];
        self::walk_elementor_extract( $data, $rows, 'e' );

        return [
            'editor'  => 'elementor',
            'payload' => $data,
            'rows'    => $rows,
        ];
    }

    private static function walk_elementor_extract( $node, array &$rows, string $path ) {
        if ( ! is_array( $node ) ) return;

        if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
            foreach ( $node['settings'] as $k => $v ) {
                if ( is_string( $v ) && trim( $v ) !== '' ) {
                    $rows[] = [
                        'field_key' => $path . '|settings:' . $k,
                        'original'  => $v,
                        'translate' => 1,
                    ];
                }
            }
        }

        foreach ( $node as $k => $v ) {
            if ( is_array( $v ) ) {
                self::walk_elementor_extract(
                    $v,
                    $rows,
                    is_int( $k ) ? $path . ':' . $k : $path . '|' . $k
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
