<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpml-auto-dashboard-free-vs-pro">
	<div class="wpml-auto-dashboard-free-vs-pro-container">
		<div class="header">
			<h1><?php esc_html_e( 'Free vs Other Solutions', 'wpml-auto-translate-addon' ); ?></h1>
			<div class="wpml-auto-dashboard-status">
				<span class="status"><?php esc_html_e( 'Info', 'wpml-auto-translate-addon' ); ?></span>
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=other_products&utm_content=freevspro' ); ?>"
					class="wpml-auto-dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'See more addons', 'wpml-auto-translate-addon' ); ?>
				</a>
			</div>
		</div>

		<p>
			<?php
			echo esc_html__(
				'Compare what this free addon offers with typical paid auto-translation solutions, to understand where it fits in your workflow.',
				'wpml-auto-translate-addon'
			);
			?>
		</p>

		<table>
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Feature', 'wpml-auto-translate-addon' ); ?></th>
					<th><?php echo esc_html__( 'This Addon (Free)', 'wpml-auto-translate-addon' ); ?></th>
					<th><?php echo esc_html__( 'Typical Paid SaaS', 'wpml-auto-translate-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$wpml_auto_features = array(
					__( 'WPML Translation Dashboard integration', 'wpml-auto-translate-addon' )          => array( true, true ),
					__( 'Uses your own AI/API keys (via AI SDK)', 'wpml-auto-translate-addon' )       => array( true, false ),
					__( 'No per‑word SaaS billing from this addon', 'wpml-auto-translate-addon' )     => array( true, false ),
					__( 'Bulk translation of jobs', 'wpml-auto-translate-addon' )                     => array( true, true ),
					__( 'Preview with Google Website Translator', 'wpml-auto-translate-addon' )       => array( true, false ),
					__( 'Tight WordPress/WPML integration', 'wpml-auto-translate-addon' )             => array( true, true ),
					__( 'Hosted SaaS translation management UI', 'wpml-auto-translate-addon' )        => array( false, true ),
					__( 'Vendor‑managed usage limits & billing', 'wpml-auto-translate-addon' )        => array( false, true ),
					__( 'Dedicated enterprise support SLAs', 'wpml-auto-translate-addon' )            => array( false, true ),
				);

				foreach ( $wpml_auto_features as $feature => $availability ) :
					?>
					<tr>
						<td><?php echo esc_html( $feature ); ?></td>
						<td class="<?php echo $availability[0] ? 'check' : 'cross'; ?>">
							<?php echo $availability[0] ? '✓' : '✗'; ?>
						</td>
						<td class="<?php echo $availability[1] ? 'check' : 'cross'; ?>">
							<?php echo $availability[1] ? '✓' : '✗'; ?>
						</td>
					</tr>
					<?php
				endforeach;
				?>
			</tbody>
		</table>
	</div>
</div>