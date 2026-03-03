import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import SetupContinueButton, { SetupBackButton } from './SetupContinueButton';
import { getNonce } from '../utils';

const AiTranslation = ({ onBack, onContinue }) => {
	const data = window.wpml_at_setup || {};
	const dashboardUrl =
		data.dashboard_url ||
		( ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?automl_ai_dashboard&tab=settings' ) );
	const saved_models = data.saved_models || {};
	const savedCreds   = data.saved_credentials || {};

	const [openaiKey, setOpenaiKey]       = React.useState( savedCreds.openai_key || '' );
	const [googleKey, setGoogleKey]       = React.useState( savedCreds.google_key || '' );
	const [openaiModel, setOpenaiModel]   = React.useState( saved_models.openai_model || '' );
	const [googleModel, setGoogleModel]   = React.useState( saved_models.google_model || '' );
	const [saving, setSaving]             = React.useState( false );
	const [openaiMessage, setOpenaiMessage]   = React.useState( null ); // error under OpenAI
	const [googleMessage, setGoogleMessage]   = React.useState( null ); // error under Google
	const [generalMessage, setGeneralMessage] = React.useState( null ); // success / general error
	const [isError, setIsError]               = React.useState( false );

	const handleSave = async () => {
		setSaving( true );
		setOpenaiMessage( null );
		setGoogleMessage( null );
		setGeneralMessage( null );
		setIsError( false );

		try {
			await apiFetch( {
				path: 'automl-bulk-translate/wizard-save-credentials',
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getNonce(),
				},
				body: JSON.stringify( {
					openai_key: openaiKey,
					google_key: googleKey,
					openai_model: openaiModel,
					google_model: googleModel,
				} ),
			} );

			// Success: show a single general success message
			setGeneralMessage( __( 'API keys saved.', 'automl-ai-translation-for-wpml' ) );
			setIsError( false );
			return true;
		} catch ( err ) {
			const fieldErrors = err?.data?.errors || {};

			// Field‑specific errors from PHP
			if ( fieldErrors.openai ) {
				setOpenaiMessage( fieldErrors.openai );
			}
			if ( fieldErrors.google ) {
				setGoogleMessage( fieldErrors.google );
			}

			// No key at all
			if ( err?.code === 'automl_no_api_key' && ! fieldErrors.openai && ! fieldErrors.google ) {
				setGeneralMessage( err?.message );
			}

			// Fallback general error if nothing else
			if ( ! fieldErrors.openai && ! fieldErrors.google && ! generalMessage && err?.message ) {
				setGeneralMessage( err.message );
			}

			setIsError( true );
			return false;
		} finally {
			setSaving( false );
		}
	};

	return (
		<>
			<div
				className="automl-ai-wizard-card"
				style={{ maxWidth: 600, margin: '0 auto', minHeight: '40vh' }}
			>
				<div className="automl-ai-wizard-language-container" style={{ flex: 1 }}>
					<h2 style={{ marginTop: 0 }}>
						{ __( 'Connect AI Provider', 'automl-ai-translation-for-wpml' ) }
					</h2>
					<p style={{ marginBottom: 12, color: '#6b7280' }}>
						{ __(
							'To start using AI translation, connect at least one AI provider below. Add your AI provider API key to start translating your website with AutoML.',
							'automl-ai-translation-for-wpml'
						) }
					</p>

					{ generalMessage && (
						<p
							style={{
								color: isError ? '#b32d2e' : '#00a32a',
								marginBottom: 12,
								fontSize: 14,
							}}
						>
							{ generalMessage }
						</p>
					) }

					<div style={{ marginBottom: 16 }}>
						<label
							htmlFor="automl-ai-wizard-openai-key"
							style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}
						>
							{ __( 'OpenAI API key', 'automl-ai-translation-for-wpml' ) }
						</label>
						<input
							id="automl-ai-wizard-openai-key"
							type="password"
							value={ openaiKey }
							onChange={ ( e ) => setOpenaiKey( e.target.value ) }
							style={{ width: '100%', padding: '8px 12px', fontSize: 14 }}
						/>
						{ openaiMessage && (
							<p
								style={{
									color: '#b32d2e',
									marginTop: 4,
									marginBottom: 12,
									fontSize: 13,
								}}
							>
								{ openaiMessage }
							</p>
						) }
					</div>

					<div style={{ marginBottom: 16 }}>
						<label
							htmlFor="automl-ai-wizard-google-key"
							style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}
						>
							{ __( 'Google / Gemini API key', 'automl-ai-translation-for-wpml' ) }
						</label>
						<input
							id="automl-ai-wizard-google-key"
							type="password"
							value={ googleKey }
							onChange={ ( e ) => setGoogleKey( e.target.value ) }
							style={{ width: '100%', padding: '8px 12px', fontSize: 14 }}
						/>
						{ googleMessage && (
							<p
								style={{
									color: '#b32d2e',
									marginTop: 4,
									marginBottom: 12,
									fontSize: 13,
								}}
							>
								{ googleMessage }
							</p>
						) }
					</div>

					<div className="automl-ai-wizard-card-language-footer">
						<span
							className="automl-ai-wizard-card-language-footer-icon"
							aria-hidden="true"
						>
							<img
								src={
									( data.home_url || '' ) +
									'/wp-content/plugins/automl-ai-translation-for-wpml/assets/images/star-icons.png'
								}
								alt=""
								width={ 18 }
								height={ 18 }
								style={{ display: 'block' }}
							/>
						</span>
						<div className="automl-ai-wizard-card-language-footer-content">
							<p>
								<strong>
									{ __( 'Chrome Built-in AI', 'automl-ai-translation-for-wpml' ) }
								</strong>
								{ ' — ' }
								{ __(
									'Translate on your device using local AI, no API key required, unlimited translations, save 100% on API usage costs.',
									'automl-ai-translation-for-wpml'
								) }
							</p>
							<a
								href={ data.upgrade_url || '#' }
								target="_blank"
								rel="noopener noreferrer"
								className="automl-ai-wizard-card-language-footer-link"
							>
								{ __( 'Upgrade to Pro →', 'automl-ai-translation-for-wpml' ) }
							</a>
						</div>
					</div>

					<p className="automl-ai-wizard-api-note">
						{ __(
							'API keys are saved securely and can be updated anytime in WPML → AutoML AI → Settings.',
							'automl-ai-translation-for-wpml'
						) }
					</p>
				</div>

				<div
					className="automl-ai-wizard-footer"
					style={{ display: 'flex', justifyContent: 'space-between', marginTop: 24 }}
				>
					<SetupBackButton onClick={ onBack } />
					<SetupContinueButton
						onClick={ async () => {
							const saved = await handleSave();
							if ( ! saved ) return;
							try {
								await apiFetch( {
									path: 'automl-bulk-translate/wizard-complete',
									method: 'POST',
									headers: { 'X-WP-Nonce': getNonce() },
								} );
							} catch ( e ) {
								// ignore, we'll still redirect
							}
							window.location.href = dashboardUrl;
						} }
						label={ __( 'Finish setup', 'automl-ai-translation-for-wpml' ) }
						disabled={ saving }
					/>
				</div>
			</div>

			<div className="automl-ai-wizard-card-footer">
				{ __( 'Need help? Visit our', 'automl-ai-translation-for-wpml' ) }{ ' ' }
				<a href={ data.doc_url || '#' } target="_blank" rel="noopener noreferrer">
					{ __( 'Documentation', 'automl-ai-translation-for-wpml' ) }
				</a>
			</div>
		</>
	);
};

export default AiTranslation;