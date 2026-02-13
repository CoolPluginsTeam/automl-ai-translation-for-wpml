<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'automl-ai-translation-for-wpml' ) );
}
?>
<div class="wpml-auto-dashboard-settings">
	<div class="wpml-auto-dashboard-settings-container">
		<div class="header">
			<h1><?php echo esc_html__( 'WPML Auto Translate Settings', 'automl-ai-translation-for-wpml' ); ?></h1>
		</div>

		<p class="description">
			<?php
			echo esc_html__(
				'Configure your AI providers and translation models here. Keys are stored via the WP AI Client option and models via the WPML addon model option.',
				'automl-ai-translation-for-wpml'
			);
			?>
		</p>

		<div class="wpml-auto-dashboard-api-settings-container">
			<div class="wpml-auto-dashboard-api-settings">
				<form method="post" action="options.php">
					<?php
                     // Dummy username field to satisfy browser heuristics; not used by backend.
                        ?>
                        <input
                            type="text"
                            name="wpml_auto_dummy_api_key"
                            autocomplete="api-key"
                            style="display:none;"
                            aria-hidden="true"
                        />
                        <?php
					// AI SDK credentials (wp-ai-client).
                    settings_fields( 'wp-ai-client-settings' );
          
					// Current AI SDK credentials.
					$wp_ai_credentials = get_option( 'wp_ai_client_provider_credentials', array() );

					// Current selected models (saved by the addon).
					$current_models       = get_option( 'wpml_at_ai_translation_models', array() );
					$current_openai_model = isset( $current_models['openai'] ) ? $current_models['openai'] : '';
					$current_google_model = isset( $current_models['google'] ) ? $current_models['google'] : '';
					$openai_api_key = get_option( 'wp_ai_client_provider_credentials', array() )['openai'];
					$google_api_key = get_option( 'wp_ai_client_provider_credentials', array() )['google'];
                    if ( empty( $current_openai_model ) && !empty( $openai_api_key ) ) {
						$current_openai_model = 'gpt-5-mini';
						update_option( 'wpml_at_ai_translation_models', array( 'openai' => $current_openai_model ) );
					}
					if ( empty( $current_google_model ) && !empty( $google_api_key ) ) {
						$current_google_model = 'gemini-2.5-flash';
						update_option( 'wpml_at_ai_translation_models', array( 'google' => $current_google_model ) );
					}
					
					$openai_models = array();
					$google_models = array();

					// Use "has API key" instead of isProviderConfigured() to avoid HTTP requests on every page load.
					$has_openai_key = ! empty( $wp_ai_credentials['openai'] );
					$has_google_key = ! empty( $wp_ai_credentials['google'] );

					if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
						$registry = \WordPress\AiClient\AiClient::defaultRegistry();

						// OpenAI models (cached 1 hour to avoid API request on every refresh).
						if ( $has_openai_key ) {
							$cache_key = 'automl_wpml_openai_models';
							$cached    = get_transient( $cache_key );
							if ( false !== $cached && is_array( $cached ) ) {
								$openai_models = $cached;
							} else {
								try {
									$openai_class = $registry->getProviderClassName( 'openai' );
									$directory    = $openai_class::modelMetadataDirectory();
									$openai_models = array_map(
										static function ( $model ) {
											/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
											return $model->getId();
										},
										$directory->listModelMetadata()
									);
									set_transient( $cache_key, $openai_models, 24 * HOUR_IN_SECONDS );
								} catch ( \Throwable $e ) {
									$openai_models = array();
								}
							}
						}

						// Google / Gemini models (cached 1 hour to avoid API request on every refresh).
						if ( $has_google_key ) {
							$cache_key = 'automl_wpml_google_models';
							$cached    = get_transient( $cache_key );
							if ( false !== $cached && is_array( $cached ) ) {
								$google_models = $cached;
							} else {
								try {
									$google_class = $registry->getProviderClassName( 'google' );
									$directory    = $google_class::modelMetadataDirectory();
									$google_models = array_map(
										static function ( $model ) {
											/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
											return $model->getId();
										},
										$directory->listModelMetadata()
									);
									set_transient( $cache_key, $google_models, 24 * HOUR_IN_SECONDS );
								} catch ( \Throwable $e ) {
									$google_models = array();
								}
							}
						}
					}

					?>
					<div class="wpml-auto-dashboard-api-settings-form">
						<?php
						// Providers shown in the UI.
						$wpml_auto_api_settings = array(
							'openai' => array(
								'name'        => 'OpenAI',
								'doc_url'     => 'https://developer.wordpress.org/docs/ai/#openai',
								'placeholder' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
							'google' => array(
								'name'        => 'Google / Gemini',
								'doc_url'     => 'https://developer.wordpress.org/docs/ai/#google-gemini',
								'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
						);

						foreach ( $wpml_auto_api_settings as $api_key => $settings ) :
							?>
							<label for="<?php echo esc_attr( $api_key ); ?>-api">
								<?php
								// translators: %s: API name.
								printf(
									esc_html__( 'Add %s API key', 'automl-ai-translation-for-wpml' ),
									esc_html( $settings['name'] )
								);
								?>
							</label>
							<div class="input-group">
                            <input
                                type="password"
                                id="<?php echo esc_attr( $api_key ); ?>-api"
                                name="wp_ai_client_provider_credentials[<?php echo esc_attr( $api_key ); ?>]"
                                value="<?php echo isset( $wp_ai_credentials[ $api_key ] ) ? esc_attr( $wp_ai_credentials[ $api_key ] ) : ''; ?>"
                                placeholder="<?php echo esc_attr( $settings['placeholder'] ); ?>"
                                autocomplete="new-password"
                            />
							</div>

							<?php
							$has_key = ! empty( $wp_ai_credentials[ $api_key ] );

							// OpenAI model selector.
							if ( 'openai' === $api_key && $has_key && ! empty( $openai_models ) ) : ?>
								<div class="wpml-auto-dashboard-api-settings-openai-model">
									<label for="wpml_selected_openai_model" class="api-settings-label">
										<?php esc_html_e( 'Select OpenAI Model', 'automl-ai-translation-for-wpml' ); ?>
									</label>
									<select
										id="wpml_selected_openai_model"
										name="wpml_at_ai_translation_models[openai]"
										class="wpml-openai-model-select"
									>
										<option value=""><?php esc_html_e( 'Select model...', 'automl-ai-translation-for-wpml' ); ?></option>
										<?php foreach ( $openai_models as $model_id ) : ?>
											<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_openai_model, $model_id ); ?>>
												<?php echo esc_html( $model_id ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php
							endif;

							// Google / Gemini model selector.
							if ( 'google' === $api_key && $has_key && ! empty( $google_models ) ) : ?>
								<div class="wpml-auto-dashboard-api-settings-google-model">
									<label for="wpml_selected_google_model" class="api-settings-label">
										<?php esc_html_e( 'Select Gemini Model', 'automl-ai-translation-for-wpml' ); ?>
									</label>
									<select
										id="wpml_selected_google_model"
										name="wpml_at_ai_translation_models[google]"
										class="wpml-google-model-select"
									>
										<option value=""><?php esc_html_e( 'Select model...', 'automl-ai-translation-for-wpml' ); ?></option>
										<?php foreach ( $google_models as $model_id ) : ?>
											<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_google_model, $model_id ); ?>>
												<?php echo esc_html( $model_id ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php
							endif;

							printf(
								// translators: 1: Click here link, 2: API name.
								esc_html__( '%1$s to see how to configure %2$s in the AI SDK.', 'automl-ai-translation-for-wpml' ),
								'<a href="' . esc_url( $settings['doc_url'] ) . '" target="_blank">' . esc_html__( 'Click here', 'automl-ai-translation-for-wpml' ) . '</a>',
								esc_html( $settings['name'] )
							);
							echo '<br/><br/>';
						endforeach;
						?>

						<hr style="margin: 2rem 0px;">

						<div class="wpml-auto-dashboard-save-btn-container">
							<?php submit_button( __( 'Save (via WP AI Client & WPML Addon)', 'automl-ai-translation-for-wpml' ) ); ?>
						</div>
					</div><!-- .wpml-auto-dashboard-api-settings-form -->
				</form>
			</div>
		</div>
	</div>
</div>