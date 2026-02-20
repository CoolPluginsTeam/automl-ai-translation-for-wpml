import React from 'react';
import { __ } from '@wordpress/i18n';
import VideoIntro from './VideoIntro';
import Languages from './Languages';
import AiTranslation from './AiTranslation';

const STEPS = [
	{ key: 'video_intro', label: __( 'Intro', 'automl-ai-translation-for-wpml' ) },
	{ key: 'languages', label: __( 'Languages', 'automl-ai-translation-for-wpml' ) },
	{ key: 'ai_translation', label: __( 'AI Translation', 'automl-ai-translation-for-wpml' ) },
];

const SetupProgress = ({ currentStep, setCurrentStep, onGetStarted, onFinish }) => {
	const currentIndex = STEPS.findIndex( s => s.key === currentStep );

	return (
		<div className="wpml-at-wizard-progress-wrap" style={{ paddingBottom: 40 }}>
			{ currentStep !== 'video_intro' && (
				<div className="wpml-at-wizard-steps" style={{ marginBottom: 24, display: 'flex', gap: 16, flexWrap: 'wrap', justifyContent: 'center' }}>
					{ STEPS.map( ( step, index ) => {
						const stepIndex = STEPS.findIndex( s => s.key === step.key );
						const isActive = step.key === currentStep;
						const isPast = stepIndex < currentIndex;
						return (
							<span
								key={ step.key }
								style={{
									fontWeight: isActive ? 600 : 400,
									color: isPast ? '#16a34a' : isActive ? '#1d4ed8' : '#6b7280',
								}}
							>
								{ isPast && '✓ ' }
								{ step.label }
							</span>
						);
					} ) }
				</div>
			) }
			<div>
				{ currentStep === 'video_intro' && <VideoIntro onGetStarted={ onGetStarted } /> }
				{ currentStep === 'languages' && (
					<Languages
						onBack={ () => setCurrentStep( 'video_intro' ) }
						onContinue={ () => setCurrentStep( 'ai_translation' ) }
					/>
				) }
				{ currentStep === 'ai_translation' && (
					<AiTranslation
						onBack={ () => setCurrentStep( 'languages' ) }
						onContinue={ onFinish }
					/>
				) }
			</div>
		</div>
	);
};

export default SetupProgress;