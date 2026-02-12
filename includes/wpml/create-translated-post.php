<?php

namespace AUTOML_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create_Translated_Post
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Create_Translated_Post {

    /**
     * @var int
     */
    private $post_id;
    /**
     * @var array
     */
    private $translate_strings;
    /**
     * @var string
     */
    private $translated_title;
    /**
     * @var string
     */
    private $source_language;
    /**
     * @var string
     */
    private $target_language;

    /**
     * @var array
     */
    private $post_translation_status;

	public function __construct(int $post_id, array $translate_strings, string $translated_title, string $source_language, string $target_language) {
        if(!$this->is_create_post()) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'You are not authorized to perform this action.' );
        }

        if(!isset($post_id) || empty($post_id)) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'Invalid post ID' );
        }

        if(!current_user_can('edit_post', $post_id)) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'You are not authorized to perform this action.' );
        }

        if(!isset($source_language) || empty($source_language)) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'Invalid source language' );
        }

        if(!isset($target_language) || empty($target_language)) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'Invalid target language' );
        }

        if(!isset($translate_strings) || empty($translate_strings)) {
            $this->post_translation_status = false;
            return wp_send_json_error( 'No translate strings found' );
        }
        

        if(isset($translated_title) && !empty($translated_title)) {
            $this->translated_title = $translated_title;
        }

        $this->post_translation_status = true;
        $this->post_id = $post_id;
        $this->source_language = $source_language;
        $this->target_language = $target_language;
        $this->filter_translate_strings($translate_strings);
	}

    public function create_post(){
        if(!$this->is_create_post()) {
            return wp_send_json_error( 'You are not authorized to perform this action.' );
        }

        if(!$this->post_translation_status) {
            return wp_send_json_error( 'Post translation status is false' );
        }

        return $this->create_translated_post();
    }
    
    private function is_create_post() {
        return (defined('DOING_AUTOML_WPML_CREATE_TRANSLATED_POST') && true === constant('DOING_AUTOML_WPML_CREATE_TRANSLATED_POST'));
	}

	private function create_translated_post() {
		check_ajax_referer( WPML_AT_Helper::NONCE_KEY, 'nonce' );

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$target_lang = sanitize_text_field( $_POST['target_lang'] ?? '' );
		$rows = (array) ( $_POST['translated_strings'] ?? [] );
	}

    private function filter_translate_strings(array $translate_strings) {
        $filtered_translate_strings = array();
        
        var_dump($translate_strings);
    }
}