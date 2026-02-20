import { __ } from '@wordpress/i18n';

export default function SetupContinueButton({ onClick, label }) {
	return (
		<button
			type="button"
			className="button button-primary"
			onClick={ onClick }
			style={{ minWidth: 100 }}
		>
			{ label || __( 'Continue', 'automl-ai-translation-for-wpml' ) }
		</button>
	);
}

export function SetupBackButton({ onClick }) {
	return (
		<button
			type="button"
			className="button"
			onClick={ onClick }
		>
			{ __( 'Back', 'automl-ai-translation-for-wpml' ) }
		</button>
	);
}