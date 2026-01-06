<?php
/**
 * Helper functions for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class.
 */
class WPML_AT_Helper {

	/**
	 * Nonce key for AJAX requests.
	 */
	const NONCE_KEY = 'cp_wpml_auto_translate_nonce';

	/**
	 * Get WPML active languages.
	 *
	 * @return array Array of language codes and names.
	 */
	public static function get_wpml_languages() {
		$languages = array();

		if ( function_exists( 'icl_get_languages' ) ) {
			$wpml_languages = icl_get_languages( 'skip_missing=0' );
			if ( ! empty( $wpml_languages ) && is_array( $wpml_languages ) ) {
				foreach ( $wpml_languages as $lang ) {
					$languages[] = array(
						'code' => $lang['code'],
						'name' => $lang['native_name'],
					);
				}
			}
		} elseif ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				$active_languages = $sitepress->get_active_languages();
				if ( ! empty( $active_languages ) && is_array( $active_languages ) ) {
					foreach ( $active_languages as $code => $lang ) {
						$languages[] = array(
							'code' => $code,
							'name' => $lang['native_name'],
						);
					}
				}
			}
		}

		return $languages;
	}

	/**
	 * Get source language for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string Language code.
	 */
	public static function get_post_source_language( $post_id, $post_type ) {
		global $sitepress;
		$source_lang = null;

		if ( $sitepress ) {
			$source_lang = $sitepress->get_language_for_element( $post_id, 'post_' . $post_type );
		}

		if ( ! $source_lang ) {
			$source_lang = apply_filters( 'wpml_default_language', 'en' );
		}

		return $source_lang;
	}

	/**
	 * Get translations for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return array Translations array.
	 */
	public static function get_post_translations( $post_id, $post_type ) {
		$trid = apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $post_type );

		if ( ! $trid ) {
			return array();
		}

		return apply_filters(
			'wpml_get_element_translations',
			array(),
			$trid,
			'post_' . $post_type
		);
	}

	/**
	 * Extract language code and element ID from translation object/array.
	 *
	 * @param object|array $translation Translation object or array.
	 * @return array Array with 'code' and 'element_id'.
	 */
	public static function extract_translation_info( $translation ) {
		if ( is_object( $translation ) ) {
			return array(
				'code'       => isset( $translation->language_code ) ? $translation->language_code : '',
				'element_id' => isset( $translation->element_id ) ? (int) $translation->element_id : 0,
			);
		}

		return array(
			'code'       => isset( $translation['language_code'] ) ? $translation['language_code'] : '',
			'element_id' => isset( $translation['element_id'] ) ? (int) $translation['element_id'] : 0,
		);
	}

	/**
	 * Get existing translation ID for a specific language.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $post_type   Post type.
	 * @param string $target_lang Target language code.
	 * @return int Translation post ID or 0 if not found.
	 */
	public static function get_existing_translation_id( $post_id, $post_type, $target_lang ) {
		$translations = self::get_post_translations( $post_id, $post_type );

		if ( empty( $translations ) || ! is_array( $translations ) ) {
			return 0;
		}

		foreach ( $translations as $lang_code => $translation ) {
			$info = self::extract_translation_info( $translation );

			if ( $info['code'] === $target_lang && $info['element_id'] ) {
				return $info['element_id'];
			}
		}

		return 0;
	}

	/**
	 * Decode Unicode escape sequences (like u03a0 or \u03a0) to actual characters.
	 *
	 * @param string $text Text with Unicode escape sequences.
	 * @return string Decoded text.
	 */
	public static function decode_unicode_escapes( $text ) {
		if ( empty( $text ) || ! is_string( $text ) ) {
			return $text;
		}

		// Check if text contains Unicode escape sequences (uXXXX or \uXXXX format).
		if ( ! preg_match( '/[\\\\]?u[0-9a-fA-F]{4}/', $text ) ) {
			return $text; // No Unicode escapes found, return as-is.
		}

		// Replace uXXXX with \uXXXX format for JSON decode.
		$text_with_backslash = preg_replace( '/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $text );

		// Try JSON decode which handles \uXXXX format.
		$decoded = json_decode( '"' . $text_with_backslash . '"' );

		if ( json_last_error() === JSON_ERROR_NONE && is_string( $decoded ) ) {
			return $decoded;
		}

		// Fallback: manual decoding of uXXXX patterns (without backslash).
		return preg_replace_callback(
			'/u([0-9a-fA-F]{4})/',
			function( $matches ) {
				$hex        = $matches[1];
				$code_point = hexdec( $hex );
				return mb_convert_encoding( pack( 'n', $code_point ), 'UTF-8', 'UCS-2BE' );
			},
			$text
		);
	}

	/**
	 * Create translation post if missing.
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param string $target_lang     Target language code.
	 * @return int Translation post ID or 0 on failure.
	 */
	public static function create_translation_if_missing( $source_post_id, $target_lang ) {
		global $wpdb;

		$source_post_id = absint( $source_post_id );
		$target_lang    = sanitize_text_field( $target_lang );

		if ( ! $source_post_id || ! $target_lang ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WPML uses custom table structure, direct query necessary.
		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid, element_type
				FROM {$wpdb->prefix}icl_translations
				WHERE element_id = %d
				LIMIT 1",
				$source_post_id
			)
		);

		if ( ! $source ) {
			return 0;
		}

		// Check existing translation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WPML uses custom table structure, direct query necessary.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND language_code = %s",
				$source->trid,
				$target_lang
			)
		);

		if ( $existing ) {
			return (int) $existing;
		}

		$src = get_post( $source_post_id );
		if ( ! $src ) {
			return 0;
		}

		// Detect editor from SOURCE.
		$editor = CP_WPML_Google_Auto_Translate_Ajax::detect_editor( $source_post_id );

		// Create empty shell.
		$new_id = wp_insert_post(
			array(
				'post_type'    => $src->post_type,
				'post_status'  => 'draft',
				'post_title'   => $src->post_title,
				'post_content' => '', // IMPORTANT.
				'post_author'  => $src->post_author,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return 0;
		}

		// Link WPML.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WPML uses custom table structure, direct query necessary.
		$wpdb->insert(
			"{$wpdb->prefix}icl_translations",
			array(
				'element_type'        => $source->element_type,
				'element_id'          => $new_id,
				'trid'               => $source->trid,
				'language_code'      => $target_lang,
				'source_language_code' => null,
			)
		);

		/* ===============================
		* COPY EDITOR STRUCTURE
		* =============================== */

		if ( $editor === 'elementor' ) {

			// Clone Elementor data
			$elementor_data = get_post_meta( $source_post_id, '_elementor_data', true );

			update_post_meta( $new_id, '_elementor_data', $elementor_data );
			update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $new_id, '_elementor_template_type', 'wp-page' );

		} elseif ( $editor === 'gutenberg' ) {

			// Clone Gutenberg structure
			wp_update_post([
				'ID'           => $new_id,
				'post_content' => $src->post_content,
			]);
		}

		return (int) $new_id;
	}

}

