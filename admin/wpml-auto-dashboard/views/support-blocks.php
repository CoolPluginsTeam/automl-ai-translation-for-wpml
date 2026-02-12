<?php
/**
 * Do not access the page directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPML_AT_Dashboard_Supported_Blocks' ) ) {

	/**
	 * Supported Blocks page inside the WPML Auto dashboard.
	 */
	class WPML_AT_Dashboard_Supported_Blocks {

		/**
		 * Singleton instance.
		 *
		 * @var WPML_AT_Dashboard_Supported_Blocks|null
		 */
		private static $instance = null;

		/**
		 * Plugin category cache.
		 *
		 * @var array
		 */
		private $plugin_category = array();

		/**
		 * Get singleton instance.
		 *
		 * @return WPML_AT_Dashboard_Supported_Blocks
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
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			if ( 'support-blocks' === $tab && 'wpml-auto-dashboard' === $page ) {
				$this->render_support_blocks_page();
				$this->enqueue_editor_assets();
			}
		}

		/**
		 * Enqueue assets for the supported blocks page.
		 *
		 * NOTE: You must add the referenced JS/CSS files under your plugin for this to work,
		 * or adjust the paths to where you actually place them.
		 */
		public function enqueue_editor_assets() {
			// DataTables + custom styles/scripts (adjust paths as needed).
			wp_enqueue_script(
				'wpml-at-datatable-script',
				WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/assets/js/dataTables.min.js',
				array( 'jquery' ),
				WPML_AT_VERSION,
				true
			);

			wp_enqueue_style(
				'wpml-at-supported-blocks-style',
				WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/assets/css/wpml-at-supported-blocks.min.css',
				array(),
				WPML_AT_VERSION
			);

			wp_enqueue_script(
				'wpml-at-supported-blocks',
				WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/assets/js/wpml-at-supported-blocks.min.js',
				array( 'wpml-at-datatable-script' ),
				WPML_AT_VERSION,
				true
			);
		}

		/**
		 * Render the support blocks page HTML.
		 */
		public function render_support_blocks_page() {
			?>
			<div class="wpml-at-supported-blocks-wrapper">
				<h3>
					<?php
					// translators: %s: plugin name.
					printf(
						esc_html__( 'Supported Blocks for %s', 'automl-ai-translation-for-wpml' ),
						'WPML Google Auto Translate Addon'
					);
					?>
				</h3>

				<div class="wpml-at-supported-blocks-filters">
					<div class="wpml-at-category-tab">
						<label for="wpml-at-blocks-category">
							<?php esc_html_e( 'Block Type Category:', 'automl-ai-translation-for-wpml' ); ?>
						</label>
						<select id="wpml-at-blocks-category" name="wpml_at_blocks_category">
							<option value="all"><?php esc_html_e( 'All', 'automl-ai-translation-for-wpml' ); ?></option>
							<option value="core">Core</option>
							<?php $this->output_blocks_category_options(); ?>
						</select>
					</div>

					<div class="wpml-at-filter-tab">
						<label for="wpml-at-blocks-filter">
							<?php esc_html_e( 'Show Blocks:', 'automl-ai-translation-for-wpml' ); ?>
						</label>
						<select id="wpml-at-blocks-filter" name="wpml_at_blocks_filter">
							<option value="all"><?php esc_html_e( 'All', 'automl-ai-translation-for-wpml' ); ?></option>
							<option value="supported"><?php esc_html_e( 'Supported Blocks', 'automl-ai-translation-for-wpml' ); ?></option>
							<option value="unsupported"><?php esc_html_e( 'Unsupported Blocks', 'automl-ai-translation-for-wpml' ); ?></option>
						</select>
					</div>
				</div>

				<div class="wpml-at-blocks-section">
					<div class="wpml-at-blocks-lists">
						<table class="wpml-at-supported-blocks-table" id="wpml-at-supported-blocks-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Sr.No', 'automl-ai-translation-for-wpml' ); ?></th>
									<th><?php esc_html_e( 'Block Name', 'automl-ai-translation-for-wpml' ); ?></th>
									<th><?php esc_html_e( 'Block Title', 'automl-ai-translation-for-wpml' ); ?></th>
									<th><?php esc_html_e( 'Status', 'automl-ai-translation-for-wpml' ); ?></th>
									<th><?php esc_html_e( 'Modify', 'automl-ai-translation-for-wpml' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php $this->output_supported_blocks_table_rows(); ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Output category options based on registered blocks.
		 */
		public function output_blocks_category_options() {
			if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
				return;
			}

			$blocks_data = WP_Block_Type_Registry::get_instance()->get_all_registered();

			$filter_blocks_data = array_filter(
				$blocks_data,
				function ( $block ) {
					return ! in_array( $block->category, array( 'media', 'reusable' ), true );
				}
			);

			foreach ( $filter_blocks_data as $block ) {
				$plugin_name = explode( '/', $block->name );
				$plugin_name = isset( $plugin_name[0] ) ? $plugin_name[0] : '';

				if ( ! empty( $plugin_name ) ) {
					$filter_plugin_name = $this->normalize_block_plugin_name( $plugin_name );
					$filter_plugin_name = str_replace( '-', ' ', $filter_plugin_name );
					$filter_plugin_name = ucwords( $filter_plugin_name );

					if ( in_array( $plugin_name, $this->plugin_category, true ) || 'core' === $plugin_name ) {
						continue;
					}

					$this->plugin_category[] = $plugin_name;

					echo '<option value="' . esc_attr( $plugin_name ) . '">' . esc_html( $filter_plugin_name ) . '</option>';
				}
			}
		}

		/**
		 * Output table rows for supported/unsupported blocks.
		 *
		 * This example treats all blocks as "Supported" by default; if you already have
		 * your own storage of block rules, you can adapt this logic similar to ATFP_Helper.
		 */
		public function output_supported_blocks_table_rows() {
			if ( ! class_exists( 'WP_Block_Type_Registry' ) || ! method_exists( 'WP_Block_Type_Registry', 'get_all_registered' ) ) {
				return;
			}

			$blocks_data = WP_Block_Type_Registry::get_instance()->get_all_registered();

			// If you have custom rules, you can load them here and decide support status.
			$supported_block_names = array(); // Example: array( 'core/paragraph', 'core/image', ... ).

			$s_no = 1;

			foreach ( $blocks_data as $block ) {
				$block_name  = esc_html( $block->name );
				$block_title = esc_html( $block->title );
				$status      = in_array( $block_name, $supported_block_names, true ) ? 'Supported' : 'Unsupported';
				$modify_text = in_array( $block_name, $supported_block_names, true ) ? esc_html__( 'Edit', 'automl-ai-translation-for-wpml' ) : esc_html__( 'Add', 'automl-ai-translation-for-wpml' );

				// In the original plugin this goes to a custom post type editor; you can change this link.
				$modify_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wpml-auto-dashboard&tab=settings' ) ) . '">' . $modify_text . '</a>';

				echo '<tr data-block-name="' . esc_attr( strtolower( $block_name ) ) . '" data-block-status="' . esc_attr( strtolower( $status ) ) . '">';
				echo '<td>' . esc_html( $s_no++ ) . '</td>';
				echo '<td>' . esc_html( $block_name ) . '</td>';
				echo '<td>' . esc_html( $block_title ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . wp_kses( $modify_link, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ) . '</td>';
				echo '</tr>';
			}
		}

		/**
		 * Map short plugin keys to readable names.
		 *
		 * @param string $block_name Block plugin slug.
		 * @return string
		 */
		private function normalize_block_plugin_name( $block_name ) {
			$predefined = array(
				'ub'               => 'Ultimate Blocks',
				'uagb'             => 'Spectra',
				'themeisle-blocks' => 'Otter Blocks',
			);

			if ( array_key_exists( $block_name, $predefined ) ) {
				return $predefined[ $block_name ];
			}

			return $block_name;
		}
	}

	WPML_AT_Dashboard_Supported_Blocks::get_instance();
}