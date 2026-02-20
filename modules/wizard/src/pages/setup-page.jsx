import React from 'react';
import { __ } from '@wordpress/i18n';
import SetupProgress from '../components/SetupProgress';

const SetupPage = () => {
	const [currentStep, setCurrentStep] = React.useState( 'video_intro' );
	const [showReady, setShowReady] = React.useState( false );

	const data = window.wpml_at_setup || {};
	const dashboardUrl = data.dashboard_url || ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?page=wpml-auto-dashboard' );

	const handleGetStarted = () => {
		setCurrentStep( 'languages' );
	};

	const handleFinish = () => {
		setShowReady( true );
	};

	if ( showReady ) {
		return (
			<div className="wpml-at-wizard-wrap">
				<div className="wpml-at-wizard-card" style={{ maxWidth: 600, margin: '0 auto', padding: 40 }}>
					<h2 style={{ marginTop: 0 }}>{ __( "You're ready to translate with AI", 'automl-ai-translation-for-wpml' ) }</h2>
					<p style={{ color: '#6b7280', marginBottom: 24 }}>
						{ __( "Use the WPML Auto Translate dashboard to translate your posts and strings with AI.", 'automl-ai-translation-for-wpml' ) }
					</p>
					<a href={ dashboardUrl } className="button button-primary button-hero">
						{ __( 'Open WPML Auto Translate', 'automl-ai-translation-for-wpml' ) }
					</a>
				</div>
			</div>
		);
	}

	return (
		<div className="wpml-at-wizard-wrap">
			<h1 style={{ textAlign: 'center', paddingTop: 30, marginBottom: 16 }}>
				{ __( 'AutoML – AI Translation for WPML', 'automl-ai-translation-for-wpml' ) }
			</h1>
			<SetupProgress
				currentStep={ currentStep }
				setCurrentStep={ setCurrentStep }
				onGetStarted={ handleGetStarted }
				onFinish={ handleFinish }
			/>
		</div>
	);
};

export default SetupPage;