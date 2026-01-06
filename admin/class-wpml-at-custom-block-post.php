<?php
/**
 * Custom Block Post Type for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Block Post Type class.
 */
class WPML_AT_Custom_Block_Post {

	/**
	 * Singleton instance.
	 *
	 * @var WPML_AT_Custom_Block_Post
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPML_AT_Custom_Block_Post
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wpml_at_get_custom_blocks_content', array( $this, 'ajax_get_custom_blocks_content' ) );
		add_action( 'wp_ajax_wpml_at_update_custom_blocks_content', array( $this, 'ajax_update_custom_blocks_content' ) );
	}


	/**
	 * Enqueue scripts for custom block post type editor.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		$current_screen = get_current_screen();
		if ( 'wpml_at_add_blocks' === $current_screen->post_type && is_object( $current_screen ) && 'post.php' === $hook && $current_screen->is_block_editor ) {
			wp_enqueue_script(
				'wpml-at-add-new-block',
				WPML_AT_PLUGIN_URL . 'assets/js/wpml-at-add-new-block.js',
				array( 'jquery', 'wp-data', 'wp-element' ),
				WPML_AT_VERSION,
				true
			);
			wp_enqueue_script(
				'wpml-at-update-custom-blocks',
				WPML_AT_PLUGIN_URL . 'assets/js/wpml-at-update-custom-blocks.js',
				array( 'jquery' ),
				WPML_AT_VERSION,
				true
			);
			wp_enqueue_style(
				'wpml-at-custom-blocks',
				WPML_AT_PLUGIN_URL . 'assets/css/wpml-at-custom-blocks.css',
				array(),
				WPML_AT_VERSION,
				'all'
			);

			wp_localize_script(
				'wpml-at-add-new-block',
				'wpmlAtAddBlockVars',
				array(
					'demo_page_url' => esc_url( 'https://coolplugins.net/product/automatic-translations-for-polylang/' ),
				)
			);

			// Get available block rules for console logging (matching Polylang behavior)
			$block_rules = WPML_Engine::get_block_rules();
			$available_blocks = array_keys( $block_rules );

			wp_localize_script(
				'wpml-at-update-custom-blocks',
				'wpmlAtBlockUpdateObject',
				array(
					'ajax_url'            => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'          => wp_create_nonce( 'wpml_at_block_update_nonce' ),
					'action_get_content' => 'wpml_at_get_custom_blocks_content',
					'action_update_content' => 'wpml_at_update_custom_blocks_content',
					'available_blocks'   => $available_blocks, // For console logging only
				)
			);
		}
	}

	/**
	 * Handle post save to extract custom block data.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $post->post_type ) && 'wpml_at_add_blocks' === $post->post_type ) {
			// Check if content contains the marker text
			if ( strpos( $post->post_content, 'Make This Content Available for Translation' ) !== false ) {
				update_option( 'wpml_at_custom_block_data', $post->post_content );
			} else {
				delete_option( 'wpml_at_custom_block_data' );
			}
		}
	}

	/**
	 * Register custom post type.
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => _x( 'WPML Auto Translate Blocks', 'post type general name', 'wpml-auto-translate-addon' ),
			'singular_name'      => _x( 'WPML Auto Translate Block', 'post type singular name', 'wpml-auto-translate-addon' ),
			'menu_name'          => _x( 'Auto Translate Blocks', 'admin menu', 'wpml-auto-translate-addon' ),
			'name_admin_bar'     => _x( 'Auto Translate Block', 'add new on admin bar', 'wpml-auto-translate-addon' ),
			'add_new'            => _x( 'Add New', 'Auto Translate Block', 'wpml-auto-translate-addon' ),
			'add_new_item'       => __( 'Add New Auto Translate Block', 'wpml-auto-translate-addon' ),
			'new_item'           => __( 'New Auto Translate Block', 'wpml-auto-translate-addon' ),
			'edit_item'          => __( 'Edit Auto Translate Block', 'wpml-auto-translate-addon' ),
			'view_item'          => __( 'View Auto Translate Block', 'wpml-auto-translate-addon' ),
			'all_items'          => __( 'All Auto Translate Blocks', 'wpml-auto-translate-addon' ),
			'search_items'       => __( 'Search Auto Translate Blocks', 'wpml-auto-translate-addon' ),
			'not_found'          => __( 'No Auto Translate Blocks found.', 'wpml-auto-translate-addon' ),
			'not_found_in_trash' => __( 'No Auto Translate Blocks found in Trash.', 'wpml-auto-translate-addon' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Hidden from admin menu
			'show_in_nav_menus'  => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'wpml-auto-translate-blocks' ),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => true,
			'show_in_rest'       => true,
			'supports'           => array( 'editor' ),
			'capabilities'       => array(
				'create_post'  => false,
				'create_posts' => false,
				'delete_post'  => false,
				'edit_post'    => 'edit_pages',
				'delete_posts' => false,
				'edit_posts'   => 'edit_pages',
				'edit_pages'   => 'edit_pages',
				'edit_page'    => 'edit_pages',
			),
		);

		register_post_type( 'wpml_at_add_blocks', $args );
	}

	/**
	 * Get or create the custom block post ID.
	 *
	 * @return int Post ID.
	 */
	public static function get_custom_block_post_id() {
		$query = new WP_Query(
			array(
				'post_type'      => 'wpml_at_add_blocks',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'post_status'    => 'any',
			)
		);

		$existing_post = $query->posts ? $query->posts[0] : null;

		if ( ! $existing_post ) {
			$post_title = esc_html__( 'Add More Gutenberg Blocks', 'wpml-auto-translate-addon' );
			$post_id = wp_insert_post(
				array(
					'post_title'   => $post_title,
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'wpml_at_add_blocks',
				)
			);
			return $post_id;
		} else {
			return $existing_post->ID;
		}
	}

	/**
	 * AJAX handler to get custom blocks content.
	 */
	public function ajax_get_custom_blocks_content() {
		if ( ! check_ajax_referer( 'wpml_at_block_update_nonce', 'wpml_at_nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token sent.', 'wpml-auto-translate-addon' ) );
			wp_die( '0', 400 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wpml-auto-translate-addon' ), 403 );
			wp_die( '0', 403 );
		}

		$custom_content = get_option( 'wpml_at_custom_block_data', false ) ? get_option( 'wpml_at_custom_block_data', false ) : false;

		if ( $custom_content && is_string( $custom_content ) && ! empty( trim( $custom_content ) ) ) {
			return wp_send_json_success( array( 'block_data' => $custom_content ) );
		} else {
			return wp_send_json_success( array( 'message' => __( 'No custom blocks found.', 'wpml-auto-translate-addon' ) ) );
		}
		exit();
	}

	/**
	 * AJAX handler to update custom blocks content.
	 */
	public function ajax_update_custom_blocks_content() {
		if ( ! check_ajax_referer( 'wpml_at_block_update_nonce', 'wpml_at_nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token sent.', 'wpml-auto-translate-addon' ) );
			wp_die( '0', 400 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wpml-auto-translate-addon' ), 403 );
			wp_die( '0', 403 );
		}

		// Sanitize JSON input before decoding.
		$json = isset( $_POST['save_block_data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['save_block_data'] ) ) : false;
		
		if ( false === $json || empty( $json ) ) {
			wp_send_json_error( __( 'No block data provided.', 'wpml-auto-translate-addon' ) );
			wp_die( '0', 400 );
		}
		
		$updated_blocks_data = json_decode( $json, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( __( 'Invalid JSON', 'wpml-auto-translate-addon' ) );
			wp_die( '0', 400 );
		}

		if ( $updated_blocks_data ) {
			$block_rules = WPML_Engine::get_block_rules();
			$custom_block_translation = array();

			// Get previous translation data if exists
			$previous_translate_data = get_option( 'wpml_at_custom_block_translation', false );
			if ( $previous_translate_data && ! empty( $previous_translate_data ) && is_array( $previous_translate_data ) ) {
				$custom_block_translation = $previous_translate_data;
			}

			// Process each block in the updated data
			foreach ( $updated_blocks_data as $key => $block_data ) {
				$existing_rules = isset( $block_rules[ $key ] ) ? $block_rules[ $key ] : null;
				$this->verify_block_data( array( $key ), $block_data, $existing_rules, $custom_block_translation );
			}

			// Save the custom block translation data
			if ( count( $custom_block_translation ) > 0 ) {
				update_option( 'wpml_at_custom_block_translation', $custom_block_translation );
			}

			// Delete the temporary custom block data
			delete_option( 'wpml_at_custom_block_data' );
		}

		return wp_send_json_success( array( 'message' => __( 'WPML Auto Translate: Custom Blocks data updated successfully', 'wpml-auto-translate-addon' ) ) );
	}

	/**
	 * Verify and process block data recursively.
	 *
	 * @param array $id_keys Array of keys representing the path to the attribute.
	 * @param mixed $value The value to verify.
	 * @param mixed $block_rules Existing block rules for this block.
	 * @param array $custom_block_translation Reference to the custom block translation array.
	 */
	private function verify_block_data( $id_keys, $value, $block_rules, &$custom_block_translation ) {
		$block_rules = is_object( $block_rules ) ? json_decode( json_encode( $block_rules ), true ) : $block_rules;

		if ( ! isset( $block_rules ) ) {
			return $this->create_nested_attribute( $value, $id_keys, $custom_block_translation );
		}

		if ( is_array( $value ) && isset( $block_rules ) ) {
			foreach ( $value as $key => $item ) {
				if ( isset( $block_rules[ $key ] ) && is_array( $item ) ) {
					$this->verify_block_data( array_merge( $id_keys, array( $key ) ), $item, $block_rules[ $key ], $custom_block_translation );
					continue;
				} elseif ( ! isset( $block_rules[ $key ] ) && true === $item ) {
					$this->create_nested_attribute( true, array_merge( $id_keys, array( $key ) ), $custom_block_translation );
					continue;
				} elseif ( ! isset( $block_rules[ $key ] ) && is_array( $item ) ) {
					$this->create_nested_attribute( $item, array_merge( $id_keys, array( $key ) ), $custom_block_translation );
					continue;
				}
			}
		}
	}

	/**
	 * Create nested attribute structure.
	 *
	 * @param mixed $value The value to set.
	 * @param array $id_keys Array of keys representing the path.
	 * @param array $custom_block_translation Reference to the custom block translation array.
	 */
	private function create_nested_attribute( $value, $id_keys, &$custom_block_translation ) {
		$value = is_object( $value ) ? json_decode( json_encode( $value ), true ) : $value;

		$current_array = &$custom_block_translation;

		foreach ( $id_keys as $index => $id ) {
			if ( ! isset( $current_array[ $id ] ) ) {
				$current_array[ $id ] = array();
			}
			$current_array = &$current_array[ $id ];
		}
		$current_array = $value;
	}
}

