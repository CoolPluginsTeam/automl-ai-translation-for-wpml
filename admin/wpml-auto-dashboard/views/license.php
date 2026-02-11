<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-license">
	<div class="wpml-auto-dashboard-license-container">
		<div class="header">
			<h1><?php esc_html_e( 'License Key', 'wpml-auto-translate-addon' ); ?></h1>
			<div class="wpml-auto-dashboard-status">
				<span><?php esc_html_e( 'Free', 'wpml-auto-translate-addon' ); ?></span>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=license' ); ?>"
					class="wpml-auto-dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'View More Plugins', 'wpml-auto-translate-addon' ); ?>
				</a>
			</div>
		</div>

		<p>
			<?php esc_html_e( 'This addon does not require a license key. You can freely use it with WPML and the WordPress AI SDK.', 'wpml-auto-translate-addon' ); ?>
		</p>

		<p>
			<?php
			// translators: %s: plugin name.
			echo sprintf(
				esc_html__( "You're using %s - no license needed. Enjoy!", 'wpml-auto-translate-addon' ),
				'<strong>' . esc_html__( 'WPML Google Auto Translate Addon', 'wpml-auto-translate-addon' ) . '</strong>'
			);
			?>
		</p>

		<div class="wpml-auto-dashboard-upgrade-box">
			<p>
				<?php esc_html_e( 'If you like this addon, you can explore more multilingual & translation plugins by Cool Plugins.', 'wpml-auto-translate-addon' ); ?>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=license_box' ); ?>"
					target="_blank">
					<?php esc_html_e( 'Browse products', 'wpml-auto-translate-addon' ); ?>
				</a>.
			</p>
			<em>
				<?php esc_html_e( 'Thank you for using our plugins. Your feedback and reviews help us improve and add more powerful translation tools.', 'wpml-auto-translate-addon' ); ?>
			</em>
		</div>
	</div>
</div>