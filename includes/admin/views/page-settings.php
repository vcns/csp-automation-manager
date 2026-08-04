<?php
/**
 * Admin view: Settings page.
 * Rendered by Admin_UI::render_settings().
 * All output is escaped; form submission handled by WordPress Settings API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$learning_window            = new \WP_CSP\CSP\Learning_Window();
$learning_status            = $learning_window->is_open() ? __( 'Open', 'csp-automation-manager' ) : __( 'Locked', 'csp-automation-manager' );
$current_report_endpoint    = esc_url_raw( rest_url( 'csp-manager/v1/report' ) );
$configured_report_endpoint = (string) get_option( 'wp_csp_report_endpoint_url', '' );
$configured_policy_header   = (string) get_option( 'wp_csp_policy_header_name', '' );
$reporting_transport        = \WP_CSP\CSP\Policy_Builder::sanitize_reporting_transport( get_option( 'wp_csp_reporting_transport', 'report-uri' ) );
$automation_config          = ( new \WP_CSP\CSP\Automation_Config() )->all();
$automation_surfaces        = \WP_CSP\CSP\Automation_Config::SURFACES;
$automation_mode_labels     = \WP_CSP\CSP\Automation_Config::mode_labels();
$automation_directives      = array( 'default-src', 'img-src', 'font-src', 'media-src', 'manifest-src' );
$automation_schemes         = array( 'https', 'wss' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
$scan_logs_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_scan_logs ORDER BY started_at DESC LIMIT 20", ARRAY_A );
$scan_logs     = ! empty( $scan_logs_raw ) ? $scan_logs_raw : array();
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

		<h2 class="title"><?php esc_html_e( 'Deterministic Automation', 'csp-automation-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure how far the deterministic engine may go without administrator approval. Manual mode remains the default; hard-excluded, critical, unknown, and insufficient-evidence proposals always require review.', 'csp-automation-manager' ); ?>
		</p>
		<table class="widefat striped" role="presentation">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Mode', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Automation enabled', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Maximum per run', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Directive scope', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Allowed schemes', 'csp-automation-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $automation_surfaces as $surface ) : ?>
				<?php $surface_config = $automation_config[ $surface ] ?? \WP_CSP\CSP\Automation_Config::DEFAULT_SURFACE_CONFIG; ?>
				<tr>
					<td><strong><?php echo esc_html( ucfirst( $surface ) ); ?></strong></td>
					<td>
						<select name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][mode]">
							<?php foreach ( $automation_mode_labels as $mode => $label ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $surface_config['mode'], $mode ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="hidden" name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][emergency_disabled]" value="1" />
						<label>
							<input type="checkbox" name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][emergency_disabled]" value="0" <?php checked( empty( $surface_config['emergency_disabled'] ) ); ?> />
							<?php esc_html_e( 'Allow eligible auto-approvals', 'csp-automation-manager' ); ?>
						</label>
					</td>
					<td>
						<input type="number" class="small-text" min="0" max="50" name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][max_automatic_changes_per_scan]" value="<?php echo esc_attr( (string) ( $surface_config['max_automatic_changes_per_scan'] ?? 0 ) ); ?>" />
					</td>
					<td>
						<?php foreach ( $automation_directives as $directive ) : ?>
							<label style="display:block">
								<input type="checkbox" name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][enabled_directives][]" value="<?php echo esc_attr( $directive ); ?>" <?php checked( in_array( $directive, $surface_config['enabled_directives'] ?? array(), true ) ); ?> />
								<code><?php echo esc_html( $directive ); ?></code>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Leave all unchecked to permit any directive that is inside the selected automation posture and not hard-excluded by the deterministic engine.', 'csp-automation-manager' ); ?></p>
					</td>
					<td>
						<?php foreach ( $automation_schemes as $scheme ) : ?>
							<label style="display:block">
								<input type="checkbox" name="wp_csp_automation_config[<?php echo esc_attr( $surface ); ?>][allowed_source_schemes][]" value="<?php echo esc_attr( $scheme ); ?>" <?php checked( in_array( $scheme, $surface_config['allowed_source_schemes'] ?? array(), true ) ); ?> />
								<code><?php echo esc_html( $scheme ); ?></code>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'Automatic decisions are recorded with actor automation_engine and can be reverted from the review queue like administrator approvals.', 'csp-automation-manager' ); ?>
		</p>

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
				<th scope="row"><label for="wp_csp_reporting_transport"><?php esc_html_e( 'Reporting transport', 'csp-automation-manager' ); ?></label></th>
				<td>
					<select id="wp_csp_reporting_transport" name="wp_csp_reporting_transport">
						<option value="report-uri" <?php selected( $reporting_transport, 'report-uri' ); ?>>
							<?php esc_html_e( 'Direct report-uri (recommended)', 'csp-automation-manager' ); ?>
						</option>
						<option value="both" <?php selected( $reporting_transport, 'both' ); ?>>
							<?php esc_html_e( 'Direct report-uri plus Reporting API', 'csp-automation-manager' ); ?>
						</option>
						<option value="report-to" <?php selected( $reporting_transport, 'report-to' ); ?>>
							<?php esc_html_e( 'Reporting API only', 'csp-automation-manager' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Use direct report-uri while learning so browser reports arrive promptly in the Violations tab. Reporting API delivery can be queued or delayed by the browser, and browsers that use report-to may ignore report-uri.', 'csp-automation-manager' ); ?>
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

		<h2 class="title"><?php esc_html_e( 'Scan Log', 'csp-automation-manager' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Review recent manual and scheduled scan runs, policy-change counts, warnings, and completion status after site, theme, plugin, or content changes.', 'csp-automation-manager' ); ?>
		</p>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Trigger', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Sources +/-', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Hashes +/-', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Policy Changed', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Started', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'csp-automation-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $scan_logs as $log ) : ?>
				<?php
				$duration = '';
				if ( ! empty( $log['completed_at'] ) && ! empty( $log['started_at'] ) ) {
					$diff     = strtotime( $log['completed_at'] ) - strtotime( $log['started_at'] );
					$duration = $diff . 's';
				}
				?>
				<tr>
					<td><?php echo esc_html( ucfirst( (string) $log['trigger_type'] ) ); ?></td>
					<td><?php echo esc_html( ucfirst( (string) $log['status'] ) ); ?></td>
					<td>+<?php echo esc_html( (string) $log['sources_added'] ); ?> / -<?php echo esc_html( (string) $log['sources_removed'] ); ?></td>
					<td>+<?php echo esc_html( (string) $log['hashes_added'] ); ?> / -<?php echo esc_html( (string) $log['hashes_removed'] ); ?></td>
					<td><?php echo ! empty( $log['policy_changed'] ) ? esc_html__( 'Yes', 'csp-automation-manager' ) : '&mdash;'; ?></td>
					<td><?php echo esc_html( (string) $log['started_at'] ); ?></td>
					<td><?php echo esc_html( $duration ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $scan_logs ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No scans run yet.', 'csp-automation-manager' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>


		<?php submit_button(); ?>
	</form>
</div>
