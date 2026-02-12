<?php

namespace AUTOML_WPML\Includes\Wpml\Create_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AUTOML_WPML\Includes\Wpml\Get_Package_Content;
use WPML_Elementor_Translatable_Nodes;
use WPML_Elementor_DB_Factory;
use WPML_Elementor_Data_Settings;
use WPML_String_Registration_Factory;
use WPML_Elementor_Register_Strings;
use WPML_Elementor_Update_Translation;
use WPML_Page_Builders_Integration;

/**
 * Elementor_Content_Update
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Elementor_Content_Update {
    /**
     * @var int
     */
    private $post_id;
    /**
     * @var array
     */
    private $translate_strings;
    /**
     * @var int
     */
    private $translated_post_id;
    /**
     * @var string
     */
    private $target_language;
    /**
     * @var WPML_Page_Builders_Integration
     */
    private $elementor_builder_factory;

    public function __construct(int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce) {
        if ( ! $this->is_elementor_content_update($nonce) ) {
            return wp_send_json_error( 'You are not authorized to perform this action.' );
        }

        $this->post_id = $post_id;
        $this->translated_post_id = $translated_post_id;
        $this->translate_strings = $translate_strings;
        $this->target_language = $target_language;

        $this->cretae_elementor_builder_integration();
    }

    private function is_elementor_content_update(string $nonce) {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'automl_wpml_elementor_content_update_nonce' ) ) {
            return false;
        }

        return ( defined( 'DOING_AUTOML_WPML_ELEMENTOR_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOML_WPML_ELEMENTOR_CONTENT_UPDATE' ) );
    }

    private function cretae_elementor_builder_integration(): void {
        $nodes                = new WPML_Elementor_Translatable_Nodes();
        $elementor_db_factory = new WPML_Elementor_DB_Factory();
        $data_settings        = new WPML_Elementor_Data_Settings( $elementor_db_factory->create() );

        $string_registration_factory = new WPML_String_Registration_Factory( $data_settings->get_pb_name() );
        $string_registration         = $string_registration_factory->create();

        $register_strings   = new WPML_Elementor_Register_Strings( $nodes, $data_settings, $string_registration );
        $update_translation = new WPML_Elementor_Update_Translation( $nodes, $data_settings );

        $this->elementor_builder_factory = new WPML_Page_Builders_Integration( $register_strings, $update_translation, $data_settings );
    }

    public function update_elementor_content(): void {
        $this->update_module_translation();
    }

    private function update_module_translation(): void {
        $source_post=get_post($this->post_id);
        $this->elementor_builder_factory->update_translated_post( 'Elementor', $this->translated_post_id, $source_post, $this->translate_strings, $this->target_language );

        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        delete_metadata( 'post', $this->translated_post_id, '_elementor_element_cache', '' );
    }

}