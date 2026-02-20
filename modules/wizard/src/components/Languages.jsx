import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getNonce } from '../utils';
import SetupContinueButton, { SetupBackButton } from './SetupContinueButton';

const Languages = ({ onBack, onContinue }) => {
	const data = window.wpml_at_setup || {};
	const defaultCode = ( data.default_language || '' ).toLowerCase();
	const allLanguages = Array.isArray( data.wpml_languages ) ? data.wpml_languages : [];
	const wpmlLanguages = defaultCode
		? allLanguages.filter( ( lang ) => ( lang.code || '' ).toLowerCase() !== defaultCode )
		: allLanguages;

        const savedCode = ( data.saved_language && data.saved_language.code ) ? data.saved_language.code : '';
        const [selectedCode, setSelectedCode] = React.useState( savedCode );

	const handleContinue = async () => {
		if ( selectedCode ) {
			const selectedLang = wpmlLanguages.find( ( lang ) => ( lang.code || '' ) === selectedCode );
			const payload = selectedLang
				? {
						selected_language: {
							code: selectedLang.code,
							name: selectedLang.name || selectedLang.code,
							flag_url: selectedLang.flag_url,
                            locale: selectedLang.locale,
						},
				  }
				: { selected_language: { code: selectedCode, name: '', flag_url: '' } };
			try {
				await apiFetch( {
					path: 'automl-bulk-translate/wizard-save-language',
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': getNonce(),
					},
					body: JSON.stringify( payload ),
				} );
			} catch ( err ) {
				// Continue anyway
			}
		}
		onContinue();
	};

	return (
		<div className="wpml-at-wizard-card" style={{ maxWidth: 600, margin: '0 auto', padding: 40, minHeight: '40vh' }}>
			<div style={{ flex: 1, marginBottom: 20 }}>
				<h2 style={{ marginTop: 0 }}>{ __( 'Translation Languages', 'automl-ai-translation-for-wpml' ) }</h2>
				<p className="wpml-at-wizard-intro" style={{ marginBottom: 16 }}>
					{ __( 'Which languages do you want to translate your site into?', 'automl-ai-translation-for-wpml' ) }
				</p>
				<p className="wpml-at-wizard-intro" style={{ marginBottom: 16 }}>
					{ __( 'Select a language from the list below. Only languages you have added in WPML are shown.', 'automl-ai-translation-for-wpml' ) }
				</p>

				<label htmlFor="wpml-at-wizard-language-select" style={{ display: 'block', marginBottom: 8, fontWeight: 500 }}>
					{ __( 'Choose a language', 'automl-ai-translation-for-wpml' ) }
				</label>
				<select
					id="wpml-at-wizard-language-select"
					value={ selectedCode }
					onChange={ ( e ) => setSelectedCode( e.target.value ) }
					className="wpml-at-wizard-select"
					style={{
						width: '100%',
						maxWidth: 400,
						padding: '8px 12px',
						fontSize: 14,
						border: '1px solid #8c8f94',
						borderRadius: 4,
						background: '#fff',
					}}
				>
					<option value="">
						{ __( 'Select an option', 'automl-ai-translation-for-wpml' ) }
					</option>
					{ wpmlLanguages.map( ( lang ) => (
						<option key={ lang.code } value={ lang.code }>
							{ lang.name || lang.code }
						</option>
					) ) }
				</select>

				{ wpmlLanguages.length === 0 && (
					<p style={{ marginTop: 12, color: '#b32d2e', fontSize: 14 }}>
						{ __( 'No languages found. Add languages in WPML → Languages first.', 'automl-ai-translation-for-wpml' ) }
					</p>
				) }
			</div>
			<div className="wpml-at-wizard-footer" style={{ display: 'flex', justifyContent: 'space-between', marginTop: 24 }}>
				<SetupBackButton onClick={ onBack } />
				<SetupContinueButton onClick={ handleContinue } label={ __( 'Continue', 'automl-ai-translation-for-wpml' ) } />
			</div>
		</div>
	);
};

export default Languages;