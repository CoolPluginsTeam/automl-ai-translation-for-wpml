<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers
 */

if ( ! function_exists( 'wpml_auto_format_time_taken' ) ) :
	/**
	 * Format total time taken in a readable way.
	 *
	 * @param int $time_taken Seconds.
	 * @return string
	 */
	function wpml_auto_format_time_taken( $time_taken ) {
		if ( 0 === intval( $time_taken ) ) {
			return esc_html__( '0', 'automl-ai-translation-for-wpml' );
		}

		$time_taken = intval( $time_taken );

		if ( $time_taken < 60 ) {
			// translators: %d: seconds.
			return sprintf( esc_html__( '%d sec', 'automl-ai-translation-for-wpml' ), $time_taken );
		}

		if ( $time_taken < 3600 ) {
			$min = floor( $time_taken / 60 );
			$sec = $time_taken % 60;
			// translators: 1: minutes, 2: seconds.
			return sprintf( esc_html__( '%1$d min %2$d sec', 'automl-ai-translation-for-wpml' ), $min, $sec );
		}

		$hours = floor( $time_taken / 3600 );
		$min   = floor( ( $time_taken % 3600 ) / 60 );
		// translators: 1: hours, 2: minutes.
		return sprintf( esc_html__( '%1$d hours %2$d min', 'automl-ai-translation-for-wpml' ), $hours, $min );
	}
endif;

if ( ! function_exists( 'wpml_auto_is_plugin_installed' ) ) :
	/**
	 * Check if a specific plugin is installed.
	 *
	 * @param string $plugin_slug Plugin slug key.
	 * @return bool
	 */
	function wpml_auto_is_plugin_installed( $plugin_slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( 'automatic-translator-addon-for-loco-translate' === $plugin_slug ) {
			return isset( $plugins['automatic-translator-addon-for-loco-translate/automatic-translator-addon-for-loco-translate.php'] )
				|| isset( $plugins['loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php'] );
		}

		return false;
	}
endif;

if ( ! function_exists( 'wpml_auto_get_plugin_display_name' ) ) :
	/**
	 * Get display name for addon plugin (free / pro).
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return string
	 */
	function wpml_auto_get_plugin_display_name( $plugin_slug ) {
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();

		$plugin_paths = array(
			'automatic-translator-addon-for-loco-translate' => array(
				'free'      => 'automatic-translator-addon-for-loco-translate/automatic-translator-addon-for-loco-translate.php',
				'pro'       => 'loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php',
				'free_name' => esc_html__( 'LocoAI – Auto Translate For Loco Translate', 'automl-ai-translation-for-wpml' ),
				'pro_name'  => esc_html__( 'LocoAI – Auto Translate for Loco Translate (Pro)', 'automl-ai-translation-for-wpml' ),
			),
		);

		if ( ! isset( $plugin_paths[ $plugin_slug ] ) ) {
			return $plugin_slug;
		}

		$free_installed = isset( $plugins[ $plugin_paths[ $plugin_slug ]['free'] ] );
		$pro_installed  = isset( $plugins[ $plugin_paths[ $plugin_slug ]['pro'] ] );

		if ( $pro_installed ) {
			return $plugin_paths[ $plugin_slug ]['pro_name'];
		} elseif ( $free_installed ) {
			return $plugin_paths[ $plugin_slug ]['free_name'];
		}

		return $plugin_paths[ $plugin_slug ]['free_name'];
	}
endif;

if ( ! function_exists( 'wpml_auto_format_number' ) ) :
	/**
	 * Format big numbers as K/M/B.
	 *
	 * @param int $number Number.
	 * @return string
	 */
	function wpml_auto_format_number( $number ) {
		$number = intval( $number );

		if ( $number >= 1000000000 ) {
			return round( $number / 1000000000, 1 ) . esc_html__( 'B', 'automl-ai-translation-for-wpml' );
		} elseif ( $number >= 1000000 ) {
			return round( $number / 1000000, 1 ) . esc_html__( 'M', 'automl-ai-translation-for-wpml' );
		} elseif ( $number >= 1000 ) {
			return round( $number / 1000, 1 ) . esc_html__( 'K', 'automl-ai-translation-for-wpml' );
		}

		return (string) $number;
	}
endif;
?>

<!-- Right Sidebar -->
<div class="wpml-auto-dashboard-sidebar">
	<div class="wpml-auto-dashboard-status">
		<h3><?php esc_html_e( 'Auto Translation Status', 'automl-ai-translation-for-wpml' ); ?></h3>
		<div class="wpml-auto-dashboard-sts-top">
			<?php
			// You can later store stats in an option similar to this.
			$wpml_auto_all_translation_data = get_option( 'wpml_auto_dashboard_data', array() );

			if ( ! is_array( $wpml_auto_all_translation_data ) || ! isset( $wpml_auto_all_translation_data['wpml_auto'] ) ) {
				$wpml_auto_all_translation_data['wpml_auto'] = array();
			}

			$totals = array_reduce(
				$wpml_auto_all_translation_data['wpml_auto'],
				function ( $carry, $translation ) {
					$carry['string_count']    += intval( $translation['string_count'] ?? 0 );
					$carry['character_count'] += intval( $translation['character_count'] ?? 0 );
					$carry['time_taken']      += intval( $translation['time_taken'] ?? 0 );

					if ( ! empty( $translation['job_id'] ) ) {
						$carry['translation_count']++;
					}
					return $carry;
				},
				array(
					'string_count'      => 0,
					'character_count'   => 0,
					'time_taken'        => 0,
					'translation_count' => 0,
				)
			);

			$wpml_auto_time_taken_str = wpml_auto_format_time_taken( $totals['time_taken'] );
			?>
			<span><?php echo esc_html( wpml_auto_format_number( $totals['character_count'] ) ); ?></span>
			<span><?php esc_html_e( 'Total Characters Translated!', 'automl-ai-translation-for-wpml' ); ?></span>
		</div>
		<ul class="wpml-auto-dashboard-sts-btm">
			<li>
				<span><?php esc_html_e( 'Total Strings', 'automl-ai-translation-for-wpml' ); ?></span>
				<span><?php echo esc_html( wpml_auto_format_number( $totals['string_count'] ) ); ?></span>
			</li>
			<li>
				<span><?php esc_html_e( 'Total Translation Jobs', 'automl-ai-translation-for-wpml' ); ?></span>
				<span><?php echo esc_html( $totals['translation_count'] ); ?></span>
			</li>
			<li>
				<span><?php esc_html_e( 'Time Taken', 'automl-ai-translation-for-wpml' ); ?></span>
				<span><?php echo esc_html( $wpml_auto_time_taken_str ); ?></span>
			</li>
		</ul>
	</div>

	<div class="wpml-auto-dashboard-translate-full">
		<h3><?php esc_html_e( 'Other Auto Translation Addons', 'automl-ai-translation-for-wpml' ); ?></h3>
		<div class="wpml-auto-dashboard-addon first">
			<div class="wpml-auto-dashboard-addon-l">
				<strong><?php echo esc_html( wpml_auto_get_plugin_display_name( 'automatic-translator-addon-for-loco-translate' ) ); ?></strong>
				<span class="addon-desc">
					<?php esc_html_e( 'Loco Translate addon to automatically translate plugins and themes.', 'automl-ai-translation-for-wpml' ); ?>
				</span>

				<?php if ( wpml_auto_is_plugin_installed( 'automatic-translator-addon-for-loco-translate' ) ) : ?>
					<span class="installed"><?php esc_html_e( 'Installed', 'automl-ai-translation-for-wpml' ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=Automatic+translate+addon+for+loco+translate+by+coolplugins&tab=search&type=term' ) ); ?>"
						class="wpml-auto-dashboard-btn"
						target="_blank">
						<?php esc_html_e( 'Install', 'automl-ai-translation-for-wpml' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<div class="wpml-auto-dashboard-addon-r">
				<img src="<?php echo esc_url( WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/images/atlt-logo.png' ); ?>" alt="<?php esc_attr_e( 'Loco Auto Translate Addon', 'automl-ai-translation-for-wpml' ); ?>">
			</div>
		</div>
	</div>

	<div class="wpml-auto-dashboard-rate-us">
		<h3><?php esc_html_e( 'Rate Us ⭐⭐⭐⭐⭐', 'automl-ai-translation-for-wpml' ); ?></h3>
		<p><?php esc_html_e( "We'd love your feedback! Hope this addon made WPML auto-translations easier for you.", 'automl-ai-translation-for-wpml' ); ?></p>
		<a href="https://wordpress.org/support/plugin/automl-ai-translation-for-wpml/reviews/#new-post"
			class="review-link"
			target="_blank">
			<?php esc_html_e( 'Submit a Review →', 'automl-ai-translation-for-wpml' ); ?>
		</a>
	</div>
</div>