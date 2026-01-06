<?php
/**
 * Supported Blocks admin page for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supported Blocks admin class.
 */
class WPML_AT_Supported_Blocks {

	/**
	 * Singleton instance.
	 *
	 * @var WPML_AT_Supported_Blocks
	 */
	private static $instance = null;

	/**
	 * Plugin category for blocks.
	 *
	 * @var array
	 */
	private $plugin_categories = array();

	/**
	 * Get singleton instance.
	 *
	 * @return WPML_AT_Supported_Blocks
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpml_at_save_block_support', array( $this, 'ajax_save_block_support' ) );
		add_action( 'wp_ajax_wpml_at_bulk_save_block_support', array( $this, 'ajax_bulk_save_block_support' ) );
		
		// Prevent front-end access
		add_action( 'template_redirect', array( $this, 'prevent_frontend_access' ) );
	}

	/**
	 * Prevent front-end access to admin page.
	 */
	public function prevent_frontend_access() {
		if ( ! is_admin() && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( strpos( $request_uri, 'wpml-auto-translate-supported-blocks' ) !== false ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wpml-auto-translate-supported-blocks' ) );
				exit;
			}
		}
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		// Use late priority to ensure WPML menus are registered first
		add_action( 'admin_menu', array( $this, 'add_menu_fallback' ), 999 );
		
		// Also try WPML hook if available
		add_action( 'wpml_admin_menu_register_item', array( $this, 'register_menu_item' ) );
	}

	/**
	 * Register menu item using WPML menu system.
	 *
	 * @param array $menu Menu item data.
	 */
	public function register_menu_item( $menu ) {
		// Only add if this is the Translation Management menu
		if ( isset( $menu['menu_slug'] ) && strpos( $menu['menu_slug'], 'translation-management' ) !== false ) {
			$parent_slug = $menu['menu_slug'];
			
			add_submenu_page(
				$parent_slug,
				__( 'Supported Blocks', 'wpml-auto-translate-addon' ),
				__( 'Supported Blocks', 'wpml-auto-translate-addon' ),
				'manage_options',
				'wpml-auto-translate-supported-blocks',
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Fallback menu addition.
	 */
	public function add_menu_fallback() {
		global $submenu;
		
		// Check if menu already exists
		if ( menu_page_url( 'wpml-auto-translate-supported-blocks', false ) ) {
			return;
		}
		
		// Try different possible parent slugs
		$possible_parents = array();
		
		// Add WPML_TM_FOLDER if defined
		if ( defined( 'WPML_TM_FOLDER' ) ) {
			$possible_parents[] = WPML_TM_FOLDER . '/menu/main.php';
		}
		
		// Add other possible slugs
		$possible_parents[] = 'wpml-translation-management';
		$possible_parents[] = 'tm/menu/main.php';
		
		// Check global submenu to find actual parent
		if ( ! empty( $submenu ) ) {
			foreach ( $submenu as $parent_slug => $items ) {
				// Look for Translation Management menu
				if ( strpos( $parent_slug, 'translation-management' ) !== false || 
					 strpos( $parent_slug, 'tm' ) !== false ||
					 ( is_array( $items ) && ! empty( $items ) ) ) {
					// Check if this looks like the TM menu by checking menu items
					foreach ( $items as $item ) {
						if ( isset( $item[2] ) && ( strpos( $item[2], 'main.php' ) !== false || strpos( $item[2], 'dashboard' ) !== false ) ) {
							$possible_parents[] = $parent_slug;
							break 2;
						}
					}
				}
			}
		}
		
		// Try to add submenu
		foreach ( $possible_parents as $parent ) {
			if ( isset( $submenu[ $parent ] ) || $parent === 'wpml-translation-management' ) {
				add_submenu_page(
					$parent,
					__( 'Supported Blocks', 'wpml-auto-translate-addon' ),
					__( 'Supported Blocks', 'wpml-auto-translate-addon' ),
					'manage_options',
					'wpml-auto-translate-supported-blocks',
					array( $this, 'render_page' )
				);
				return;
			}
		}
		
		// Last resort: Add as standalone menu (only if we're in admin)
		if ( is_admin() ) {
			add_menu_page(
				__( 'WPML Supported Blocks', 'wpml-auto-translate-addon' ),
				__( 'WPML Supported Blocks', 'wpml-auto-translate-addon' ),
				'manage_options',
				'wpml-auto-translate-supported-blocks',
				array( $this, 'render_page' ),
				'dashicons-translation',
				30
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Check by page parameter first (most reliable)
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic, not processing form data.
		if ( empty( $_GET['page'] ) || 'wpml-auto-translate-supported-blocks' !== $_GET['page'] ) {
			return;
		}

		// Enqueue DataTables CSS/JS (using CDN for simplicity, or you can include local files)
		// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- DataTables is a legitimate third-party library commonly loaded from CDN.
		wp_enqueue_style(
			'wpml-at-datatables-css',
			WPML_AT_PLUGIN_URL . 'assets/css/wpml-at-datatable.css',
			array(),
			WPML_AT_VERSION
		);

		// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- DataTables is a legitimate third-party library commonly loaded from CDN.
		wp_enqueue_script(
			'wpml-at-datatables-js',
			WPML_AT_PLUGIN_URL . 'assets/js/wpml-at-datatable.js',
			array( 'jquery' ),
			WPML_AT_VERSION,
			true
		);

		// Enqueue custom CSS
		wp_enqueue_style(
			'wpml-at-supported-blocks',
			WPML_AT_PLUGIN_URL . 'assets/css/wpml-at-supported-blocks.css',
			array(),
			WPML_AT_VERSION
		);

		// Enqueue custom JS
		wp_enqueue_script(
			'wpml-at-supported-blocks',
			WPML_AT_PLUGIN_URL . 'assets/js/wpml-at-supported-blocks.js',
			array( 'jquery', 'wpml-at-datatables-js' ),
			WPML_AT_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'wpml-at-supported-blocks',
			'wpmlAtSupportedBlocks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpml_at_supported_blocks_nonce' ),
				'i18n'    => array(
					'saving'     => __( 'Saving...', 'wpml-auto-translate-addon' ),
					'saved'      => __( 'Changes saved successfully!', 'wpml-auto-translate-addon' ),
					'error'      => __( 'Error saving changes. Please try again.', 'wpml-auto-translate-addon' ),
					'selectAll'  => __( 'Select All', 'wpml-auto-translate-addon' ),
					'deselectAll' => __( 'Deselect All', 'wpml-auto-translate-addon' ),
				),
			)
		);
	}

	/**
	 * Render the supported blocks page.
	 */
	public function render_page() {
		// Ensure we're in admin - redirect if accessed from frontend
		if ( ! is_admin() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpml-auto-translate-supported-blocks' ) );
			exit;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpml-auto-translate-addon' ) );
		}

		// Verify we're on the correct page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic, not processing form data.
		if ( empty( $_GET['page'] ) || 'wpml-auto-translate-supported-blocks' !== $_GET['page'] ) {
			wp_die( esc_html__( 'Invalid page access.', 'wpml-auto-translate-addon' ) );
		}

		?>
		<div class="wrap wpml-at-supported-blocks-wrapper">
			<h1><?php echo esc_html__( 'Supported Blocks Translation Settings', 'wpml-auto-translate-addon' ); ?></h1>
			<p class="description">
				<?php
				echo sprintf(
					/* translators: %s: Plugin name */
					esc_html__( 'Manage Gutenberg blocks to make them translation-ready with %s.', 'wpml-auto-translate-addon' ),
					'<strong>WPML Google Auto Translate Addon</strong>'
				);
				?>
			</p>

			<div class="wpml-at-filters">
				<div class="wpml-at-filter-group">
					<label for="wpml-at-blocks-category">
						<?php esc_html_e( 'Block Type Category:', 'wpml-auto-translate-addon' ); ?>
					</label>
					<select id="wpml-at-blocks-category" name="wpml_at_blocks_category">
						<option value="all"><?php esc_html_e( 'All', 'wpml-auto-translate-addon' ); ?></option>
						<option value="core"><?php esc_html_e( 'Core', 'wpml-auto-translate-addon' ); ?></option>
						<?php $this->render_block_categories(); ?>
					</select>
				</div>

				<div class="wpml-at-filter-group">
					<label for="wpml-at-blocks-filter">
						<?php esc_html_e( 'Show Blocks:', 'wpml-auto-translate-addon' ); ?>
					</label>
					<select id="wpml-at-blocks-filter" name="wpml_at_blocks_filter">
						<option value="all"><?php esc_html_e( 'All', 'wpml-auto-translate-addon' ); ?></option>
						<option value="supported"><?php esc_html_e( 'Supported Blocks', 'wpml-auto-translate-addon' ); ?></option>
						<option value="unsupported"><?php esc_html_e( 'Unsupported Blocks', 'wpml-auto-translate-addon' ); ?></option>
					</select>
				</div>
			</div>

			<div class="wpml-at-table-section">
				<div class="wpml-at-table-actions">
					<button type="button" id="wpml-at-save-changes" class="button button-primary">
						<?php esc_html_e( 'Save Changes', 'wpml-auto-translate-addon' ); ?>
					</button>
					<span id="wpml-at-save-message" class="wpml-at-save-message"></span>
				</div>
				<table id="wpml-at-blocks-table" class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sr.No', 'wpml-auto-translate-addon' ); ?></th>
							<th><?php esc_html_e( 'Block Name', 'wpml-auto-translate-addon' ); ?></th>
							<th><?php esc_html_e( 'Block Title', 'wpml-auto-translate-addon' ); ?></th>
							<th><?php esc_html_e( 'Enable Translation', 'wpml-auto-translate-addon' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wpml-auto-translate-addon' ); ?></th>
							<th><?php esc_html_e( 'Attributes', 'wpml-auto-translate-addon' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $this->render_blocks_table(); ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render block categories dropdown options.
	 */
	private function render_block_categories() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return;
		}

		$blocks_data = WP_Block_Type_Registry::get_instance()->get_all_registered();

		$filtered_blocks = array_filter( $blocks_data, function( $block ) {
			return ! in_array( $block->category, array( 'media', 'reusable' ), true );
		} );

		foreach ( $filtered_blocks as $block ) {
			$plugin_name = explode( '/', $block->name );
			$plugin_name = isset( $plugin_name[0] ) ? $plugin_name[0] : '';

			if ( empty( $plugin_name ) || $plugin_name === 'core' ) {
				continue;
			}

			$formatted_name = $this->format_plugin_name( $plugin_name );

			if ( in_array( $plugin_name, $this->plugin_categories, true ) ) {
				continue;
			}

			$this->plugin_categories[] = $plugin_name;
			printf(
				'<option value="%s">%s</option>',
				esc_attr( $plugin_name ),
				esc_html( $formatted_name )
			);
		}
	}

	/**
	 * Format plugin name for display.
	 *
	 * @param string $block_name Block name.
	 * @return string Formatted name.
	 */
	private function format_plugin_name( $block_name ) {
		$predefined_blocks = array(
			'ub'                => 'Ultimate Blocks',
			'uagb'              => 'Spectra',
			'themeisle-blocks' => 'Otter Blocks',
		);

		if ( array_key_exists( $block_name, $predefined_blocks ) ) {
			return $predefined_blocks[ $block_name ];
		}

		$formatted = str_replace( '-', ' ', $block_name );
		return ucwords( $formatted );
	}

	/**
	 * Render blocks table rows.
	 */
	private function render_blocks_table() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Block registry not available.', 'wpml-auto-translate-addon' ) . '</td></tr>';
			return;
		}

		$block_rules = WPML_Engine::get_block_rules();
		$custom_rules = $this->get_custom_block_rules();
		$supported_block_names = array_keys( $block_rules );
		$blocks_data = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$s_no = 1;

		foreach ( $blocks_data as $block ) {
			// Skip invalid blocks
			if ( ! is_object( $block ) || empty( $block->name ) ) {
				continue;
			}

			$block_name = isset( $block->name ) ? esc_html( $block->name ) : '';
			$block_title = isset( $block->title ) ? esc_html( $block->title ) : '';
			
			// Skip if block name is empty
			if ( empty( $block_name ) ) {
				continue;
			}
			
			// Check if block is enabled (either in default rules or custom rules)
			$is_enabled = in_array( $block_name, $supported_block_names, true );
			
			// Check custom rules (user can disable default blocks or enable new ones)
			if ( isset( $custom_rules[ $block_name ] ) ) {
				$is_enabled = (bool) $custom_rules[ $block_name ];
			}
			
			$status = $is_enabled ? 'Supported' : 'Unsupported';
			$status_class = $is_enabled ? 'supported' : 'unsupported';

			// Get block attributes from rules
			$attributes = '-';
			if ( $is_enabled && isset( $block_rules[ $block_name ]['attributes'] ) ) {
				$attrs = $block_rules[ $block_name ]['attributes'];
				$attr_names = $this->get_attribute_names( $attrs );
				$attributes = ! empty( $attr_names ) ? implode( ', ', $attr_names ) : '-';
			}

			// Get plugin name for filtering
			$plugin_name_parts = explode( '/', $block_name );
			$plugin_name = isset( $plugin_name_parts[0] ) ? $plugin_name_parts[0] : 'core';

			// Output row with exactly 6 columns
			echo '<tr data-block-name="' . esc_attr( strtolower( $block_name ) ) . '" data-block-status="' . esc_attr( strtolower( $status ) ) . '" data-plugin-category="' . esc_attr( $plugin_name ) . '">';
			
			// Column 1: Sr.No
			echo '<td>' . esc_html( $s_no++ ) . '</td>';
			
			// Column 2: Block Name
			echo '<td><code>' . esc_html( $block_name ) . '</code></td>';
			
			// Column 3: Block Title
			echo '<td>' . esc_html( $block_title ) . '</td>';
			
			// Column 4: Enable/Disable toggle
			echo '<td><label class="wpml-at-toggle"><input type="checkbox" class="wpml-at-block-toggle" value="' . esc_attr( $block_name ) . '" ' . checked( $is_enabled, true, false ) . '><span class="wpml-at-toggle-slider"></span></label></td>';
			
			// Column 5: Status
			echo '<td><span class="wpml-at-status wpml-at-status-' . esc_attr( $status_class ) . '">' . esc_html( $status ) . '</span></td>';
			
			// Column 6: Attributes and Modify link
			echo '<td>';
			echo esc_html( $attributes );
			if ( ! empty( $attributes ) && $attributes !== '-' ) {
				echo '<br>';
			}
			$modify_text = $is_enabled ? esc_html__( 'Edit', 'wpml-auto-translate-addon' ) : esc_html__( 'Add', 'wpml-auto-translate-addon' );
			$custom_post_id = WPML_AT_Custom_Block_Post::get_custom_block_post_id();
			$modify_link = admin_url( 'post.php?post=' . esc_attr( $custom_post_id ) . '&action=edit&wpml_at_new_block=' . esc_attr( $block_name ) );
			echo '<a href="' . esc_url( $modify_link ) . '" class="wpml-at-modify-link" target="_blank">' . esc_html( $modify_text ) . '</a>';
			echo '</td>';
			
			echo '</tr>';
		}
	}

	/**
	 * Get custom block rules from database.
	 *
	 * @return array Custom block rules.
	 */
	private function get_custom_block_rules() {
		$custom_rules = get_option( 'wpml_at_custom_block_rules', array() );
		return is_array( $custom_rules ) ? $custom_rules : array();
	}

	/**
	 * AJAX handler to save block support status.
	 */
	public function ajax_save_block_support() {
		check_ajax_referer( 'wpml_at_supported_blocks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'wpml-auto-translate-addon' ) ) );
		}

		$block_name = isset( $_POST['block_name'] ) ? sanitize_text_field( wp_unslash( $_POST['block_name'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;

		if ( empty( $block_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid block name', 'wpml-auto-translate-addon' ) ) );
		}

		$custom_rules = $this->get_custom_block_rules();
		$custom_rules[ $block_name ] = $enabled;
		
		update_option( 'wpml_at_custom_block_rules', $custom_rules );

		wp_send_json_success( array(
			'message' => __( 'Block support updated successfully', 'wpml-auto-translate-addon' ),
		) );
	}

	/**
	 * AJAX handler to bulk save block support status.
	 */
	public function ajax_bulk_save_block_support() {
		check_ajax_referer( 'wpml_at_supported_blocks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'wpml-auto-translate-addon' ) ) );
		}

		// Sanitize and validate blocks array input.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array elements are sanitized individually in the foreach loop below.
		$blocks = isset( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
		$blocks = is_array( $blocks ) ? $blocks : array();

		$custom_rules = $this->get_custom_block_rules();

		foreach ( $blocks as $block_name => $enabled ) {
			$block_name = sanitize_text_field( $block_name );
			$enabled    = (bool) $enabled;
			$custom_rules[ $block_name ] = $enabled;
		}

		update_option( 'wpml_at_custom_block_rules', $custom_rules );

		wp_send_json_success( array(
			'message' => __( 'Block support updated successfully', 'wpml-auto-translate-addon' ),
			'count'  => count( $blocks ),
		) );
	}

	/**
	 * Get attribute names from block rules recursively.
	 *
	 * @param array $attrs Attributes array.
	 * @return array Attribute names.
	 */
	private function get_attribute_names( $attrs ) {
		$names = array();

		foreach ( $attrs as $key => $value ) {
			if ( $value === true ) {
				$names[] = $key;
			} elseif ( is_array( $value ) ) {
				// Handle nested attributes
				if ( array_is_list( $value ) && ! empty( $value ) ) {
					// Repeater/array - get nested attributes
					$nested = $this->get_attribute_names( $value[0] );
					$names = array_merge( $names, $nested );
				} else {
					// Nested object
					$nested = $this->get_attribute_names( $value );
					$names = array_merge( $names, $nested );
				}
			}
		}

		return $names;
	}
}

