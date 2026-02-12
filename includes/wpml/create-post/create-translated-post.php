<?php

namespace AUTOML_WPML\Includes\Wpml\Create_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AUTOML_WPML\Includes\Wpml\Get_Package_Content;

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
	 * @var string
	 */
	private $editor_type;

	/**
	 * @var array
	 */
	private $post_translation_status;

	public function __construct( int $post_id, array $translate_strings, string $translated_title, string $source_language, string $target_language, string $editor_type ) {
		if ( ! $this->is_create_post() ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'You are not authorized to perform this action.' );
		}

		if ( ! isset( $editor_type ) || empty( $editor_type ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'Invalid editor type' );
		}

		if ( ! isset( $post_id ) || empty( $post_id ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'Invalid post ID' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'You are not authorized to perform this action.' );
		}

		if ( ! isset( $source_language ) || empty( $source_language ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'Invalid source language' );
		}

		if ( ! isset( $target_language ) || empty( $target_language ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'Invalid target language' );
		}

		if ( ! isset( $translate_strings ) || empty( $translate_strings ) ) {
			$this->post_translation_status = false;
			return wp_send_json_error( 'No translate strings found' );
		}

		if ( isset( $translated_title ) && ! empty( $translated_title ) ) {
			$this->translated_title = $translated_title;
		}

		$this->editor_type             = $editor_type;
		$this->post_translation_status = true;
		$this->post_id                 = $post_id;
		$this->source_language         = $source_language;
		$this->target_language         = $target_language;
		$this->filter_translate_strings( $translate_strings );
	}

	public function create_post() {
		if ( ! $this->is_create_post() ) {
			return wp_send_json_error( 'You are not authorized to perform this action.' );
		}

		if ( ! $this->post_translation_status ) {
			return wp_send_json_error( 'Post translation status is false' );
		}

		return $this->create_translated_post();
	}

	private function is_create_post() {
		return ( defined( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST' ) && true === constant( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST' ) );
	}

	private function create_translated_post() {
	}

	private function filter_translate_strings( array $translate_strings ): void {
		$this->translate_strings = array();

		$get_package_content  = new Get_Package_Content( $this->post_id, $this->source_language );
		$translatable_strings = $get_package_content->get_translatable_strings();

		if ( isset( $translatable_strings['contents'] ) && is_array( $translatable_strings['contents'] ) && ! empty( $translatable_strings['contents'] ) ) {
			foreach ( $translatable_strings['contents'] as $package_key => $package_content ) {
				if ( isset( $translate_strings[ $package_key ] ) && ! empty( $translate_strings[ $package_key ] ) ) {
					if ( isset( $package_content['translate'] ) && $package_content['translate'] === 1 ) {
						if ( isset( $package_content['html'] ) && isset( $package_content['text'] ) ) {
							if ( $package_content['text'] === $package_content['html'] ) {
								$this->translate_strings[ $package_key ] = array(
									$this->target_language => array(
										'value'  => sanitize_text_field( $translate_strings[ $package_key ]['html'] ),
										'status' => 10,
									),
								);
							} elseif ( ! empty( $package_content['html'] ) ) {
								$this->translate_strings[ $package_key ] = array(
									$this->target_language => array(
										'value'  => wp_kses_post( $translate_strings[ $package_key ]['html'] ),
										'status' => 10,
									),
								);
							}
						}
					}
				}
			}
		}
	}
}
