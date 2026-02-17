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
					$automl_wpml_ai_credentials = get_option( 'wp_ai_client_provider_credentials', array() );

					// Current selected models (saved by the addon).
					$automl_wpml_current_models       = get_option( 'wpml_at_ai_translation_models', array() );
					$automl_wpml_current_openai_model = isset( $automl_wpml_current_models['openai'] ) ? $automl_wpml_current_models['openai'] : '';
					$automl_wpml_current_google_model = isset( $automl_wpml_current_models['google'] ) ? $automl_wpml_current_models['google'] : '';
					$automl_wpml_openai_api_key = get_option( 'wp_ai_client_provider_credentials', array() )['openai'];
					$automl_wpml_google_api_key = get_option( 'wp_ai_client_provider_credentials', array() )['google'];
                    if ( empty( $automl_wpml_current_openai_model ) && !empty( $automl_wpml_openai_api_key ) ) {
						$automl_wpml_current_openai_model = 'gpt-4o-mini';
						update_option( 'wpml_at_ai_translation_models', array( 'openai' => $automl_wpml_current_openai_model ) );
					}
					if ( empty( $automl_wpml_current_google_model ) && !empty( $automl_wpml_google_api_key ) ) {
						$automl_wpml_current_google_model = 'gemini-2.5-flash';
						update_option( 'wpml_at_ai_translation_models', array( 'google' => $automl_wpml_current_google_model ) );
					}
					
					$automl_wpml_openai_models = array();
					$automl_wpml_google_models = array();

					// Use "has API key" instead of isProviderConfigured() to avoid HTTP requests on every page load.
					$automl_wpml_has_openai_key = ! empty( $automl_wpml_ai_credentials['openai'] );
					$automl_wpml_has_google_key = ! empty( $automl_wpml_ai_credentials['google'] );

					if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
						$automl_wpml_registry = \WordPress\AiClient\AiClient::defaultRegistry();

						// OpenAI models (cached 1 hour to avoid API request on every refresh).
						if ( $automl_wpml_has_openai_key ) {
							$automl_wpml_cache_key = 'automl_wpml_openai_models';
							$automl_wpml_cached    = get_transient( $automl_wpml_cache_key );
							if ( false !== $automl_wpml_cached && is_array( $automl_wpml_cached ) ) {
								$automl_wpml_openai_models = $automl_wpml_cached;
							} else {
								try {
									$automl_wpml_openai_class = $automl_wpml_registry->getProviderClassName( 'openai' );
									$automl_wpml_directory    = $automl_wpml_openai_class::modelMetadataDirectory();
									$automl_wpml_openai_models = array_map(
										static function ( $model ) {
											/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
											return $model->getId();
										},
										$automl_wpml_directory->listModelMetadata()
									);
									set_transient( $automl_wpml_cache_key, $automl_wpml_openai_models, 24 * HOUR_IN_SECONDS );
								} catch ( \Throwable $e ) {
									$automl_wpml_openai_models = array();
								}
							}
						}

						// Google / Gemini models (cached 1 hour to avoid API request on every refresh).
						if ( $automl_wpml_has_google_key ) {
							$automl_wpml_cache_key = 'automl_wpml_google_models';
							$automl_wpml_cached    = get_transient( $automl_wpml_cache_key );
							if ( false !== $automl_wpml_cached && is_array( $automl_wpml_cached ) ) {
								$automl_wpml_google_models = $automl_wpml_cached;
							} else {
								try {
									$automl_wpml_google_class = $automl_wpml_registry->getProviderClassName( 'google' );
									$automl_wpml_directory    = $automl_wpml_google_class::modelMetadataDirectory();
									$automl_wpml_google_models = array_map(
										static function ( $model ) {
											/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
											return $model->getId();
										},
										$automl_wpml_directory->listModelMetadata()
									);
									set_transient( $automl_wpml_cache_key, $automl_wpml_google_models, 24 * HOUR_IN_SECONDS );
								} catch ( \Throwable $e ) {
									$automl_wpml_google_models = array();
								}
							}
						}
					}

					?>
					<div class="wpml-auto-dashboard-api-settings-form">
						<?php
						// Providers shown in the UI.
						$automl_wpml_api_settings = array(
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

						foreach ( $automl_wpml_api_settings as $automl_wpml_api_key => $automl_wpml_settings ) :
							?>
							<label for="<?php echo esc_attr( $automl_wpml_api_key ); ?>-api">
								<?php
								printf(
									// translators: %s: API name.
									esc_html__( 'Add %s API key', 'automl-ai-translation-for-wpml' ),
									esc_html( $automl_wpml_settings['name'] )
								);
								?>
							</label>
							<div class="input-group">
                            <input
                                type="password"
                                id="<?php echo esc_attr( $automl_wpml_api_key ); ?>-api"
                                name="wp_ai_client_provider_credentials[<?php echo esc_attr( $automl_wpml_api_key ); ?>]"
                                value="<?php echo isset( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] ) ? esc_attr( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] ) : ''; ?>"
                                placeholder="<?php echo esc_attr( $automl_wpml_settings['placeholder'] ); ?>"
                                autocomplete="new-password"
                            />
							</div>

							<?php
							$automl_wpml_has_key = ! empty( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] );

							// OpenAI model selector.
							if ( 'openai' === $automl_wpml_api_key && $automl_wpml_has_key && ! empty( $automl_wpml_openai_models ) ) : ?>
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
										<?php foreach ( $automl_wpml_openai_models as $automl_wpml_model_id ) : ?>
											<option value="<?php echo esc_attr( $automl_wpml_model_id ); ?>" <?php selected( $automl_wpml_current_openai_model, $automl_wpml_model_id ); ?>>
												<?php echo esc_html( $automl_wpml_model_id ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php
							endif;

							// Google / Gemini model selector.
							if ( 'google' === $automl_wpml_api_key && $automl_wpml_has_key && ! empty( $automl_wpml_google_models ) ) : ?>
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
										<?php foreach ( $automl_wpml_google_models as $automl_wpml_model_id ) : ?>
											<option value="<?php echo esc_attr( $automl_wpml_model_id ); ?>" <?php selected( $automl_wpml_current_google_model, $automl_wpml_model_id ); ?>>
												<?php echo esc_html( $automl_wpml_model_id ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php
							endif;

							printf(
								// translators: 1: Click here link, 2: API name.
								esc_html__( '%1$s to see how to configure %2$s in the AI SDK.', 'automl-ai-translation-for-wpml' ),
								'<a href="' . esc_url( $automl_wpml_settings['doc_url'] ) . '" target="_blank">' . esc_html__( 'Click here', 'automl-ai-translation-for-wpml' ) . '</a>',
								esc_html( $automl_wpml_settings['name'] )
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