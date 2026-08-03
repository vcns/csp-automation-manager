<?php
/**
 * Admin view: Settings page.
 * Rendered by Admin_UI::render_settings().
 * All output is escaped; form submission handled by WordPress Settings API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$learning_window            = new \WP_CSP\CSP\Learning_Window();
$learning_status            = $learning_window->is_open() ? __( 'Open', 'csp-automation-manager' ) : __( 'Locked', 'csp-automation-manager' );
$current_report_endpoint    = esc_url_raw( rest_url( 'csp-manager/v1/report' ) );
$configured_report_endpoint = (string) get_option( 'wp_csp_report_endpoint_url', '' );
$configured_policy_header   = (string) get_option( 'wp_csp_policy_header_name', '' );
?>
<div class="wrap wp-csp-wrap">
	<h1><?php esc_html_e( 'CSP Automation Manager Settings', 'csp-automation-manager' ); ?></h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'wp_csp_settings_group' ); ?>


		<!-- ── Promotion gates ───────────────────────────────────────────── -->
		<h2 class="title"><?php esc_html_e( 'Promotion Gates', 'csp-automation-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These settings control the conditions that must be met before a surface can be promoted from report-only to enforce mode.', 'csp-automation-manager' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wp_csp_enforce_gate_violation_window">
						<?php esc_html_e( 'Violation-free window (hours)', 'csp-automation-manager' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="wp_csp_enforce_gate_violation_window"
						name="wp_csp_enforce_gate_violation_window"
						value="<?php echo esc_attr( get_option( 'wp_csp_enforce_gate_violation_window', 24 ) ); ?>"
						min="1" max="720" class="small-text" />
					<p class="description">
						<?php esc_html_e( 'Number of hours without any CSP violations required before a surface can be promoted to enforce mode. Default: 24. Increase this for production sites to ensure stability.', 'csp-automation-manager' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Proxy Header Emission', 'csp-automation-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Use this when WordPress is the origin behind Cloudflare, a CDN, or another edge proxy that needs to copy an origin-only header into the browser-facing CSP header.', 'csp-automation-manager' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp_csp_policy_header_name"><?php esc_html_e( 'Policy header name', 'csp-automation-manager' ); ?></label></th>
				<td>
					<input type="text" id="wp_csp_policy_header_name" name="wp_csp_policy_header_name"
						value="<?php echo esc_attr( $configured_policy_header ); ?>"
						placeholder="<?php echo esc_attr( 'X-Origin-CSP-Policy' ); ?>"
						class="regular-text code" />
					<p class="description">
						<?php esc_html_e( 'Leave blank for the plugin to emit the normal mode-aware headers: Content-Security-Policy-Report-Only in report-only mode and Content-Security-Policy in enforce mode. Set a custom HTTP field name to emit the policy under that exact origin header instead, then configure the proxy to copy it back to the appropriate browser-facing CSP header.', 'csp-automation-manager' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Only valid HTTP header field names are accepted. Hop-by-hop and cookie headers are rejected.', 'csp-automation-manager' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Report Endpoint Learning', 'csp-automation-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Validated CSP reports can add pending source candidates while the site is inside the material-change learning window.', 'csp-automation-manager' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp_csp_report_endpoint_url"><?php esc_html_e( 'Reporting server URL', 'csp-automation-manager' ); ?></label></th>
				<td>
					<input type="url" id="wp_csp_report_endpoint_url" name="wp_csp_report_endpoint_url"
						value="<?php echo esc_attr( $configured_report_endpoint ); ?>"
						placeholder="<?php echo esc_attr( $current_report_endpoint ); ?>"
						class="regular-text code" />
					<button type="button" class="button wp-csp-use-current-report-endpoint" data-report-endpoint="<?php echo esc_attr( $current_report_endpoint ); ?>">
						<?php esc_html_e( 'Use current site endpoint', 'csp-automation-manager' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Leave blank to use the current WordPress REST endpoint shown below. Set an absolute public URL when visitors reach the site through a proxy, CDN, load balancer, or different HTTPS host. The URL should still resolve to this plugin report endpoint if you want local report learning.', 'csp-automation-manager' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Detected current endpoint:', 'csp-automation-manager' ); ?>
						<code><?php echo esc_html( $current_report_endpoint ); ?></code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp_csp_learning_window_hours"><?php esc_html_e( 'Learning window (hours)', 'csp-automation-manager' ); ?></label></th>
				<td>
					<input type="number" id="wp_csp_learning_window_hours" name="wp_csp_learning_window_hours"
						value="<?php echo esc_attr( get_option( 'wp_csp_learning_window_hours', 48 ) ); ?>"
						min="1" max="720" class="small-text" />
					<p class="description">
						<?php esc_html_e( 'Default: 48. The report endpoint stops creating or updating source candidates after this many hours from the latest page, post, or plugin change.', 'csp-automation-manager' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Learning status', 'csp-automation-manager' ); ?></th>
				<td>
					<p>
						<?php
						printf(
							/* translators: 1: status, 2: last material change time, 3: lock time */
							esc_html__( '%1$s. Last material change: %2$s. Locks at: %3$s UTC.', 'csp-automation-manager' ),
							'<strong>' . esc_html( $learning_status ) . '</strong>',
							esc_html( $learning_window->last_material_change_at() ),
							esc_html( $learning_window->locks_at() )
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<!-- ── Scan schedule ─────────────────────────────────────────────── -->
		<h2 class="title"><?php esc_html_e( 'Scan Schedule', 'csp-automation-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp_csp_cron_hour"><?php esc_html_e( 'Daily Scan Hour (0–23, UTC)', 'csp-automation-manager' ); ?></label></th>
				<td>
					<input type="number" id="wp_csp_cron_hour" name="wp_csp_cron_hour"
						value="<?php echo esc_attr( get_option( 'wp_csp_cron_hour', 2 ) ); ?>"
						min="0" max="23" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp_csp_notify_email"><?php esc_html_e( 'Notification Email', 'csp-automation-manager' ); ?></label></th>
				<td>
					<input type="email" id="wp_csp_notify_email" name="wp_csp_notify_email"
						value="<?php echo esc_attr( get_option( 'wp_csp_notify_email', get_option( 'admin_email' ) ) ); ?>"
						class="regular-text" />
					<p class="description"><?php esc_html_e( 'Receive an email when the policy changes after a scheduled scan.', 'csp-automation-manager' ); ?></p>
				</td>
			</tr>
		</table>


		<?php submit_button(); ?>
	</form>
</div>
