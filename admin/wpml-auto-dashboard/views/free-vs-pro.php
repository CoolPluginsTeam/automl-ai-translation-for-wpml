<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-free-vs-pro">
	<div class="wpml-auto-dashboard-free-vs-pro-container">
		<div class="header">
			<h1><?php esc_html_e( 'Free vs Other Solutions', 'automl-ai-translation-for-wpml' ); ?></h1>
			<div class="wpml-auto-dashboard-status">
				<span class="status"><?php esc_html_e( 'Info', 'automl-ai-translation-for-wpml' ); ?></span>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=freevspro' ); ?>"
					class="wpml-auto-dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'See more addons', 'automl-ai-translation-for-wpml' ); ?>
				</a>
			</div>
		</div>

		<p>
			<?php
			echo esc_html__(
				'Compare what this free addon offers with typical paid auto-translation solutions, to understand where it fits in your workflow.',
				'automl-ai-translation-for-wpml'
			);
			?>
		</p>

		<table>
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Feature', 'automl-ai-translation-for-wpml' ); ?></th>
					<th><?php echo esc_html__( 'This Addon (Free)', 'automl-ai-translation-for-wpml' ); ?></th>
					<th><?php echo esc_html__( 'Typical Paid SaaS', 'automl-ai-translation-for-wpml' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$automl_wpml_features = array(
					__( 'WPML Translation Dashboard integration', 'automl-ai-translation-for-wpml' )          => array( true, true ),
					__( 'Uses your own AI/API keys (via AI SDK)', 'automl-ai-translation-for-wpml' )       => array( true, false ),
					__( 'No per‑word SaaS billing from this addon', 'automl-ai-translation-for-wpml' )     => array( true, false ),
					__( 'Bulk translation of jobs', 'automl-ai-translation-for-wpml' )                     => array( true, true ),
					__( 'Preview with Google Website Translator', 'automl-ai-translation-for-wpml' )       => array( true, false ),
					__( 'Tight WordPress/WPML integration', 'automl-ai-translation-for-wpml' )             => array( true, true ),
					__( 'Hosted SaaS translation management UI', 'automl-ai-translation-for-wpml' )        => array( false, true ),
					__( 'Vendor‑managed usage limits & billing', 'automl-ai-translation-for-wpml' )        => array( false, true ),
					__( 'Dedicated enterprise support SLAs', 'automl-ai-translation-for-wpml' )            => array( false, true ),
				);

				foreach ( $automl_wpml_features as $automl_wpml_feature => $automl_wpml_availability ) :
					?>
					<tr>
						<td><?php echo esc_html( $automl_wpml_feature ); ?></td>
						<td class="<?php echo $automl_wpml_availability[0] ? 'check' : 'cross'; ?>">
							<?php echo $automl_wpml_availability[0] ? '✓' : '✗'; ?>
						</td>
						<td class="<?php echo $automl_wpml_availability[1] ? 'check' : 'cross'; ?>">
							<?php echo $automl_wpml_availability[1] ? '✓' : '✗'; ?>
						</td>
					</tr>
					<?php
				endforeach;
				?>
			</tbody>
		</table>
	</div>
</div>