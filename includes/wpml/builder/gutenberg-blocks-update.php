<?php

namespace AUTOML_WPML\Includes\Wpml\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_Gutenberg_Integration;
use WPML_Gutenberg_Config_Option;
use WPML_ST_String_Factory;
use WPML_Gutenberg_Strings_Registration;
use WPML_PB_Reuse_Translations;
use WPML_PB_String_Translation;
use WPML\PB\TranslateLinks as WPML_TranslateLinks;
use WPML\PB\Gutenberg\StringsInBlock\Collection as StringsInBlockCollection;
use WPML\PB\Gutenberg\StringsInBlock\HTML as StringsInBlockHTML;
use WPML\PB\Gutenberg\StringsInBlock\Attributes as StringsInBlockAttributes;

use function WPML\Container\make;

/**
 * Gutenberg_Blocks_Update
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Gutenberg_Blocks_Update extends Content_Update_Base {
    /**
     * @var WPML_Gutenberg_Integration
     */
    private $gutenberg_builder_factory;

    /**
     * Editor Type
     */
    protected $editor_type = 'Gutenberg';

    public function __construct(int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce) {
        add_filter('option_wpml-gutenberg-config', array($this, 'update_gutenberg_config'), 10, 2);
        parent::__construct($post_id, $translated_post_id, $translate_strings, $target_language, $nonce);
    }

    protected function is_content_update() {
        return ( defined( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) );
    }

    protected function cretae_builder_integration(): void {

        if(!$this->content_update_allowed){
            wp_send_json_error( 'You are not authorized to perform this action three.' );
            exit;
        }

		global $sitepress, $wpdb;

        $config_option    = new WPML_Gutenberg_Config_Option();
		$strings_in_block = $this->create_strings_in_block( $config_option );
		$string_factory   = new WPML_ST_String_Factory( $wpdb );

		$strings_registration = new WPML_Gutenberg_Strings_Registration(
			$strings_in_block,
			$string_factory,
			new WPML_PB_Reuse_Translations( $string_factory ),
			new WPML_PB_String_Translation( $wpdb ),
			make( 'WPML_Translate_Link_Targets' ),
			WPML_TranslateLinks::getTranslatorForString( $string_factory, $sitepress->get_active_languages() )
		);

        $this->gutenberg_builder_factory=new WPML_Gutenberg_Integration($strings_in_block,$config_option,$strings_registration, $sitepress);
    }

    private function create_strings_in_block( WPML_Gutenberg_Config_Option $config_option ) {
		$string_parsers = [
			new StringsInBlockHTML( $config_option ),
			new StringsInBlockAttributes( $config_option ),
		];

		return new StringsInBlockCollection( $string_parsers );
	}

    public function update_gutenberg_config( $config, $option_name ) {
        if($this->is_content_update()){
            $custom_block_config=$this->get_block_parse_rules();
    
            if(!empty($custom_block_config)){
                $this->set_custom_block_config($config,$custom_block_config);
            }
        }
        
        return $config;
    }

    private function get_block_parse_rules()
    {
        $response = wp_remote_get( esc_url_raw( WPML_AT_PLUGIN_URL . 'includes/blocks-config/blocks-config.json' ), array(
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            global $wp_filesystem;

            // Initialize the WordPress filesystem
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            WP_Filesystem();

            $local_path = WPML_AT_PLUGIN_DIR . 'includes/blocks-config/blocks-config.json';
            if($wp_filesystem->exists($local_path) && $wp_filesystem->is_readable( $local_path )){
                $block_rules = $wp_filesystem->get_contents( $local_path );
            }else{
                $block_rules = array();
            }
        } else {
            $block_rules = wp_remote_retrieve_body( $response );
        }

        if(empty($block_rules)){
            return array();
        }

        $block_translation_rules = json_decode($block_rules);

        return $block_translation_rules;
    }

    private function set_custom_block_config( array &$config, $custom_block_config ): void {

        if ( empty( $custom_block_config ) ) {
            return;
        }
    
        foreach ( (array) $custom_block_config as $block_name => $block_config ) {
    
            // Skip if block not present in base config
            if ( ! isset( $config[ $block_name ] ) ) {
                continue;
            }
    
            // Ensure attributes exist
            if ( empty( $block_config->attributes ) ) {
                continue;
            }
    
            $this->set_block_config_attributes(
                $config,
                $block_name,
                (array) $block_config->attributes
            );
        }
    }
    
    private function set_block_config_attributes( array &$config, string $block_name, array $attributes ): void {
        // Ensure base keys exist
        if ( ! isset( $config[ $block_name ]['key'] ) ) {
            $config[ $block_name ]['key'] = [];
        }


        foreach ( $attributes as $attr_key => $attr_value ) {
    
            // Skip if attribute already exists
            if ( isset( $config[ $block_name ]['key'][ $attr_key ] ) ) {
                continue;
            }
    
            /**
             * CASE 1 — SIMPLE ATTRIBUTE (true)
             */
            if ( $attr_value === true ) {
                $config[ $block_name ]['key'][ $attr_key ] = [];
                continue;
            }
    
            /**
             * CASE 2 — OBJECT ATTRIBUTE
             */
            if ( is_object( $attr_value ) ) {
                $this->parse_object_children( $config[ $block_name ]['key'][ $attr_key ], $attr_value );
                continue;
            }
    
            /**
             * CASE 3 — ARRAY ATTRIBUTE
             */
            if ( is_array( $attr_value ) ) {
                if(!isset($config[ $block_name ]['key'][ $attr_key ])){
                    $config[ $block_name ]['key'][ $attr_key ] = ['*' => ['children' => []]];
                }
                    
                if(!isset($config[ $block_name ]['key'][ $attr_key ]['*'])) $config[ $block_name ]['key'][ $attr_key ]['*'] = ['children' => []];

                $this->parse_array_children( $config[ $block_name ]['key'][ $attr_key ]['*']['children'], $attr_value[0] );
            }
        }
    }

    private function parse_object_children( &$reference, $object ): void {    
        foreach ( (array) $object as $child_key => $child_value ) {
    
            // simple true value
            if ( $child_value === true ) {
                $reference[ $child_key ]= [];
                continue;
            }
    
            // nested object
            if ( is_object( $child_value ) ) {
                $this->parse_object_children( $reference[ $child_key ], $child_value );
                continue;
            }
    
            // nested array
            if ( is_array( $child_value ) ) {
                if(!isset($reference[$child_key]['*'])) $reference[$child_key]['*'] = ['children' => []];
                    $this->parse_array_children( $reference[ $child_key ]["*"]['children'], $child_value[0] );
            }
        }
    }

    private function parse_array_children( &$reference, $child_values ): void {

        if ( is_object( $child_values ) ) {
            $this->parse_object_children( $reference, $child_values );
            return;
        }
    
        if ( is_array( $child_values ) ) {
            if(!isset($reference['*'])) $reference['*'] = ['children' => []];
            $this->parse_array_children( $reference["*"]['children'], $child_values[0] );
            return;
        }
    }            

    protected function update_builder_translation(): void {

        if(!$this->gutenberg_builder_factory instanceof WPML_Gutenberg_Integration){
            wp_send_json_error( 'Gutenberg builder factory not found.' );
            exit;
        }
        
        $source_post=get_post($this->post_id);
        $source_content=$source_post->post_content;

        $updated_content=$this->gutenberg_builder_factory->replace_strings_in_blocks($source_content, $this->translate_strings, $this->target_language);
        
        wpml_update_escaped_post( [ 'ID' => $this->translated_post_id, 'post_content' => $updated_content ], $this->target_language );
    }
}