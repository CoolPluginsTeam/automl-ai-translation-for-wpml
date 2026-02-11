<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-ai-translations">
	<div class="wpml-auto-dashboard-ai-translations-container">
		<div class="header">
			<h1><?php esc_html_e( 'AI Translations', 'wpml-auto-translate-addon' ); ?></h1>
			<div class="wpml-auto-dashboard-status">
				<span><?php esc_html_e( 'Active', 'wpml-auto-translate-addon' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-client' ) ); ?>"
					class="wpml-auto-dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'Configure AI Provider', 'wpml-auto-translate-addon' ); ?>
				</a>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e(
				'This addon uses the WordPress AI SDK (wp-ai-client) to connect to multiple AI providers. Choose a model per provider and then use WPML Translation Management to auto-translate content.',
				'wpml-auto-translate-addon'
			); ?>
		</p>

		<div class="wpml-auto-dashboard-translations">
			<?php
			$wpml_auto_ai_translations = array(
				array(
					'logo'       => 'openai-translate-logo.png',
					'alt'        => 'OpenAI',
					'title'      => esc_html__( 'OpenAI Models', 'wpml-auto-translate-addon' ),
					'description'=> esc_html__( 'Use OpenAI models (like GPT) via the AI SDK registry for context-aware translations.', 'wpml-auto-translate-addon' ),
					'icon'       => 'open-ai-translate.png',
					'url'        => 'https://developer.wordpress.org/docs/ai/#openai',
				),
				array(
					'logo'       => 'geminiai-logo.png',
					'alt'        => 'Google / Gemini',
					'title'      => esc_html__( 'Google / Gemini Models', 'wpml-auto-translate-addon' ),
					'description'=> esc_html__( 'Use Google / Gemini models registered in the AI SDK for fast and accurate translations.', 'wpml-auto-translate-addon' ),
					'icon'       => 'gemini-translate.png',
					'url'        => 'https://developer.wordpress.org/docs/ai/#google-gemini',
				),
				array(
					'logo'       => 'chrome-built-in-ai-logo.png',
					'alt'        => 'Other Providers',
					'title'      => esc_html__( 'Other Providers', 'wpml-auto-translate-addon' ),
					'description'=> esc_html__( 'Work with any supported AI provider exposed through the WordPress AI SDK.', 'wpml-auto-translate-addon' ),
					'icon'       => 'chrome-ai-translate.png',
					'url'        => 'https://developer.wordpress.org/docs/ai/',
				),
			);

			foreach ( $wpml_auto_ai_translations as $item ) :
				?>
				<div class="wpml-auto-dashboard-translation-card">
					<div class="logo">
						<img src="<?php echo esc_url( WPML_AT_PLUGIN_URL . 'assets/images/' . $item['logo'] ); ?>"
							alt="<?php echo esc_attr( $item['alt'] ); ?>">
					</div>
					<h3><?php echo esc_html( $item['title'] ); ?></h3>
					<p><?php echo esc_html( $item['description'] ); ?></p>
					<div class="play-btn-container">
						<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">
							<img src="<?php echo esc_url( WPML_AT_PLUGIN_URL . 'admin/wpml-auto-dashboard/images/' . $item['icon'] ); ?>"
								alt="<?php echo esc_attr( $item['alt'] ); ?>">
						</a>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>
	</div>
</div>