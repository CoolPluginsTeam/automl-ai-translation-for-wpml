<?php
/**
 * Admin functionality for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class WPML_AT_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'add_translate_button' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_translate_button' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'add_row_actions_for_custom_post_types' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$is_translation_dashboard = ! empty( $_GET['page'] ) && strpos( $_GET['page'], 'tm/menu/main.php' ) !== false;
		$is_post_list              = strpos( $hook, 'edit.php' ) !== false;

		if ( ! $is_translation_dashboard && ! $is_post_list ) {
			return;
		}

		wp_enqueue_script(
			'cp-wpml-auto-translate-admin',
			WPML_AT_PLUGIN_URL . 'assets/cp-wpml-auto-translate-admin.js',
			array( 'jquery' ),
			WPML_AT_VERSION,
			true
		);

		$languages = WPML_AT_Helper::get_wpml_languages();

		wp_localize_script(
			'cp-wpml-auto-translate-admin',
			'CP_WPML_AUTO_TRANSLATE',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( WPML_AT_Helper::NONCE_KEY ),
				'languages' => $languages,
			)
		);
	}

	/**
	 * Add translate button to row actions for translatable post types.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post object.
	 * @return array Modified row actions.
	 */
	public function add_translate_button( $actions, $post ) {
		global $sitepress;

		// Skip for revisions or autosaves.
		if ( 'revision' === $post->post_type ) {
			return $actions;
		}

		// Check if WPML is active.
		if ( ! $sitepress ) {
			return $actions;
		}

		// Check if this post type is translatable in WPML.
		$translatable_types = $sitepress->get_translatable_documents();
		if ( ! isset( $translatable_types[ $post->post_type ] ) ) {
			return $actions;
		}

		// Add the Translate button.
		$actions['cool_translate'] = sprintf(
			'<a href="#" class="cp-wpml-row-translate-btn" data-post-id="%d" style="color:#21759b;font-weight:600;">%s</a>',
			esc_attr( $post->ID ),
			esc_html__( 'Translate', 'wpml-auto-translate-addon' )
		);

		return $actions;
	}

	/**
	 * Dynamically add row actions filter for all translatable post types.
	 */
	public function add_row_actions_for_custom_post_types() {
		global $sitepress;

		if ( ! $sitepress ) {
			return;
		}

		$translatable_types = $sitepress->get_translatable_documents();

		foreach ( array_keys( $translatable_types ) as $post_type ) {
			// Skip posts and pages as they're already handled.
			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				continue;
			}

			// Add filter for custom post type row actions.
			add_filter( $post_type . '_row_actions', array( $this, 'add_translate_button' ), 10, 2 );
		}
	}
}

