<?php

namespace AUTOML_WPML\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( Helper::class ) ) {
	return;
}

/**
 * Helper
 *
 * @package AUTOML_WPML\Helper
 */
class Helper {
	/**
	 * Bulk translation supported
	 *
	 * @param object $current_screen The current screen object.
	 * @return bool True if bulk translation should be rendered, false otherwise.
	 */
	public static function tranlastable_post_type( $current_screen ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		if ( ( isset( $current_screen->action ) && $current_screen->action === 'add' ) || ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'edit' ) ) {
			return false;
		}

		if ( ! isset( $current_screen->post_type ) || empty( $current_screen->post_type ) ) {
			return false;
		}

		if ( isset( $current_screen->post_type ) && $current_screen->post_type === 'attachment' ) {
			return false;
		}

		if ( ! in_array( $current_screen->post_type, array( 'page', 'post' ) ) ) {
			return false;
		}

		return true;
	}

	public static function supported_editors(): array {
		return array( 'Gutenberg', 'Elementor' );
	}
}
