<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-left-section">

	<div class="wpml-auto-dashboard-get-started">
		<div class="wpml-auto-dashboard-get-started-container">
			<h3><?php echo esc_html__( 'Get Started', 'wpml-auto-translate-addon' ); ?></h3>

			<div class="wpml-auto-dashboard-get-started-grid">
				<div class="wpml-auto-dashboard-get-started-grid-content">
					<h2><?php echo esc_html__( 'Automate the Translation Process :-', 'wpml-auto-translate-addon' ); ?></h2>
					<iframe
						title="Automate the Translation Process with WPML Auto Translate Addon"
						src="https://www.youtube.com/embed/ecHsOyIL_J4?feature=oembed"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe>
					<ul>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Go to %1$sWPML > Translation Management%2$s in your WordPress dashboard. Choose the content you want to translate.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Use the %1$sWPML translation dashboard%2$s to select jobs and send them for automatic translation.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li><?php echo esc_html__( 'Select Google Translate as your translation engine through this addon.', 'wpml-auto-translate-addon' ); ?></li>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Click the %1$sTranslate%2$s button. The addon will automatically generate translations using Google.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Review the translated content, make any manual edits if needed, then click %1$sSave%2$s.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
					</ul>
				</div>
			</div>

			<div class="wpml-auto-dashboard-get-started-grid">
				<div class="wpml-auto-dashboard-get-started-grid-content">
					<h2><?php echo esc_html__( 'Preview with Google Website Translator :-', 'wpml-auto-translate-addon' ); ?></h2>
					<iframe
						title="Preview Translations with Google Website Translator"
						src="https://www.youtube.com/embed/bmmc-Ynwj8w?feature=oembed"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe>
					<ul>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Enable the %1$sGoogle Website Translator widget%2$s from this addon’s settings.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'Use the widget on the front-end to quickly preview how your site looks in different languages.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li><?php echo esc_html__( 'Compare preview translations with saved WPML translations to refine your content.', 'wpml-auto-translate-addon' ); ?></li>
						<li>
							<?php
							// translators: 1: strong tag, 2: strong tag.
							echo sprintf(
								esc_html__(
									'When you are satisfied with the results, update your WPML translation jobs accordingly.',
									'wpml-auto-translate-addon'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
					</ul>
				</div>
			</div>

		</div>
	</div>

</div>