<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-license">
	<div class="wpml-auto-dashboard-license-container">
		<div class="header">
			<h1><?php esc_html_e( 'License Key', 'automl-ai-translation-for-wpml' ); ?></h1>
			<div class="wpml-auto-dashboard-status">
				<span><?php esc_html_e( 'Free', 'automl-ai-translation-for-wpml' ); ?></span>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=license' ); ?>"
					class="wpml-auto-dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'View More Plugins', 'automl-ai-translation-for-wpml' ); ?>
				</a>
			</div>
		</div>

		<p>
			<?php esc_html_e( 'This addon does not require a license key. You can freely use it with WPML and the WordPress AI SDK.', 'automl-ai-translation-for-wpml' ); ?>
		</p>

		<p>
			<?php
			echo sprintf(
				// translators: %s: plugin name.
				esc_html__( "You're using %s - no license needed. Enjoy!", 'automl-ai-translation-for-wpml' ),
				'<strong>' . esc_html__( 'WPML Google Auto Translate Addon', 'automl-ai-translation-for-wpml' ) . '</strong>'
			);
			?>
		</p>

		<div class="wpml-auto-dashboard-upgrade-box">
			<p>
				<?php esc_html_e( 'If you like this addon, you can explore more multilingual & translation plugins by Cool Plugins.', 'automl-ai-translation-for-wpml' ); ?>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=license_box' ); ?>"
					target="_blank">
					<?php esc_html_e( 'Browse products', 'automl-ai-translation-for-wpml' ); ?>
				</a>.
			</p>
			<em>
				<?php esc_html_e( 'Thank you for using our plugins. Your feedback and reviews help us improve and add more powerful translation tools.', 'automl-ai-translation-for-wpml' ); ?>
			</em>
		</div>
	</div>
</div>