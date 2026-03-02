import React from 'react';
import { __ } from '@wordpress/i18n';

const VideoIntro = ({ onGetStarted }) => {
	const data = window.wpml_at_setup || {};
	const videoUrl = data.video_url || 'https://www.youtube.com/embed/dst_bf7uiTc';

	return (
		<div className="automl-ai-wizard-card" style={{ maxWidth: 600, margin: '12px auto', padding: 20, minHeight: '38vh' }}>
			<div style={{ textAlign: 'center', marginBottom: 24 }}>
				<h3 className="automl-ai-wizard-card h2" style={{ fontSize: '1.5rem', marginBottom: 12 }}>
					{ __( 'Watch Setup Guide', 'automl-ai-translation-for-wpml' ) }
				</h3>
				<p style={{ color: '#6b7280', marginBottom: 24 }}>
					{ __( 'Learn how to configure AutoML for AI translation with WPML.', 'automl-ai-translation-for-wpml' ) }
				</p>
			</div>
			<div style={{ position: 'relative', width: '100%', paddingBottom: '56.25%', marginBottom: 24 }}>
				<iframe
					title={ __( 'AutoML Setup Guide', 'automl-ai-translation-for-wpml' ) }
					style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', borderRadius: 8 }}
					src={ videoUrl }
					frameBorder="0"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowFullScreen
				/>
			</div>
			<div style={{ display: 'flex', justifyContent: 'center', paddingTop: 16 }}>
				<button type="button" className="button button-primary button-hero" onClick={ onGetStarted }>
					{ __( 'Get Started', 'automl-ai-translation-for-wpml' ) }
				</button>
			</div>
		</div>
	);
};

export default VideoIntro;