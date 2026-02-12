<?php
/**
 * Admin Dashboard for WPML Auto Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPML_Auto_Dashboard' ) ) {

	/**
	 * Handles the custom admin dashboard page.
	 */
	class WPML_Auto_Dashboard {

		/**
		 * Instance.
		 *
		 * @var WPML_Auto_Dashboard|null
		 */
		protected static $instance = null;

		/**
		 * Get singleton instance.
		 *
		 * @return WPML_Auto_Dashboard
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
			add_action( 'admin_enqueue_scripts', array( $this, 'wpml_auto_enqueue_dashboard_assets' ) );
		}

		/**
		 * Enqueue dashboard CSS/JS only on our page.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function wpml_auto_enqueue_dashboard_assets( $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			if ( 'wpml-auto-dashboard' !== $page ) {
				return;
			}

			// Adjust paths if you place assets elsewhere.
			wp_enqueue_style(
				'wpml-auto-dashboard-style',
				WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/css/admin-styles.css',
				array(),
				WPML_AT_VERSION
			);

			wp_enqueue_script(
				'wpml-auto-dashboard-script',
				WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/js/wpml-auto-data-share-setting.js',
				array( 'jquery' ),
				WPML_AT_VERSION,
				true
			);
		}

		/**
		 * Render the main dashboard layout and load tab views.
		 */
		public function wpml_auto_render_dashboard_page() {
			// Folder for view files (you will create these files next).
			// Expected:
			// - admin/wpml-auto-dashboard/views/dashboard.php
			// - admin/wpml-auto-dashboard/views/ai-translations.php
			// - admin/wpml-auto-dashboard/views/settings.php
			// - admin/wpml-auto-dashboard/views/license.php
			// - admin/wpml-auto-dashboard/views/free-vs-pro.php
			// - admin/wpml-auto-dashboard/views/support-blocks.php
			// - admin/wpml-auto-dashboard/views/sidebar.php
			// - admin/wpml-auto-dashboard/views/footer.php
			$file_prefix = 'admin/wpml-auto-dashboard/views/';

			$valid_tabs = array(
				'dashboard'       => __( 'Dashboard', 'automl-ai-translation-for-wpml' ),
				'ai-translations' => __( 'AI Translations', 'automl-ai-translation-for-wpml' ),
				'settings'        => __( 'Settings', 'automl-ai-translation-for-wpml' ),
				'license'         => __( 'License', 'automl-ai-translation-for-wpml' ),
				'free-vs-pro'     => __( 'Free vs Pro', 'automl-ai-translation-for-wpml' ),
				'support-blocks'  => __( 'Supported Blocks', 'automl-ai-translation-for-wpml' ),
			);

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
			$current_tab = array_key_exists( $tab, $valid_tabs ) ? $tab : 'dashboard';
			?>
			<div class="wpml-auto-dashboard-wrapper">
				<div class="wpml-auto-dashboard-header">
					<div class="wpml-auto-dashboard-header-left">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpml-auto-dashboard&tab=dashboard' ) ); ?>" class="wpml-auto-dashboard-logo-link">
							<img src="<?php echo esc_url( WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/images/polylang-addon-logo.svg' ); ?>" alt="<?php esc_attr_e( 'WPML Auto Logo', 'automl-ai-translation-for-wpml' ); ?>">
						</a>
						<div class="wpml-auto-dashboard-tab-title">
							<span>↳</span> <?php echo esc_html( $valid_tabs[ $current_tab ] ); ?>
						</div>
					</div>
					<div class="wpml-auto-dashboard-header-right">
						<span><?php echo esc_html__( 'AutoML - AI Translation for WPML', 'automl-ai-translation-for-wpml' ); ?></span>
					</div>
				</div>

				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'automl-ai-translation-for-wpml' ); ?>">
					<?php foreach ( $valid_tabs as $tab_key => $tab_title ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpml-auto-dashboard&tab=' . $tab_key ) ); ?>"
							class="nav-tab <?php echo esc_attr( $tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
							<?php echo esc_html( $tab_title ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="tab-content">
					<?php
					// Main tab content.
					$view_file = WPML_AT_PLUGIN_DIR . $file_prefix . $current_tab . '.php';
					if ( file_exists( $view_file ) ) {
						require $view_file;
					} else {
						echo '<p>' . esc_html__( 'View file not found.', 'automl-ai-translation-for-wpml' ) . '</p>';
					}

					// Sidebar (everything except support-blocks).
					if ( 'support-blocks' !== $current_tab ) {
						$sidebar_file = WPML_AT_PLUGIN_DIR . $file_prefix . 'sidebar.php';
						if ( file_exists( $sidebar_file ) ) {
							require $sidebar_file;
						}
					}
					?>
				</div>

				<?php
				// Footer.
				$footer_file = WPML_AT_PLUGIN_DIR . $file_prefix . 'footer.php';
				if ( file_exists( $footer_file ) ) {
					require $footer_file;
				}
				?>
			</div>
			<?php
		}
	}

	// Bootstrap the dashboard class.
	WPML_Auto_Dashboard::get_instance();
}