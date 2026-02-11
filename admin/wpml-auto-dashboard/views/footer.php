<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-info">
	<div class="wpml-auto-dashboard-info-links">
		<p>
			<?php esc_html_e( 'Made with ❤️ by', 'wpml-auto-translate-addon' ); ?>
			<span class="logo">
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=author_page&utm_content=dashboard_footer' ); ?>" target="_blank">
					<img src="<?php echo esc_url( WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/images/cool-plugins-logo-black.svg' ); ?>" alt="<?php esc_attr_e( 'Cool Plugins Logo', 'wpml-auto-translate-addon' ); ?>">
				</a>
			</span>
		</p>
		<a href="<?php echo esc_url( 'https://coolplugins.net/support/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Support', 'wpml-auto-translate-addon' ); ?>
		</a> |
		<a href="<?php echo esc_url( 'https://coolplugins.net/docs/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Docs', 'wpml-auto-translate-addon' ); ?>
		</a>

		<div class="wpml-auto-dashboard-social-icons">
			<?php
			$wpml_auto_social_links = array(
				array( 'https://www.facebook.com/coolplugins/', 'facebook.svg', esc_html__( 'Facebook', 'wpml-auto-translate-addon' ) ),
				array( 'https://linkedin.com/company/coolplugins', 'linkedin.svg', esc_html__( 'LinkedIn', 'wpml-auto-translate-addon' ) ),
				array( 'https://x.com/cool_plugins', 'twitter.svg', esc_html__( 'Twitter / X', 'wpml-auto-translate-addon' ) ),
				array( 'https://www.youtube.com/@cool_plugins', 'youtube.svg', esc_html__( 'YouTube Channel', 'wpml-auto-translate-addon' ) ),
			);

			foreach ( $wpml_auto_social_links as $link ) {
				echo '<a href="' . esc_url( $link[0] ) . '" target="_blank">
						<img src="' . esc_url( WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/images/' . $link[1] ) . '" alt="' . esc_attr( $link[2] ) . '">
					  </a>';
			}
			?>
		</div>
	</div>
</div>