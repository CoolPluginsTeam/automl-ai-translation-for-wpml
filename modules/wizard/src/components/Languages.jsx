import React from 'react';
import { Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getNonce } from '../utils';
import SetupContinueButton, { SetupBackButton } from './SetupContinueButton';

const Languages = ( { onBack, onContinue } ) => {
	const data = window.wpml_at_setup || {};
	const defaultCode = ( data.default_language || '' ).toLowerCase();
	const allLanguages = Array.isArray( data.wpml_languages ) ? data.wpml_languages : [];
	const wpmlLanguages = defaultCode
		? allLanguages.filter(
				( lang ) => ( lang.code || '' ).toLowerCase() !== defaultCode
		  )
		: allLanguages;

	const savedCode =
		data.saved_language && data.saved_language.code
			? data.saved_language.code
			: '';
	const [ selectedCode, setSelectedCode ] = React.useState( savedCode );

	// Options for dropdown
	const languageOptions = wpmlLanguages.map( ( lang ) => ( {
		value: lang.code,
		label: lang.name || lang.code,
	} ) );

	const selectedLang =
		wpmlLanguages.find(
			( lang ) => ( lang.code || '' ) === selectedCode
		) || null;

	const selectedLabel =
		selectedLang?.name ||
		selectedLang?.code ||
		__( 'Select an option', 'automl-ai-translation-for-wpml' );

	const handleContinue = async () => {
		if ( selectedCode ) {
			const selectedLangObj = wpmlLanguages.find(
				( lang ) => ( lang.code || '' ) === selectedCode
			);
			const payload = selectedLangObj
				? {
						selected_language: {
							code: selectedLangObj.code,
							name:
								selectedLangObj.name || selectedLangObj.code,
							flag_url: selectedLangObj.flag_url,
							locale: selectedLangObj.locale,
						},
				  }
				: {
						selected_language: {
							code: selectedCode,
							name: '',
							flag_url: '',
						},
				  };
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
		<>
			<div
				className="automl-ai-wizard-card"
				style={ {
					maxWidth: 600,
					margin: '0 auto',
					minHeight: '40vh',
					background: '#fff',
				} }
			>
				<div className="automl-ai-wizard-language-container" style={ { flex: 1, marginBottom: 20 } }>
					<h2 style={ { marginTop: 0 } }>
						{ __(
							'Select Language for AI Translation',
							'automl-ai-translation-for-wpml'
						) }
					</h2>
					<p
						className="automl-ai-wizard-intro"
						style={ { color: '#6b7280' } }
					>
						{ __(
							'Choose your website language you want to translate using AI. The free version of AutoML allows AI translation for one language only.',
							'automl-ai-translation-for-wpml'
						) }
					</p>

					<label
						htmlFor="automl-ai-wizard-language-select"
						style={ {
							display: 'block',
							fontSize: '.85rem',
							margin: '25px 0 8px 0',
							fontWeight: 500,
							color: '#333',
						} }
					>
						{ __(
							'Choose a language',
							'automl-ai-translation-for-wpml'
						) }
					</label>

					<Dropdown
						popoverProps={ {
							className: 'automl-ai-wizard-language-dropdown',
						} }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<button
								type="button"
								id="automl-ai-wizard-language-select"
								onClick={ onToggle }
								className="automl-ai-wizard-select-toggle"
								style={ {
									width: '100%',
									maxWidth: 400,
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'space-between',
									padding: '8px 12px',
									fontSize: 14,
									border: '1px solid #d1d5db',
									borderRadius: 6,
									background: '#fff',
									cursor: 'pointer',
								} }
								aria-expanded={ isOpen }
							>
								<span>{ selectedLabel }</span>
								<span
									aria-hidden="true"
									style={ {
										marginLeft: 8,
										fontSize: 20,
										transition: 'transform 0.15s ease',
										transform: isOpen
											? 'rotate(180deg)'
											: 'rotate(0deg)',
									} }
								>
									▾
								</span>
							</button>
						) }
						renderContent={ ( { onClose } ) => (
							<div className="automl-ai-wizard-language-menu">
								<div
									className={
										'automl-ai-wizard-language-option' +
										( ! selectedCode
											? ' automl-ai-wizard-language-option--selected'
											: '' )
									}
									onClick={ () => {
										setSelectedCode( '' );
										onClose();
									} }
								>
									{ __( 'Select an option', 'automl-ai-translation-for-wpml' ) }
								</div>

								{ languageOptions.map( ( opt ) => (
									<div
										key={ opt.value }
										className={
											'automl-ai-wizard-language-option' +
											( selectedCode === opt.value
												? ' automl-ai-wizard-language-option--selected'
												: '' )
										}
										onClick={ () => {
											setSelectedCode( opt.value );
											onClose();
										} }
									>
										{ opt.label }
									</div>
								) ) }
							</div>
						) }
					/>

					{ wpmlLanguages.length === 0 && (
						<p
							style={ {
								marginTop: 12,
								color: '#b32d2e',
								fontSize: 14,
							} }
						>
							{ __(
								'No languages found. Add languages in WPML → Languages first.',
								'automl-ai-translation-for-wpml'
							) }
						</p>
					) }
					<div className="automl-ai-wizard-card-language-footer">
					<span
						className="automl-ai-wizard-card-language-footer-icon"
						aria-hidden="true"
					>
						<img
							src={
								(data.home_url || '') +
								'/wp-content/plugins/automl-ai-translation-for-wpml/assets/images/star-icons.png'
							}
							alt=""
							width={18}
							height={18}
							style={{ display: 'block' }}
						/>
					</span>
					<div className="automl-ai-wizard-card-language-footer-content">
						<p>
							{ __(
								'Translate all languages using AI - Have a website in multiple languages and want to translate them all using AI?',
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
				</div>

				<div className="automl-ai-wizard-footer" style={ { marginTop: 24 } }>
					<SetupBackButton onClick={ onBack } />
					<SetupContinueButton
						onClick={ handleContinue }
						label={ __( 'Continue', 'automl-ai-translation-for-wpml' ) }
						disabled={ ! selectedCode }
					/>
				</div>
			</div>

			<div className="automl-ai-wizard-card-footer">
				{ __( 'Need help? Visit our', 'automl-ai-translation-for-wpml' ) }{ ' ' }
				<a
					href={ data.doc_url || 'https://docs.coolplugins.net/' }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Documentation', 'automl-ai-translation-for-wpml' ) }
				</a>
			</div>
		</>
	);
};

export default Languages;