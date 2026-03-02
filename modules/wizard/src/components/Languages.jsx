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
		<div
			className="automl-ai-wizard-card"
			style={ {
				maxWidth: 600,
				margin: '0 auto',
				padding: 20,
				minHeight: '40vh',
			} }
		>
			<div className="automl-ai-wizard-language-container" style={ { flex: 1, marginBottom: 20 } }>
				<h2 style={ { marginTop: 0 } }>
					{ __(
						'Translation Languages',
						'automl-ai-translation-for-wpml'
					) }
				</h2>
				<p
					className="automl-ai-wizard-intro"
					style={ { marginBottom: 16 } }
				>
					{ __(
						'Which languages do you want to translate your site into?',
						'automl-ai-translation-for-wpml'
					) }
				</p>

				<label
					htmlFor="automl-ai-wizard-language-select"
					style={ {
						display: 'block',
						marginBottom: 8,
						fontWeight: 500,
					} }
				>
					{ __(
						'Choose a language',
						'automl-ai-translation-for-wpml'
					) }
				</label>
				<p
					className="automl-ai-wizard-intro"
					style={ {
						marginBottom: 8,
						fontSize: 13,
						color: '#6b7280',
					} }
				>
					{ __(
						'Please select a language first. This is required to use AI translation and Settings.',
						'automl-ai-translation-for-wpml'
					) }
				</p>

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
								border: '1px solid #8c8f94',
								borderRadius: 4,
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
  {/* First “Select an option” row */}
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

  {/* Actual language options */}
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
			</div>
			<div
				className="automl-ai-wizard-footer"
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					marginTop: 24,
				} }
			>
				<SetupBackButton onClick={ onBack } />
				<SetupContinueButton
					onClick={ handleContinue }
					label={ __(
						'Continue',
						'automl-ai-translation-for-wpml'
					) }
					disabled={ ! selectedCode }
				/>
			</div>
		</div>
	);
};

export default Languages;