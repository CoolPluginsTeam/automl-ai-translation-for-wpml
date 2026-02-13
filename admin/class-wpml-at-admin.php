<?php

/**
 * Admin functionality for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin class.
 */
class WPML_AT_Admin
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_filter('post_row_actions', array($this, 'add_translate_button'), 10, 2);
		add_filter('page_row_actions', array($this, 'add_translate_button'), 10, 2);
		add_action('admin_init', array($this, 'add_row_actions_for_custom_post_types'));
		add_action('current_screen', array($this, 'string_translation_bulk_button'));
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets($hook)
	{
		// Sanitize and validate page parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic, not processing form data.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		$is_translation_dashboard = ! empty($page) && strpos($page, 'tm/menu/main.php') !== false;
		$is_string_translation     = ! empty($page) && strpos($page, 'wpml-string-translation/menu/string-translation.php') !== false;
		$is_post_list              = strpos($hook, 'edit.php') !== false;
		$is_post_edit              = strpos($hook, 'post.php') !== false || strpos($hook, 'post-new.php') !== false;
        $available_ai_services = array();

		if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
			$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
			$provider_ids = $registry->getRegisteredProviderIds();
		
			foreach ( $provider_ids as $provider_id ) {
				if ( $registry->isProviderConfigured( $provider_id ) ) {
					// e.g. 'google', 'openai', 'anthropic', etc.
					$available_ai_services[] = $provider_id;
				}
			}
		}
		// Enqueue translation dashboard/post list scripts.
		if ($is_translation_dashboard || $is_post_list || $is_string_translation) {

			$languages = WPML_AT_Helper::get_wpml_languages();
			$default_language = WPML_AT_Helper::get_default_language();
			$lang_object = array();

			foreach ($languages as $lang) {
				$lang_object[$lang['code']] = array('name' => $lang['name'], 'flag' => $lang['flag_url']);
			}
			wp_localize_script(
				'cp-wpml-auto-translate-admin',
				'CP_WPML_AUTO_TRANSLATE',
				array(
					'ajax'      => esc_url(admin_url('admin-ajax.php')),
					'nonce'     => wp_create_nonce('cp_wpml_auto_translate_nonce'),
					'languages' => $languages,
					'admin_url' => esc_url(admin_url()),
					'i18n'      => array(
						'errorPageId'      => esc_html__('Could not detect page ID for this row.', 'automl-ai-translation-for-wpml'),
						'errorNoSelection' => esc_html__('Please select at least one post to translate.', 'automl-ai-translation-for-wpml'),
						'errorNoLanguage'  => esc_html__('Please select a target language.', 'automl-ai-translation-for-wpml'),
						'errorInvalidData' => esc_html__('Invalid post ID or language.', 'automl-ai-translation-for-wpml'),
						'errorNoStrings'   => esc_html__('No translation strings found.', 'automl-ai-translation-for-wpml'),
						'errorAjax'        => esc_html__('AJAX error while loading content.', 'automl-ai-translation-for-wpml'),
						'errorAjaxSave'    => esc_html__('AJAX error while saving.', 'automl-ai-translation-for-wpml'),
						'errorUnknown'     => esc_html__('Unknown error occurred.', 'automl-ai-translation-for-wpml'),
					),
				)
			);

			// Enqueue bulk translate build files on string translation page.
			if ($is_string_translation) {

				$asset_file = include WPML_AT_PLUGIN_DIR . 'assets/bulk-string-translate/index.asset.php';

				wp_enqueue_script(
					'wpml-at-bulk-translate',
					WPML_AT_PLUGIN_URL . 'assets/bulk-string-translate/index.js',
					$asset_file['dependencies'],
					$asset_file['version'],
					true
				);

				wp_enqueue_style(
					'wpml-at-bulk-translate',
					WPML_AT_PLUGIN_URL . 'assets/bulk-string-translate/index.css',
					array(),
					$asset_file['version']
				);

				// Localize script with necessary data for string translation
				wp_localize_script(
					'wpml-at-bulk-translate',
					'automl_wpml_bulk_translate_object',
					array(
						'taxonomy_page'          => '',
						'languageObject'         => $lang_object,
						'ajax'                   => esc_url( admin_url( 'admin-ajax.php' ) ),
						'nonce'                  => wp_create_nonce( 'cp_wpml_auto_translate_nonce' ),
						'default_language_slug'  => $default_language,
						'bulkTranslateRouteUrl' => get_rest_url(null, 'automl-bulk-translate'),
						'bulkTranslatePrivateKey' => wp_create_nonce('automl_wpml_bulk_translate_entries_nonce'),
						'automl_wpml_url'           => esc_url(WPML_AT_PLUGIN_URL),
						'AIServices' => $available_ai_services,
						'admin_url' => admin_url(),
						'ai_translate_route_url' => get_rest_url(null, 'automl-bulk-translate'),
						'ai_translate_route_nonce' => wp_create_nonce('wp_rest'),
						'ai_translate_nonce' => wp_create_nonce('automl_wpml_ai_translate_nonce'),
						'get_glossary_validate' => wp_create_nonce('automl_wpml_get_glossary_private'),
					)
				);
			}
		}
	}

	/**
	 * Add translate button to row actions for translatable post types.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post object.
	 * @return array Modified row actions.
	 */
	public function add_translate_button($actions, $post)
	{
		global $sitepress;

		// Skip for revisions or autosaves.
		if (! $post || 'revision' === $post->post_type) {
			return $actions;
		}

		// Check if WPML is active.
		if (! $sitepress) {
			return $actions;
		}

		// Check if this post type is translatable in WPML.
		$translatable_types = $sitepress->get_translatable_documents();
		if (! isset($translatable_types[$post->post_type])) {
			return $actions;
		}

		// Check user capabilities.
		if (! current_user_can('edit_post', $post->ID)) {
			return $actions;
		}

		// Add the Translate button.
		$actions['cool_translate'] = sprintf(
			'<a href="#" class="cp-wpml-row-translate-btn" data-post-id="%d" style="color:#21759b;font-weight:600;">%s</a>',
			absint($post->ID),
			esc_html__('Translate', 'automl-ai-translation-for-wpml')
		);

		return $actions;
	}

	/**
	 * Dynamically add row actions filter for all translatable post types.
	 */
	public function add_row_actions_for_custom_post_types()
	{
		global $sitepress;

		if (! $sitepress) {
			return;
		}

		$translatable_types = $sitepress->get_translatable_documents();

		foreach (array_keys($translatable_types) as $post_type) {
			// Skip posts and pages as they're already handled.
			if (in_array($post_type, array('post', 'page'), true)) {
				continue;
			}

			// Add filter for custom post type row actions.
			add_filter($post_type . '_row_actions', array($this, 'add_translate_button'), 10, 2);
		}
	}

	/**
	 * Add bulk translate button to WPML String Translation page.
	 *
	 * @param WP_Screen $screen Current screen object.
	 * @return void
	 */
	public function string_translation_bulk_button($screen)
	{
		if (! $screen) {
			return;
		}

		// Check if we're on the string translation page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if (empty($page) || strpos($page, 'wpml-string-translation/menu/string-translation.php') === false) {
			return;
		}

		// Add button and container to the page.
		add_action('admin_notices', array($this, 'render_string_bulk_translate_button'));
		add_action('admin_footer', array($this, 'render_bulk_translate_container'));
	}

	/**
	 * Render bulk translate button on string translation page.
	 *
	 * @return void
	 */
	public function render_string_bulk_translate_button()
	{
?>
		<button class="button button-primary automl-wpml-bulk-translate-btn" style="display: none;">
			<?php esc_html_e('Bulk Translate', 'automl-ai-translation-for-wpml'); ?>
		</button>
	<?php
	}

	/**
	 * Render bulk translate container div.
	 *
	 * @return void
	 */
	public function render_bulk_translate_container()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if (empty($page) || strpos($page, 'wpml-string-translation/menu/string-translation.php') === false) {
			return;
		}
	?>
		<div id="automl-wpml-bulk-translate-wrapper"></div>
<?php
	}
}
