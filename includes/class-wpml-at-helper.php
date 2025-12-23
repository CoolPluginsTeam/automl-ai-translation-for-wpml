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
	 * Constructor.
	 */
	public function __construct() {
		// Helper class - no hooks needed.
	}

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

	public static function find_wpml_job_id_for_post_lang( int $post_id, string $target_lang ): int {
		global $wpdb;
	
		$translations = $wpdb->prefix . 'icl_translations';
		$status       = $wpdb->prefix . 'icl_translation_status';
		$jobs         = $wpdb->prefix . 'icl_translate_job';
	
		// Get TRID for original post
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid
				 FROM {$translations}
				 WHERE element_id = %d
				   AND element_type LIKE 'post_%%'
				 LIMIT 1",
				$post_id
			),
			ARRAY_A
		);
	
		if ( empty( $row['trid'] ) ) {
			return 0;
		}
	
		$trid = (int) $row['trid'];
	
		// Find latest job for this TRID + target language
		$job_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tj.job_id
				 FROM {$jobs} tj
				 INNER JOIN {$status} ts ON ts.rid = tj.rid
				 INNER JOIN {$translations} t ON t.translation_id = ts.translation_id
				 WHERE t.trid = %d
				   AND t.language_code = %s
				 ORDER BY tj.job_id DESC
				 LIMIT 1",
				$trid,
				$target_lang
			)
		);
	
		return $job_id ?: 0;
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
}

