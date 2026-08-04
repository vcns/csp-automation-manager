<?php
/**
 * Admin view: CSP Automation Manager dashboard.
 * Shows per-surface policy profiles, violations, source review, and policy changes.
 * Rendered by Admin_UI::render_dashboard().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Current tab.
$tab          = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'violations';
$allowed_tabs = array( 'violations', 'sources', 'policy-changes' );
if ( ! in_array( $tab, $allowed_tabs, true ) ) {
	$tab = 'violations';
}

$base_url = admin_url( 'admin.php?page=csp-automation-manager-dashboard' );
$tab_help = array(
	'violations'     => array(
		'label'       => __( 'Violations', 'csp-automation-manager' ),
		'description' => __( 'Review browser-submitted CSP reports. Use these reports to identify required sources before promoting a surface from report-only to enforce mode.', 'csp-automation-manager' ),
	),
	'sources'        => array(
		'label'       => __( 'For Review', 'csp-automation-manager' ),
		'description' => __( 'Review discovered source candidates and decide whether each source belongs in the policy. Discovery adds review items; approvals, rejections, reversions, and undo actions require a reason and are written to the decision ledger.', 'csp-automation-manager' ),
	),
	'policy-changes' => array(
		'label'       => __( 'Policy Changes', 'csp-automation-manager' ),
		'description' => __( 'Inspect policy activity across discovered proposals, administrator or automation decisions, and immutable policy snapshots.', 'csp-automation-manager' ),
	),
);

// ── Data queries ──────────────────────────────────────────────────────────────
$surfaces = array( 'frontend', 'admin', 'login', 'api' );

// Shared pagination defaults.
$per_page = 20;
$page_num = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
$offset   = ( $page_num - 1 ) * $per_page;

// Violations – last 50.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$violations_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_violation_reports ORDER BY reported_at DESC LIMIT 50", ARRAY_A );
$violations     = ! empty( $violations_raw ) ? $violations_raw : array();

?>
<div class="wrap wp-csp-wrap">
	<h1><?php esc_html_e( 'CSP Automation Manager Dashboard', 'csp-automation-manager' ); ?></h1>

	<!-- ── Top action bar ────────────────────────────────────────────────── -->
	<p>
		<button type="button" id="wp-csp-manual-scan" class="button button-primary">
			<?php esc_html_e( 'Run Manual Scan', 'csp-automation-manager' ); ?>
		</button>
		<span id="wp-csp-scan-status" style="margin-left:10px;display:none"></span>
	</p>

	<!-- ── Tabs ──────────────────────────────────────────────────────────── -->
	<nav class="nav-tab-wrapper wp-csp-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'CSP dashboard sections', 'csp-automation-manager' ); ?>">
		<?php foreach ( $tab_help as $tab_key => $tab_data ) : ?>
		<a class="nav-tab<?php echo $tab_key === $tab ? ' nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, $base_url ) ); ?>"
			role="tab"
			title="<?php echo esc_attr( $tab_data['description'] ); ?>"
			aria-describedby="wp-csp-tab-help-<?php echo esc_attr( $tab_key ); ?>"
			<?php echo $tab_key === $tab ? 'aria-selected="true" aria-current="page"' : 'aria-selected="false"'; ?>>
			<?php echo esc_html( $tab_data['label'] ); ?>
			<span class="screen-reader-text" id="wp-csp-tab-help-<?php echo esc_attr( $tab_key ); ?>">
				<?php echo esc_html( $tab_data['description'] ); ?>
			</span>
		</a>
		<?php endforeach; ?>
	</nav>
	<div class="wp-csp-tab-help" role="note">
		<strong><?php echo esc_html( $tab_help[ $tab ]['label'] ); ?>:</strong>
		<?php echo esc_html( $tab_help[ $tab ]['description'] ); ?>
	</div>

	<div class="tab-content" style="margin-top:1em">

	<?php if ( 'sources' === $tab ) : ?>
	<!-- ── Sources tab ────────────────────────────────────────────────────── -->
		<?php
		$src_surface = isset( $_GET['src_surface'] ) ? sanitize_text_field( wp_unslash( $_GET['src_surface'] ) ) : '';
		$src_state   = isset( $_GET['src_state'] ) ? sanitize_text_field( wp_unslash( $_GET['src_state'] ) ) : '';
		$src_risk    = isset( $_GET['src_risk'] ) ? sanitize_text_field( wp_unslash( $_GET['src_risk'] ) ) : '';

		$src_where = array( '1=1' );
		$src_args  = array();
		if ( $src_surface ) {
			$src_where[] = 'surface = %s';
			$src_args[]  = $src_surface;
		}
		if ( $src_state ) {
			$src_where[] = 'approval_state = %s';
			$src_args[]  = $src_state;
		}
		if ( $src_risk ) {
			$src_where[] = 'risk_level = %s';
			$src_args[]  = $src_risk;
		}

		$src_where_sql = implode( ' AND ', $src_where );
		$count_sql     = "SELECT COUNT(*) FROM {$wpdb->prefix}csp_source_inventory WHERE {$src_where_sql}";
		if ( ! empty( $src_args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, ...$src_args );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$src_total = (int) $wpdb->get_var( $count_sql );

		$src_pages = max( 1, (int) ceil( $src_total / $per_page ) );
		$page_num  = min( max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) ), $src_pages );
		$offset    = ( $page_num - 1 ) * $per_page;

		$query_args = array_merge( $src_args, array( $per_page, $offset ) );
		$data_sql   = "SELECT * FROM {$wpdb->prefix}csp_source_inventory WHERE {$src_where_sql} ORDER BY CASE risk_level WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, last_seen_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data_sql = $wpdb->prepare( $data_sql, ...$query_args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$sources_raw = $wpdb->get_results( $data_sql, ARRAY_A );
		$sources     = ! empty( $sources_raw ) ? $sources_raw : array();
		?>
	<form method="get" action="">
		<input type="hidden" name="page" value="csp-automation-manager-dashboard" />
		<input type="hidden" name="tab"  value="sources" />
		<select name="src_surface">
			<option value=""><?php esc_html_e( 'All surfaces', 'csp-automation-manager' ); ?></option>
			<?php foreach ( $surfaces as $s ) : ?>
			<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $src_surface, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="src_state">
			<option value=""><?php esc_html_e( 'All states', 'csp-automation-manager' ); ?></option>
			<?php foreach ( array( 'pending', 'approved', 'denied' ) as $st ) : ?>
			<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $src_state, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="src_risk">
			<option value=""><?php esc_html_e( 'All risk levels', 'csp-automation-manager' ); ?></option>
			<?php foreach ( array( 'high', 'medium', 'low' ) as $risk ) : ?>
			<option value="<?php echo esc_attr( $risk ); ?>" <?php selected( $src_risk, $risk ); ?>><?php echo esc_html( ucfirst( $risk ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Filter', 'csp-automation-manager' ), 'secondary', 'filter_sources', false ); ?>
	</form>

	<table class="widefat fixed striped" style="margin-top:1em">
		<thead>
			<tr>
				<th style="width:80px"><?php esc_html_e( 'ID', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Host', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Risk', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'State', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Evidence', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Last Seen', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'csp-automation-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $sources as $src ) : ?>
		<tr>
			<td><?php echo esc_html( $src['id'] ); ?></td>
			<td><?php echo esc_html( $src['surface'] ); ?></td>
			<td><code><?php echo esc_html( $src['directive'] ); ?></code></td>
			<td><code><?php echo esc_html( $src['source_host'] ); ?></code></td>
			<td>
				<span class="wp-csp-risk-badge risk-<?php echo esc_attr( $src['risk_level'] ?? 'low' ); ?>" title="<?php echo esc_attr( $src['risk_reason'] ?? '' ); ?>">
					<?php echo esc_html( ucfirst( $src['risk_level'] ?? 'low' ) ); ?>
				</span>
			</td>
			<td>
				<span class="wp-csp-state-badge state-<?php echo esc_attr( $src['approval_state'] ); ?>">
					<?php echo esc_html( ucfirst( $src['approval_state'] ) ); ?>
				</span>
			</td>
			<td><?php echo esc_html( number_format( (int) ( $src['evidence_count'] ?? 1 ) ) ); ?></td>
			<td><?php echo esc_html( $src['last_seen_at'] ); ?></td>
			<td class="wp-csp-source-actions">
				<?php if ( 'pending' === $src['approval_state'] || 'denied' === $src['approval_state'] ) : ?>
				<button type="button" class="button button-small wp-csp-approve-source" data-id="<?php echo esc_attr( $src['id'] ); ?>">
					<?php esc_html_e( 'Approve', 'csp-automation-manager' ); ?>
				</button>
				<?php endif; ?>
				<?php if ( 'pending' === $src['approval_state'] || 'approved' === $src['approval_state'] ) : ?>
				<button type="button" class="button button-small wp-csp-deny-source" data-id="<?php echo esc_attr( $src['id'] ); ?>">
					<?php esc_html_e( 'Reject', 'csp-automation-manager' ); ?>
				</button>
				<?php endif; ?>
				<?php if ( 'approved' === $src['approval_state'] ) : ?>
				<button type="button" class="button button-small wp-csp-revert-source" data-id="<?php echo esc_attr( $src['id'] ); ?>">
					<?php esc_html_e( 'Revert', 'csp-automation-manager' ); ?>
				</button>
				<?php endif; ?>
				<?php if ( in_array( $src['last_decision'] ?? '', array( 'approved', 'auto_approved', 'rejected' ), true ) ) : ?>
				<button type="button" class="button button-small wp-csp-undo-source-decision" data-id="<?php echo esc_attr( $src['id'] ); ?>">
					<?php esc_html_e( 'Undo', 'csp-automation-manager' ); ?>
				</button>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		<?php if ( empty( $sources ) ) : ?>
		<tr><td colspan="9"><?php esc_html_e( 'No sources discovered yet. Run a scan to populate this table.', 'csp-automation-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

		<?php if ( $src_pages > 1 ) : ?>
	<div class="tablenav bottom" style="margin-top:1em">
		<div class="tablenav-pages">
			<?php
				$src_page_args = array_filter(
					array(
						'tab'         => 'sources',
						'src_surface' => $src_surface,
						'src_state'   => $src_state,
						'src_risk'    => $src_risk,
					)
				);
			?>
			<?php if ( $page_num > 1 ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $src_page_args, array( 'paged' => $page_num - 1 ) ), $base_url ) ); ?>">
				&laquo; <?php esc_html_e( 'Previous', 'csp-automation-manager' ); ?>
			</a>
			<?php endif; ?>
			<span style="margin:0 8px">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'csp-automation-manager' ),
					$page_num,
					$src_pages
				);
				?>
			</span>
			<?php if ( $page_num < $src_pages ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $src_page_args, array( 'paged' => $page_num + 1 ) ), $base_url ) ); ?>">
				<?php esc_html_e( 'Next', 'csp-automation-manager' ); ?> &raquo;
			</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php elseif ( 'policy-changes' === $tab ) : ?>
	<!-- Policy Changes tab -->
		<?php
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$policy_decisions_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_policy_change_decisions ORDER BY created_at DESC LIMIT 100", ARRAY_A );
		$policy_decisions     = ! empty( $policy_decisions_raw ) ? $policy_decisions_raw : array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$policy_versions_raw = $wpdb->get_results( "SELECT id, surface, version_number, mode, trigger_type, trigger_id, software_version, created_at FROM {$wpdb->prefix}csp_policy_versions ORDER BY created_at DESC LIMIT 100", ARRAY_A );
		$policy_versions     = ! empty( $policy_versions_raw ) ? $policy_versions_raw : array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$policy_audit_raw = $wpdb->get_results( "SELECT id, component, event, detail, severity, user_id, created_at FROM {$wpdb->prefix}csp_audit_log WHERE component = 'policy_change' AND event IN ('source_proposed', 'proposal_suppressed') ORDER BY created_at DESC LIMIT 100", ARRAY_A );
		$policy_audit     = ! empty( $policy_audit_raw ) ? $policy_audit_raw : array();
		$policy_events    = array();

		foreach ( $policy_decisions as $decision ) {
			$policy_events[] = array(
				'created_at'     => (string) $decision['created_at'],
				'event'          => ucfirst( (string) $decision['action'] ),
				'type'           => __( 'Decision', 'csp-automation-manager' ),
				'actor'          => (string) ( $decision['actor_type'] ?? 'administrator' ),
				'surface'        => (string) $decision['surface'],
				'directive'      => (string) $decision['directive'],
				'source'         => (string) $decision['source_host'],
				'risk_level'     => (string) $decision['risk_level'],
				'risk_reason'    => (string) $decision['risk_reason'],
				'policy_version' => ! empty( $decision['policy_version_id'] ) ? (string) $decision['policy_version_id'] : '',
				'suppression'    => ! empty( $decision['suppression_active'] ) ? __( 'Active', 'csp-automation-manager' ) : '',
				'detail'         => (string) $decision['reason'],
			);
		}

		foreach ( $policy_versions as $version ) {
			$policy_events[] = array(
				'created_at'     => (string) $version['created_at'],
				'event'          => __( 'Snapshot', 'csp-automation-manager' ),
				'type'           => __( 'Policy version', 'csp-automation-manager' ),
				'actor'          => 'decision' === (string) $version['trigger_type'] ? __( 'system', 'csp-automation-manager' ) : (string) $version['trigger_type'],
				'surface'        => (string) $version['surface'],
				'directive'      => '',
				'source'         => '',
				'risk_level'     => '',
				'risk_reason'    => '',
				'policy_version' => (string) $version['version_number'],
				'suppression'    => '',
				'detail'         => sprintf(
					/* translators: 1: policy mode, 2: trigger type, 3: trigger identifier */
					__( 'Captured %1$s policy snapshot from %2$s trigger %3$s.', 'csp-automation-manager' ),
					(string) $version['mode'],
					(string) $version['trigger_type'],
					! empty( $version['trigger_id'] ) ? '#' . (string) $version['trigger_id'] : __( 'without an ID', 'csp-automation-manager' )
				),
			);
		}

		foreach ( $policy_audit as $audit_event ) {
			$policy_events[] = array(
				'created_at'     => (string) $audit_event['created_at'],
				'event'          => 'proposal_suppressed' === (string) $audit_event['event'] ? __( 'Suppressed proposal', 'csp-automation-manager' ) : __( 'Proposed source', 'csp-automation-manager' ),
				'type'           => __( 'Discovery', 'csp-automation-manager' ),
				'actor'          => empty( $audit_event['user_id'] ) ? __( 'system', 'csp-automation-manager' ) : __( 'administrator', 'csp-automation-manager' ),
				'surface'        => '',
				'directive'      => '',
				'source'         => '',
				'risk_level'     => '',
				'risk_reason'    => '',
				'policy_version' => '',
				'suppression'    => 'proposal_suppressed' === (string) $audit_event['event'] ? __( 'Active', 'csp-automation-manager' ) : '',
				'detail'         => (string) $audit_event['detail'],
			);
		}

		usort(
			$policy_events,
			static function ( array $a, array $b ): int {
				return strcmp( $b['created_at'], $a['created_at'] );
			}
		);
		$policy_events = array_slice( $policy_events, 0, 100 );
		?>
	<p class="description">
		<?php esc_html_e( 'Discovered candidates appear in For Review first. This timeline shows proposal activity, administrator or automation decisions, suppression state, and the policy snapshots created after material changes.', 'csp-automation-manager' ); ?>
	</p>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'When', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Event', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Type', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Actor', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Host', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Risk', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Policy Version', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Suppression', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Detail', 'csp-automation-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $policy_events as $event ) : ?>
		<tr>
			<td><?php echo esc_html( $event['created_at'] ); ?></td>
			<td><?php echo esc_html( $event['event'] ); ?></td>
			<td><?php echo esc_html( $event['type'] ); ?></td>
			<td><?php echo esc_html( $event['actor'] ); ?></td>
			<td><?php echo '' !== $event['surface'] ? esc_html( $event['surface'] ) : '&mdash;'; ?></td>
			<td>
				<?php if ( '' !== $event['directive'] ) : ?>
					<code><?php echo esc_html( $event['directive'] ); ?></code>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td>
				<?php if ( '' !== $event['source'] ) : ?>
					<code><?php echo esc_html( $event['source'] ); ?></code>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td>
				<?php if ( '' !== $event['risk_level'] ) : ?>
				<span class="wp-csp-risk-badge risk-<?php echo esc_attr( $event['risk_level'] ); ?>" title="<?php echo esc_attr( $event['risk_reason'] ); ?>">
					<?php echo esc_html( ucfirst( $event['risk_level'] ) ); ?>
				</span>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
			<td><?php echo '' !== $event['policy_version'] ? esc_html( $event['policy_version'] ) : '&mdash;'; ?></td>
			<td>
				<?php echo '' !== $event['suppression'] ? esc_html( $event['suppression'] ) : '&mdash;'; ?>
			</td>
			<td><?php echo esc_html( $event['detail'] ); ?></td>
		</tr>
		<?php endforeach; ?>
		<?php if ( empty( $policy_events ) ) : ?>
		<tr><td colspan="11"><?php esc_html_e( 'No policy activity has been recorded yet.', 'csp-automation-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php elseif ( 'violations' === $tab ) : ?>
	<!-- ── Violations tab ─────────────────────────────────────────────────── -->
		<?php
		$viol_page_num       = max( 1, (int) ( isset( $_GET['v_paged'] ) ? $_GET['v_paged'] : 1 ) );
		$report_endpoint_url = (string) get_option( 'wp_csp_report_endpoint_url', '' );
		if ( '' === trim( $report_endpoint_url ) ) {
			$report_endpoint_url = rest_url( 'csp-manager/v1/report' );
		}
		$canonicalise_violation_source = static function ( array $violation ): array {
			$blocked_uri = trim( (string) ( $violation['blocked_uri'] ?? '' ) );
			if ( '' === $blocked_uri ) {
				return array(
					'display'  => '',
					'group'    => '',
					'evidence' => '',
				);
			}

			$lower_blocked_uri = strtolower( $blocked_uri );
			if (
				in_array( $lower_blocked_uri, array( 'inline', 'eval', 'wasm-eval', 'data', 'blob', 'about' ), true )
				|| str_starts_with( $lower_blocked_uri, 'data:' )
				|| str_starts_with( $lower_blocked_uri, 'blob:' )
				|| str_starts_with( $lower_blocked_uri, 'about:' )
			) {
				$source_file   = (string) ( $violation['source_file'] ?? '' );
				$line_number   = ! empty( $violation['line_number'] ) ? (string) (int) $violation['line_number'] : '';
				$column_number = ! empty( $violation['column_number'] ) ? (string) (int) $violation['column_number'] : '';
				$sample        = (string) ( $violation['sample'] ?? '' );
				$sample_hash   = '' !== $sample ? hash( 'sha256', $sample ) : '';
				$evidence      = array();

				if ( '' !== $source_file ) {
					$evidence[] = sprintf(
						/* translators: %s: violation source file URL */
						__( 'Source: %s', 'csp-automation-manager' ),
						$source_file
					);
				}
				if ( '' !== $line_number ) {
					$location   = '' !== $column_number ? $line_number . ':' . $column_number : $line_number;
					$evidence[] = sprintf(
						/* translators: %s: source line or line:column */
						__( 'Location: %s', 'csp-automation-manager' ),
						$location
					);
				}
				if ( '' !== $sample ) {
					$evidence[] = sprintf(
						/* translators: %s: short CSP report sample */
						__( 'Sample: %s', 'csp-automation-manager' ),
						substr( $sample, 0, 120 )
					);
				}

				return array(
					'display'  => str_starts_with( $lower_blocked_uri, 'data:' ) ? 'data:' : $blocked_uri,
					'group'    => implode( '|', array( 'non-host', $lower_blocked_uri, $source_file, $line_number, $column_number, $sample_hash ) ),
					'evidence' => implode( ' | ', $evidence ),
				);
			}

			if ( str_starts_with( $blocked_uri, '//' ) ) {
				$blocked_uri = 'https:' . $blocked_uri;
			}

			$parsed = wp_parse_url( $blocked_uri );
			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return array(
					'display'  => $blocked_uri,
					'group'    => $blocked_uri,
					'evidence' => '',
				);
			}

			$source = strtolower( (string) ( $parsed['scheme'] ?? 'https' ) ) . '://' . strtolower( (string) $parsed['host'] );
			if ( ! empty( $parsed['port'] ) ) {
				$source .= ':' . (int) $parsed['port'];
			}

			return array(
				'display'  => $source,
				'group'    => $source,
				'evidence' => '',
			);
		};
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$violation_totals = $wpdb->get_row( "SELECT COUNT(*) AS stored_rows, COALESCE(SUM(occurrence_count), 0) AS total_occurrences FROM {$wpdb->prefix}csp_violation_reports", ARRAY_A );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$violations_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_violation_reports ORDER BY reported_at DESC LIMIT 1000", ARRAY_A );
		$viol_groups    = array();
	foreach ( ! empty( $violations_raw ) ? $violations_raw : array() as $violation ) {
		$blocked_source = $canonicalise_violation_source( $violation );
		$group_key      = implode(
			'|',
			array(
				(string) ( $violation['profile_surface'] ?? '' ),
				(string) ( $violation['violated_directive'] ?? '' ),
				(string) ( $violation['disposition'] ?? '' ),
				$blocked_source['group'],
			)
		);

		if ( ! isset( $viol_groups[ $group_key ] ) ) {
			$viol_groups[ $group_key ]                     = $violation;
			$viol_groups[ $group_key ]['blocked_uri']      = $blocked_source['display'];
			$viol_groups[ $group_key ]['evidence_context'] = $blocked_source['evidence'];
			$viol_groups[ $group_key ]['row_count']        = 0;
			$viol_groups[ $group_key ]['sample_uri']       = (string) ( $violation['blocked_uri'] ?? '' );
			$viol_groups[ $group_key ]['reported_at']      = (string) ( $violation['reported_at'] ?? '' );
			$viol_groups[ $group_key ]['occurrence_count'] = 0;
		}

		$viol_groups[ $group_key ]['row_count']        = (int) $viol_groups[ $group_key ]['row_count'] + 1;
		$viol_groups[ $group_key ]['occurrence_count'] = (int) $viol_groups[ $group_key ]['occurrence_count'] + max( 1, (int) ( $violation['occurrence_count'] ?? 1 ) );
		if ( (string) ( $violation['reported_at'] ?? '' ) > (string) $viol_groups[ $group_key ]['reported_at'] ) {
			$viol_groups[ $group_key ]['reported_at'] = (string) $violation['reported_at'];
		}
	}

		$violations_grouped = array_values( $viol_groups );
		usort(
			$violations_grouped,
			static fn ( array $a, array $b ): int => strcmp( (string) ( $b['reported_at'] ?? '' ), (string) ( $a['reported_at'] ?? '' ) )
		);

		$viol_total    = count( $violations_grouped );
		$viol_pages    = max( 1, (int) ceil( $viol_total / $per_page ) );
		$viol_page_num = min( $viol_page_num, $viol_pages );
		$viol_offset   = ( $viol_page_num - 1 ) * $per_page;
		$violations    = array_slice( $violations_grouped, $viol_offset, $per_page );
		?>
	<p class="description">
		<?php
		printf(
			/* translators: 1: grouped rows, 2: stored fingerprints, 3: browser report occurrences */
			esc_html__( 'Showing %1$d grouped violation source(s) from %2$d stored fingerprint(s) and %3$d browser report occurrence(s). Remote asset URLs are grouped by policy source; inline, data, eval, blob, and about reports are separated by source location or report sample when the browser supplies that evidence.', 'csp-automation-manager' ),
			(int) $viol_total,
			(int) ( $violation_totals['stored_rows'] ?? 0 ),
			(int) ( $violation_totals['total_occurrences'] ?? 0 )
		);
		?>
	</p>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Blocked URI', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Evidence', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Occurrences', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Last Seen', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Disposition', 'csp-automation-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $violations as $v ) : ?>
		<tr>
			<td><?php echo esc_html( $v['profile_surface'] ); ?></td>
			<td>
				<code style="word-break:break-all"><?php echo esc_html( $v['blocked_uri'] ); ?></code>
				<?php if ( ! empty( $v['row_count'] ) && (int) $v['row_count'] > 1 ) : ?>
					<br><span class="description">
						<?php
						printf(
							/* translators: 1: number of grouped report rows, 2: example blocked URI */
							esc_html__( 'Grouped from %1$d file-level reports. Example: %2$s', 'csp-automation-manager' ),
							(int) $v['row_count'],
							esc_html( (string) ( $v['sample_uri'] ?? '' ) )
						);
						?>
					</span>
				<?php endif; ?>
			</td>
			<td><?php echo '' !== (string) ( $v['evidence_context'] ?? '' ) ? esc_html( (string) $v['evidence_context'] ) : '&mdash;'; ?></td>
			<td><code><?php echo esc_html( $v['violated_directive'] ); ?></code></td>
			<td><?php echo esc_html( number_format( (int) $v['occurrence_count'] ) ); ?></td>
			<td><?php echo esc_html( $v['reported_at'] ); ?></td>
			<td><?php echo esc_html( $v['disposition'] ); ?></td>
		</tr>
		<?php endforeach; ?>
		<?php if ( empty( $violations ) ) : ?>
		<tr>
			<td colspan="7">
				<p><?php esc_html_e( 'No browser violation reports have been recorded yet.', 'csp-automation-manager' ); ?></p>
				<p>
					<?php esc_html_e( 'Manual scans discover candidate sources, but they do not create violation reports. To collect violations, browse the live site while the relevant surface emits this plugin\'s report-only or enforce CSP header with reporting directives.', 'csp-automation-manager' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Expected reporting endpoint:', 'csp-automation-manager' ); ?>
					<code><?php echo esc_html( esc_url_raw( $report_endpoint_url ) ); ?></code>
				</p>
			</td>
		</tr>
		<?php endif; ?>
		</tbody>
	</table>

		<?php if ( $viol_pages > 1 ) : ?>
	<div class="tablenav bottom" style="margin-top:1em">
		<div class="tablenav-pages">
			<?php if ( $viol_page_num > 1 ) : ?>
			<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'tab'     => 'violations',
							'v_paged' => $viol_page_num - 1,
						),
						$base_url
					)
				);
				?>
									">&laquo; <?php esc_html_e( 'Previous', 'csp-automation-manager' ); ?></a>
			<?php endif; ?>
			<span style="margin:0 8px">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'csp-automation-manager' ),
					$viol_page_num,
					$viol_pages
				);
				?>
			</span>
			<?php if ( $viol_page_num < $viol_pages ) : ?>
			<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'tab'     => 'violations',
							'v_paged' => $viol_page_num + 1,
						),
						$base_url
					)
				);
				?>
									"><?php esc_html_e( 'Next', 'csp-automation-manager' ); ?> &raquo;</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
	<?php endif; ?>

	</div><!-- .tab-content -->
</div><!-- .wp-csp-wrap -->
