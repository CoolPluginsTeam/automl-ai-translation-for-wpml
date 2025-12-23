<?php
/**
 * AJAX handlers for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler class.
 */
class WPML_AT_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cp_wpml_google_auto_translate_get_post_contents', array( $this, 'get_post_contents' ) );
		add_action( 'wp_ajax_cp_wpml_google_auto_translate_save_translation', array( $this, 'save_translation' ) );
		add_action( 'wp_ajax_cp_wpml_google_auto_translate_get_pending_languages', array( $this, 'get_pending_languages' ) );
	}

	/**
	 * Decode base64 encoded WPML package contents
	 *
	 * @param array $package The WPML package array
	 * @return array Decoded package with readable strings
	 */
	private function decode_wpml_package($package) {
		$decoded_package = $package;
		
		if (isset($package['contents']) && is_array($package['contents'])) {
			foreach ($package['contents'] as $key => $content) {
				if (is_array($content) && isset($content['format']) && $content['format'] === 'base64') {
					// Decode base64 data
					$decoded_package['contents'][$key]['data'] = base64_decode($content['data']);
					$decoded_package['contents'][$key]['format'] = 'decoded';
				}
			}
		}
		
		return $decoded_package;
	}

	/**
	 * Parse HTML content and extract individual text elements
	 *
	 * @param string $html The HTML content
	 * @return array Array of text elements with their HTML
	 */
	private function parse_html_content($html) {
		$elements = array();
		
		if (empty($html)) {
			return $elements;
		}
		
		// Use DOMDocument to parse HTML
		$dom = new DOMDocument();
		// Suppress warnings for malformed HTML
		libxml_use_internal_errors(true);
		
		// Wrap in a container div to handle multiple root elements
		// Prepend XML encoding declaration for proper UTF-8 handling (replaces deprecated mb_convert_encoding)
		$wrapped_html = '<?xml encoding="UTF-8">' . '<div>' . $html . '</div>';
		@$dom->loadHTML($wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		
		// Get the container div
		$container = $dom->getElementsByTagName('div')->item(0);
		if (!$container) {
			return $elements;
		}
		
		// Define semantic block-level elements that should be extracted
		$block_elements = array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'li', 'blockquote', 'pre');
		
		// Get all block-level elements that contain text
		$xpath = new DOMXPath($dom);
		$block_nodes = $xpath->query('.//*[not(self::script) and not(self::style) and not(self::noscript) and not(self::img) and not(self::svg) and normalize-space(text())]', $container);
		
		$processed_nodes = array();
		
		foreach ($block_nodes as $node) {
			$tag_name = $node->nodeName;
			
			// Skip if already processed as part of a parent
			if (in_array($node, $processed_nodes, true)) {
				continue;
			}
			
			// Get the full text content of this element
			$text = trim($node->textContent);
			
			// Skip if empty or only whitespace
			if (empty($text)) {
				continue;
			}
			
			// Check if this is a block-level element or if it's a semantic container
			$is_block_element = in_array($tag_name, $block_elements);
			
			// Check if this element has block-level children
			$has_block_children = false;
			foreach ($node->childNodes as $child) {
				if ($child->nodeType === XML_ELEMENT_NODE && in_array($child->nodeName, $block_elements)) {
					$has_block_children = true;
					break;
				}
			}
			
			// If it's a block element and doesn't have block children, extract it
			// OR if it's an inline element (like strong, em, span) but is a direct child of a block element
			if ($is_block_element && !$has_block_children) {
				// Mark all child nodes as processed
				foreach ($node->getElementsByTagName('*') as $child) {
					$processed_nodes[] = $child;
				}
				
				// Get the HTML for this element
				$element_html = $dom->saveHTML($node);
				
				// Determine element type for better naming
				$element_type = 'content';
				if (in_array($tag_name, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'))) {
					$element_type = 'heading';
				} elseif ($tag_name === 'p') {
					$element_type = 'paragraph';
				} elseif ($tag_name === 'div') {
					$element_type = 'div';
				} elseif ($tag_name === 'span') {
					$element_type = 'span';
				}
				
				$elements[] = array(
					'text' => $text,
					'html' => $element_html,
					'type' => $element_type,
					'tag' => $tag_name
				);
			}
		}
		
		return $elements;
	}

	/**
	 * Normalize HTML string for comparison (remove extra whitespace, normalize entities)
	 *
	 * @param string $html The HTML string to normalize
	 * @return string Normalized HTML string
	 */
	private function normalize_html_string( $html ) {
		// Remove extra whitespace between tags
		$html = preg_replace( '/>\s+</', '><', $html );
		// Normalize whitespace within text
		$html = preg_replace( '/\s+/u', ' ', $html );
		// Trim
		$html = trim( $html );
		return $html;
	}

	/**
	 * Find position of HTML string in content, handling variations
	 *
	 * @param string $content The content to search in
	 * @param string $search The HTML string to find
	 * @return int|false Position or false if not found
	 */
	private function find_html_position( $content, $search ) {
		// Try exact match first
		$pos = mb_strpos( $content, $search );
		if ( $pos !== false ) {
			return $pos;
		}
		
		// Try case-insensitive match
		$pos = mb_stripos( $content, $search );
		if ( $pos !== false ) {
			return $pos;
		}
		
		return false;
	}

	/**
	 * Replace text content in HTML while preserving structure
	 *
	 * @param string $html The HTML content
	 * @param string $search_text The plain text to search for
	 * @param string $replace_html The HTML replacement
	 * @return string|false The replaced HTML or false on failure
	 */
	private function replace_text_in_html( $html, $search_text, $replace_html ) {
		if ( empty( $html ) || empty( $search_text ) ) {
			return false;
		}

		// Normalize whitespace in search text
		$search_text_normalized = preg_replace( '/\s+/u', ' ', trim( $search_text ) );
		$search_text_normalized = trim( $search_text_normalized );
		
		if ( empty( $search_text_normalized ) || mb_strlen( $search_text_normalized ) < 3 ) {
			return false;
		}
		
		// Extract plain text from HTML for comparison
		$html_text = wp_strip_all_tags( $html );
		$html_text_normalized = preg_replace( '/\s+/u', ' ', trim( $html_text ) );
		
		// Check if search text exists (case-insensitive)
		$search_lower = mb_strtolower( $search_text_normalized );
		$html_lower = mb_strtolower( $html_text_normalized );
		
		if ( mb_strpos( $html_lower, $search_lower ) === false ) {
			return false;
		}
		
		// Split search text into words
		$search_words = preg_split( '/\s+/u', $search_text_normalized, -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $search_words ) || count( $search_words ) < 2 ) {
			// For single word or short text, use simple approach
			$escaped = preg_quote( $search_text_normalized, '/' );
			$pattern = '/' . $escaped . '/iu';
			if ( preg_match( $pattern, $html_text_normalized, $matches, PREG_OFFSET_CAPTURE ) ) {
				// Find in original HTML by matching text nodes
				return preg_replace( $pattern, $replace_html, $html, 1 );
			}
			return false;
		}
		
		// For multi-word text, build pattern that allows HTML tags between words
		$pattern_parts = array();
		foreach ( $search_words as $word ) {
			$escaped_word = preg_quote( $word, '/' );
			$pattern_parts[] = $escaped_word;
		}
		
		// Pattern: match words with optional HTML/whitespace between them
		$pattern = '/' . implode( '(?:\s*<[^>]*>\s*|\s+)+', $pattern_parts ) . '/iu';
		
		// Find match in normalized text to get approximate position
		if ( preg_match( $pattern, $html_text_normalized, $text_matches, PREG_OFFSET_CAPTURE ) ) {
			// Now find and replace in actual HTML
			// Use a more flexible pattern that matches across HTML tags
			$flexible_pattern = '/' . implode( '(?:\s*<[^>]*>\s*|\s+)+', $pattern_parts ) . '/iu';
			$result = preg_replace( $flexible_pattern, $replace_html, $html, 1 );
			
			if ( $result !== $html ) {
				return $result;
			}
		}
		
		return false;
	}

	/**
	 * Extract translatable strings from WPML package
	 *
	 * @param array $package The WPML package array
	 * @return array Array of translatable strings with field information
	 */
	private function extract_translatable_strings($package) {
		$strings = array();
		
		if (!isset($package['contents']) || !is_array($package['contents'])) {
			return $strings;
		}
		
		foreach ($package['contents'] as $key => $content) {
			// Only process fields with translate => 1
			if (!is_array($content) || !isset($content['translate']) || $content['translate'] != 1) {
				continue;
			}
			
			// Skip metadata fields (name, type, etc.)
			if (strpos($key, '-name') !== false || strpos($key, '-type') !== false) {
				continue;
			}
			
			// Get the field data
			$data = isset($content['data']) ? $content['data'] : '';
			
			// Decode if base64
			if (isset($content['format']) && $content['format'] === 'base64') {
				$data = base64_decode($data);
			}
			
			// Skip empty data
			if (empty($data) || trim($data) === '') {
				continue;
			}
			
			// Try to get field name from corresponding -name field if it exists
			$field_name = $key;
			$name_key = $key . '-name';
			if (isset($package['contents'][$name_key]) && isset($package['contents'][$name_key]['data'])) {
				$name_data = $package['contents'][$name_key]['data'];
				if (isset($package['contents'][$name_key]['format']) && $package['contents'][$name_key]['format'] === 'base64') {
					$name_data = base64_decode($name_data);
				}
				if (!empty($name_data)) {
					$field_name = $name_data;
					// Format custom field names to be more readable
					if (strpos($key, 'field-') === 0) {
						// Remove leading underscore and format
						$field_name = ltrim($field_name, '_');
						$field_name = str_replace('_', ' ', $field_name);
						// Remove numeric suffix like -0, -1, etc.
						$field_name = preg_replace('/-\d+$/', '', $field_name);
						$field_name = ucwords($field_name);
					}
				}
			}
			
			// If no name field found or name is still the key, format the key as a readable name
			if ($field_name === $key) {
				// Handle field- prefix
				if (strpos($key, 'field-') === 0) {
					$field_name = str_replace('field-', '', $key);
					$field_name = ltrim($field_name, '_');
					$field_name = preg_replace('/-\d+$/', '', $field_name);
					$field_name = str_replace('_', ' ', $field_name);
					$field_name = ucwords($field_name);
				} else {
					$field_name = str_replace('_', ' ', $key);
					$field_name = ucwords($field_name);
				}
			}
			
			// Special handling for body field - parse HTML and break into individual elements
			if ($key === 'body') {
				error_log( print_r( $data, true ) );
				$parsed_elements = $this->parse_html_content($data);
				if (!empty($parsed_elements)) {
					// Add each element as a separate row
					foreach ($parsed_elements as $index => $element) {
						$text = trim($element['text']);
						if (!empty($text)) {
							$element_field_name = $field_name . ' - ' . ucfirst($element['type']) . ' ' . ($index + 1);
							if ($element['type'] === 'heading') {
								$element_field_name = $field_name . ' - ' . strtoupper($element['tag']) . ' ' . ($index + 1);
							}
							
							$strings[] = array(
								'field_name' => $element_field_name,
								'field_key' => $key . '_element_' . $index,
								'type' => $element['type'],
								'text' => $text,
								'html' => $element['html'],
								'format' => isset($content['format']) ? $content['format'] : 'text'
							);
						}
					}
				} else {
					// Fallback: if parsing fails, use the whole content
					$text = wp_strip_all_tags($data);
					$text = trim($text);
					if (!empty($text)) {
						$strings[] = array(
							'field_name' => $field_name,
							'field_key' => $key,
							'type' => 'content',
							'text' => $text,
							'html' => $data,
							'format' => isset($content['format']) ? $content['format'] : 'text'
						);
					}
				}
			} else {
				// For non-body fields, use the original logic
				// Get plain text version for display
				$text = wp_strip_all_tags($data);
				$text = trim($text);
				
				// Skip if no text content
				if (empty($text)) {
					continue;
				}
				
				$strings[] = array(
					'field_name' => $field_name,
					'field_key' => $key,
					'type' => 'content',
					'text' => $text,
					'html' => $data,
					'format' => isset($content['format']) ? $content['format'] : 'text'
				);
			}
		}
		
		return $strings;
	}


	/**
	 * Get post contents for translation.
	 */
	public function get_post_contents() {
		check_ajax_referer( WPML_AT_Helper::NONCE_KEY, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'msg' => 'No permission' ) );
		}

		$ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
		$ids = array_map( 'intval', $ids );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'msg' => 'No IDs provided' ) );
		}
		$post = get_post( $ids[0] );
		if ( ! $post ) {
			wp_send_json_error( array( 'msg' => 'No post found' ) );
		}
		$source_lang = WPML_AT_Helper::get_post_source_language( $ids[0], $post->post_type );
		$base = WP_PLUGIN_DIR . '/sitepress-multilingual-cms/classes';
		$file = $base . '/translation-jobs/class-wpml-element-translation-package.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		} else {
			wp_send_json_error( array( 'msg' => 'No file found' ) );
		}
		if ( ! class_exists( 'WPML_Element_Translation_Package' ) ) {
			wp_send_json_error( array( 'msg' => 'No class found' ) );
		}
		$builder = new WPML_Element_Translation_Package( null );
		$package = $builder->create_translation_package(
			$post,
			$source_lang,
			true // is original
		);
		// Extract translatable strings from package
		$translatable_strings = $this->extract_translatable_strings($package);
		
		$data = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			// Check if Elementor page.
			$elementor_enabled = get_post_meta( $id, '_elementor_edit_mode', true );

			if ( $elementor_enabled && 'builder' === $elementor_enabled && defined( 'ELEMENTOR_VERSION' ) ) {
				$elementor_data = get_post_meta( $id, '_elementor_data', true );

				if ( $elementor_data && '' !== $elementor_data ) {
					$elementor_data_array = array();

					if ( class_exists( '\Elementor\Plugin' ) && property_exists( '\Elementor\Plugin', 'instance' ) ) {
						$elementor_data_array = $this->decode_wpml_package( $package );
					} else {
						$elementor_data_array = $this->decode_wpml_package( $package );
					}

					$data[ $id ] = array(
						'id'              => $id,
						'title'           => get_the_title( $post ),
						'original_content' => $elementor_data_array,
						'content'         => $elementor_data_array,
						'editor_type'     => 'elementor',
						'strings'         => $translatable_strings,
						'package'         => $package,
					);
					continue;
				}
			}

			// Check for Gutenberg blocks.
			$content    = $post->post_content;
			$has_blocks = has_blocks( $content );

			if ( $has_blocks ) {
				$blocks = parse_blocks( $content );
				$data[ $id ] = array(
					'id'              => $id,
					'title'           => get_the_title( $post ),
					'original_content' => $blocks,
					'content'         => $blocks,
					'editor_type'     => 'block',
					'strings'         => $translatable_strings,
					'package'         => $package,
				);
			} else {
				$data[ $id ] = array(
					'id'              => $id,
					'title'           => get_the_title( $post ),
					'original_content' => $content,
					'content'         => $content,
					'editor_type'     => 'classic',
					'strings'         => $translatable_strings,
					'package'         => $package,
				);
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * Recursively replace text in Elementor data array structure
	 * This is safer than string replacement on JSON as it preserves structure
	 *
	 * @param mixed $data The data to process (array, string, or other)
	 * @param array $replacements Array of ['original' => 'translated'] pairs
	 * @return mixed The processed data with replacements applied
	 */
	private function recursively_replace_elementor_text( $data, $replacements ) {
		if ( is_array( $data ) ) {
			// Recursively process arrays
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->recursively_replace_elementor_text( $value, $replacements );
			}
			return $data;
		} elseif ( is_string( $data ) && ! empty( $data ) ) {
			// Process strings - try to match and replace
			$processed = $data;
			
			// Sort replacements by length (longest first) to avoid partial matches
			$sorted_replacements = $replacements;
			usort( $sorted_replacements, function( $a, $b ) {
				$len_a = strlen( $a['original'] ?? '' );
				$len_b = strlen( $b['original'] ?? '' );
				return $len_b <=> $len_a;
			});
			
			foreach ( $sorted_replacements as $replacement ) {
				$original = $replacement['original'] ?? '';
				$translated = $replacement['translated'] ?? '';
				
				if ( empty( $original ) || empty( $translated ) ) {
					continue;
				}
				
				// Try exact match first
				if ( strpos( $processed, $original ) !== false ) {
					$processed = str_replace( $original, $translated, $processed );
					continue; // Move to next replacement
				}
				
				// Try normalized match
				$normalized_original = $this->normalize_html_string( $original );
				$normalized_processed = $this->normalize_html_string( $processed );
				
				if ( strpos( $normalized_processed, $normalized_original ) !== false ) {
					// Find position in original string
					$pos = mb_stripos( $processed, $original );
					if ( $pos !== false ) {
						$processed = mb_substr( $processed, 0, $pos ) . $translated . mb_substr( $processed, $pos + mb_strlen( $original ) );
					}
					continue;
				}
				
				// Try text-only matching (strip HTML tags)
				$original_text = wp_strip_all_tags( $original );
				$original_text = trim( $original_text );
				
				if ( ! empty( $original_text ) && mb_strlen( $original_text ) > 3 ) {
					$processed_text = wp_strip_all_tags( $processed );
					
					if ( mb_stripos( $processed_text, $original_text ) !== false ) {
						// Try to replace in the full HTML string
						if ( strpos( $processed, $original ) !== false ) {
							$processed = str_replace( $original, $translated, $processed );
						} else {
							// Replace text content while preserving HTML structure
							$translated_text = wp_strip_all_tags( $translated );
							$processed = str_ireplace( $original_text, $translated_text, $processed );
						}
					}
				}
			}
			
			return $processed;
		}
		
		// Return other types as-is (numbers, booleans, null, etc.)
		return $data;
	}

	public function save_translation() {

		check_ajax_referer( WPML_AT_Helper::NONCE_KEY, 'nonce' );
	
		$post_id     = absint( $_POST['post_id'] ?? 0 );
		$target_lang = sanitize_text_field( $_POST['target_lang'] ?? '' );
		$rows        = (array) ( $_POST['translated_strings'] ?? [] );
	
		if ( ! $post_id || ! $target_lang || empty( $rows ) ) {
			wp_send_json_error([ 'msg' => 'Missing data' ]);
		}
	
		$fields = [];
	
		foreach ( $rows as $row ) {
			$key       = sanitize_text_field( $row['field_key'] ?? '' );
			$val       = wp_kses_post( wp_unslash( $row['translated'] ?? '' ) );
			$original  = wp_kses_post( wp_unslash( $row['original'] ?? '' ) );
			$translate = isset( $row['translate'] ) ? absint( $row['translate'] ) : 1;
	
			if ( ! $key || $val === '' ) {
				continue;
			}
	
			$fields[ $key ] = [
				'value'     => $val,
				'format'    => 'html',
				'original'  => $original,
				'translate' => $translate,
			];
		}
	
		if ( empty( $fields ) ) {
			wp_send_json_error([ 'msg' => 'No translated fields.' ]);
		}
	
		// Detect editor
		$editor = 'classic';
		if ( get_post_meta( $post_id, '_elementor_edit_mode', true ) === 'builder' ) {
			$editor = 'elementor';
		} else {
			$content = get_post_field( 'post_content', $post_id );
			if ( has_blocks( $content ) ) {
				$editor = 'gutenberg';
			}
		}
	
		$post_type = get_post_type( $post_id );
		$post_data = get_post( $post_id, ARRAY_A );
	
		unset( $post_data['ID'] ); // remove ID to duplicate
		$post_data['post_status'] = 'publish';
	
		// Title + Excerpt
		if ( isset( $fields['title'] ) && $fields['title']['translate'] === 1 ) {
			$post_data['post_title'] = $fields['title']['value'];
			// Generate slug from translated title
			$translated_slug = sanitize_title( $fields['title']['value'] );
			if ( ! empty( $translated_slug ) ) {
				$post_data['post_name'] = $translated_slug;
			}
		}
		if ( isset( $fields['excerpt'] ) && $fields['excerpt']['translate'] === 1 ) {
			$post_data['post_excerpt'] = $fields['excerpt']['value'];
		}

		// Translate post content
		$post_data['post_content'] = get_post_field( 'post_content', $post_id );
	
		if ( $editor === 'classic' || $editor === 'gutenberg' ) {
			// Sort fields by length (longest first) to avoid partial replacements
			$sorted_fields = $fields;
			usort( $sorted_fields, function( $a, $b ) {
				$len_a = strlen( $a['original'] ?? '' );
				$len_b = strlen( $b['original'] ?? '' );
				return $len_b <=> $len_a; // Descending order
			});
			
			foreach ( $sorted_fields as $key => $data ) {
				if ( $data['translate'] === 1 && ! empty( $data['original'] ) ) {
					$original = $data['original'];
					$translated = $data['value'];
					
					// Normalize HTML for better matching (remove extra whitespace, normalize entities)
					$normalized_original = $this->normalize_html_string( $original );
					$normalized_content = $this->normalize_html_string( $post_data['post_content'] );
					
					// Try exact match first (normalized)
					if ( strpos( $normalized_content, $normalized_original ) !== false ) {
						// Find position in original content
						$pos = $this->find_html_position( $post_data['post_content'], $original );
						if ( $pos !== false ) {
							$post_data['post_content'] = substr_replace( $post_data['post_content'], $translated, $pos, strlen( $original ) );
						} else {
							// Fallback to simple replace
							$post_data['post_content'] = str_replace( $original, $translated, $post_data['post_content'] );
						}
					} else {
						// Try matching by text content
						$original_text = wp_strip_all_tags( $original );
						$original_text = trim( $original_text );
						
						if ( ! empty( $original_text ) && mb_strlen( $original_text ) > 3 ) {
							$replaced = $this->replace_text_in_html( $post_data['post_content'], $original_text, $translated );
							if ( $replaced !== false ) {
								$post_data['post_content'] = $replaced;
							}
						}
					}
				}
			}
		}
	
		// Insert translated post
		$translated_post_id = wp_insert_post( $post_data );
	
		if ( is_wp_error( $translated_post_id ) ) {
			wp_send_json_error([ 'msg' => 'Failed to create translated post.' ]);
		}
	
		// Duplicate meta
		$meta = get_post_meta( $post_id );
		foreach ( $meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, ['_edit_lock', '_edit_last'] ) ) continue;
	
			foreach ( $meta_values as $meta_value ) {
				update_post_meta( $translated_post_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}
	
		// Elementor JSON replacement - FIXED
		if ( $editor === 'elementor' ) {

			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
			$json = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {

				// Prepare replacements
				$replacements = [];

				foreach ( $fields as $data ) {
					if ( $data['translate'] === 1 && ! empty( $data['original'] ) ) {
						$replacements[] = [
							'original'   => $data['original'],
							'translated' => $data['value'],
						];
					}
				}

				if ( ! empty( $replacements ) ) {

					// Replace text safely
					$translated_json = $this->recursively_replace_elementor_text( $json, $replacements );

					if ( is_array( $translated_json ) ) {

						/**
						 * ✅ REGISTER + TRANSLATE STRINGS (IMPORTANT)
						 */
						array_walk_recursive( $translated_json, function ( &$value ) {
							if ( is_string( $value ) && trim( $value ) !== '' ) {

								do_action(
									'wpml_register_single_string',
									'elementor',
									md5( $value ),
									$value
								);

								$value = apply_filters(
									'wpml_translate_single_string',
									$value,
									'elementor',
									md5( $value )
								);
							}
						});

						/**
						 * ✅ SAVE AS JSON STRING (CRITICAL FIX)
						 */
						update_post_meta(
							$translated_post_id,
							'_elementor_data',
							wp_slash( wp_json_encode( $translated_json ) )
						);

						/**
						 * ✅ CLEAR ELEMENTOR CACHE
						 */
						if ( class_exists( '\Elementor\Plugin' ) ) {
							\Elementor\Plugin::$instance->files_manager->clear_cache();
						}

						/**
						 * ✅ FORCE WPML SYNC
						 */
						do_action( 'wpml_sync_post_translations', $translated_post_id );
					}
				}
			}
		}

		// WPML language linking
		do_action( 'wpml_set_element_language_details', [
			'element_id'           => $translated_post_id,
			'element_type'         => 'post_' . $post_type,
			'trid'                 => apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $post_type ),
			'language_code'        => $target_lang,
			'source_language_code' => apply_filters( 'wpml_post_language_details', null, $post_id )['language_code']
		]);
		
		
		wp_send_json_success([
			'msg'            => 'Translated post created successfully.',
			'translated_id'  => $translated_post_id,
			'editor'         => $editor,
			'translated'     => array_keys( array_filter( $fields, fn($f) => $f['translate'] === 1 ) ),
			'copied'         => array_keys( array_filter( $fields, fn($f) => $f['translate'] === 0 ) ),
		]);
	}	

	/**
	 * Get pending languages (languages without existing translations) for selected posts.
	 */
	public function get_pending_languages() {
		check_ajax_referer( WPML_AT_Helper::NONCE_KEY, 'nonce' );

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

}

