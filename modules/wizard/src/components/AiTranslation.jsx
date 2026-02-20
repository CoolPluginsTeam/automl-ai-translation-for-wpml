import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import SetupContinueButton, { SetupBackButton } from './SetupContinueButton';
import { getNonce } from '../utils';

const AiTranslation = ({ onBack, onContinue }) => {
	const data = window.wpml_at_setup || {};
    const dashboardUrl = data.dashboard_url || ( ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?page=wpml-auto-dashboard' ) );
    const saved_models = data.saved_models || {};
    const savedCreds = data.saved_credentials || {};

	const [openaiKey, setOpenaiKey] = React.useState( savedCreds.openai_key || '' );
    const [googleKey, setGoogleKey] = React.useState( savedCreds.google_key || '' );
    const [openaiModel, setOpenaiModel] = React.useState( saved_models.openai_model || '' );
    const [googleModel, setGoogleModel] = React.useState( saved_models.google_model || '' );
	const [saving, setSaving] = React.useState( false );
	const [message, setMessage] = React.useState( null );
	const [isError, setIsError] = React.useState( false );   
    const handleSave = async () => {
		setSaving( true );
		setMessage( null );
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
			setMessage( __( 'API keys saved.', 'automl-ai-translation-for-wpml' ) );
			setIsError( false );
			return true;
		} catch ( err ) {
			setMessage( err?.message || __( 'Failed to save. Please try again.', 'automl-ai-translation-for-wpml' ) );
			setIsError( true );
			return false;
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="wpml-at-wizard-card" style={{ maxWidth: 600, margin: '0 auto', padding: 40, minHeight: '40vh' }}>
			<div style={{ flex: 1 }}>
				<h2 style={{ marginTop: 0 }}>{ __( 'AI Translation', 'automl-ai-translation-for-wpml' ) }</h2>
				<p style={{ fontSize: 14, marginBottom: 12 }}>
					{ __( 'AutoML lets you translate content using AI. Add your API keys below; they are saved to the same settings as WPML Auto Translate Settings.', 'automl-ai-translation-for-wpml' ) }
				</p>

				<div style={{ marginBottom: 16 }}>
					<label htmlFor="wpml-at-wizard-openai-key" style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>
						{ __( 'OpenAI API key', 'automl-ai-translation-for-wpml' ) }
					</label>
					<input
						id="wpml-at-wizard-openai-key"
						type="password"
						value={ openaiKey }
						onChange={ ( e ) => setOpenaiKey( e.target.value ) }
						placeholder="sk-..."
						style={{ width: '100%', maxWidth: 400, padding: '8px 12px', fontSize: 14 }}
					/>
				</div>
				<div style={{ marginBottom: 16 }}>
					<label htmlFor="wpml-at-wizard-google-key" style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>
						{ __( 'Google / Gemini API key', 'automl-ai-translation-for-wpml' ) }
					</label>
					<input
						id="wpml-at-wizard-google-key"
						type="password"
						value={ googleKey }
						onChange={ ( e ) => setGoogleKey( e.target.value ) }
						placeholder="..."
						style={{ width: '100%', maxWidth: 400, padding: '8px 12px', fontSize: 14 }}
					/>
				</div>

				{ message && (
					<p style={{ color: isError ? '#b32d2e' : '#00a32a', marginBottom: 12, fontSize: 14 }}>
						{ message }
					</p>
				) }

				<p style={{ fontSize: 14, marginBottom: 24 }}>
					{ __( 'You can also add or change keys and models later in WPML Auto Translate → Settings.', 'automl-ai-translation-for-wpml' ) }
				</p>
			</div>
			<div className="wpml-at-wizard-footer" style={{ display: 'flex', justifyContent: 'space-between', marginTop: 24 }}>
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
						} catch ( e ) {}
						window.location.href = dashboardUrl;
					} }
					label={ __( 'Finish setup', 'automl-ai-translation-for-wpml' ) }
					disabled={ saving }
				/>
			</div>
		</div>
	);
};

export default AiTranslation;