<?php
/**
 * Admin view: CSP Automation Manager dashboard.
 * Shows per-surface policy profiles, source inventory, violations, scan log.
 * Rendered by Admin_UI::render_dashboard().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_CSP\Admin\Table_Query;
use WP_CSP\Admin\Policy_Events_Builder;

global $wpdb;

// Current tab.
$tab          = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'start-here';
$allowed_tabs = array( 'start-here', 'profiles', 'sources', 'policy-changes', 'violations', 'scan-log', 'settings' );
if ( ! in_array( $tab, $allowed_tabs, true ) ) {
	$tab = 'start-here';
}

$base_url = admin_url( 'admin.php?page=csp-automation-manager-dashboard' );
$tab_help = array(
	'start-here'     => array(
		'label'       => __( 'Start Here', 'csp-automation-manager' ),
		'description' => __( 'A short guide to how this plugin works: report-only, the learning window, and promoting a surface to enforce mode.', 'csp-automation-manager' ),
	),
	'profiles'       => array(
		'label'       => __( 'Profiles', 'csp-automation-manager' ),
		'description' => __( 'Configure the CSP mode for each site surface. Use report-only while learning, enforce only after the surface is stable, or disabled when this plugin should not emit CSP for that surface.', 'csp-automation-manager' ),
	),
	'sources'        => array(
		'label'       => __( 'For Review', 'csp-automation-manager' ),
		'description' => __( 'Review discovered source candidates and decide whether each source belongs in the policy. Discovery adds review items; approvals, rejections, reversions, and undo actions require a reason and are written to the decision ledger.', 'csp-automation-manager' ),
	),
	'policy-changes' => array(
		'label'       => __( 'Policy Changes', 'csp-automation-manager' ),
		'description' => __( 'Inspect policy activity across discovered proposals, administrator or automation decisions, and immutable policy snapshots.', 'csp-automation-manager' ),
	),
	'violations'     => array(
		'label'       => __( 'Violations', 'csp-automation-manager' ),
		'description' => __( 'Review browser-submitted CSP reports. Use these reports to identify required sources before promoting a surface from report-only to enforce mode.', 'csp-automation-manager' ),
	),
	'scan-log'       => array(
		'label'       => __( 'Scan Log', 'csp-automation-manager' ),
		'description' => __( 'Check manual and scheduled scan runs, policy-change counts, warnings, and completion status after site, theme, plugin, or content changes.', 'csp-automation-manager' ),
	),
	'settings'       => array(
		'label'       => __( 'Settings', 'csp-automation-manager' ),
		'description' => __( 'Promotion gates, deterministic automation, proxy header emission, report endpoint learning, and scan schedule.', 'csp-automation-manager' ),
	),
);

// ── Data queries ──────────────────────────────────────────────────────────────
// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$profiles_raw      = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_policy_profiles ORDER BY surface", ARRAY_A );
$profiles          = ! empty( $profiles_raw ) ? $profiles_raw : array();
$surfaces          = array( 'frontend', 'admin', 'login', 'api' );
$automation_config = ( new \WP_CSP\CSP\Automation_Config() )->all();
$automation_labels = \WP_CSP\CSP\Automation_Config::mode_labels();

// Shared pagination defaults.
$per_page = 20;
$page_num = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
$offset   = ( $page_num - 1 ) * $per_page;

// Violations – last 50.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$violations_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_violation_reports ORDER BY reported_at DESC LIMIT 50", ARRAY_A );
$violations     = ! empty( $violations_raw ) ? $violations_raw : array();

// Scan log – last 20 runs.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$scan_logs_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_scan_logs ORDER BY started_at DESC LIMIT 20", ARRAY_A );
$scan_logs     = ! empty( $scan_logs_raw ) ? $scan_logs_raw : array();
?>
<div class="wrap wp-csp-wrap">
	<h1><?php esc_html_e( 'CSP Manager', 'csp-automation-manager' ); ?></h1>

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

	<?php if ( 'start-here' === $tab ) : ?>
	<!-- ── Start Here tab ─────────────────────────────────────────────────── -->
	<h2 class="title"><?php esc_html_e( 'What this plugin does', 'csp-automation-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'This plugin builds a Content-Security-Policy (CSP) for your site by watching what your site actually loads — scripts, styles, images, fonts, connections, and frames — and letting you decide which of those sources belong in the policy. It manages this separately for four surfaces: the public frontend, wp-admin, the login screen, and the REST API.', 'csp-automation-manager' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Nothing is blocked or enforced until you deliberately turn it on. The whole workflow below is designed so you can see exactly what a policy would do before it does it.', 'csp-automation-manager' ); ?>
	</p>

	<h2 class="title"><?php esc_html_e( 'How it works', 'csp-automation-manager' ); ?></h2>
	<ol>
		<li>
			<strong><?php esc_html_e( 'Report-only mode.', 'csp-automation-manager' ); ?></strong>
			<?php esc_html_e( 'Each surface starts in report-only mode: browsers evaluate the policy and send a report for anything that would have been blocked, but nothing is actually blocked. Reports arrive at this plugin\'s own endpoint (shown on the Settings tab, under Report Endpoint Learning) and appear in the Violations tab.', 'csp-automation-manager' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'The learning window.', 'csp-automation-manager' ); ?></strong>
			<?php esc_html_e( 'While the learning window is open (48 hours after the last material change to the site by default — a page, post, plugin, or theme edit — adjustable on the Settings tab), validated violation reports and scan discoveries add candidate sources to the For Review queue instead of just being logged. Once the window locks, new discoveries stop being added automatically until another material change reopens it.', 'csp-automation-manager' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Review.', 'csp-automation-manager' ); ?></strong>
			<?php esc_html_e( 'Every discovered source lands in For Review with a risk rating and a reason. Approve the ones that belong, reject the ones that don\'t — every decision needs a short reason and is written to a permanent decision ledger (visible on the Policy Audit page). Depending on the Automation Level you\'ve set for a surface, low-risk sources can be approved automatically instead of waiting on you; see Configuration below.', 'csp-automation-manager' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Manual promotion to enforce mode.', 'csp-automation-manager' ); ?></strong>
			<?php esc_html_e( 'When you\'re confident the policy is complete, promote a surface to enforce mode yourself from the Profiles tab — this is never done automatically. Promotion is gated: it requires at least one approved source or hash, no violations within a configurable window (24 hours by default, set on the Settings tab), and no active temporary override.', 'csp-automation-manager' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Policy revision.', 'csp-automation-manager' ); ?></strong>
			<?php esc_html_e( 'Enforce mode isn\'t a finish line. A new plugin, a theme change, or a new page can introduce sources the policy doesn\'t cover yet. Treat each material change as a prompt to reopen the learning window, review what\'s new in For Review, and revise the policy — the same report-only-then-promote cycle applies to every revision.', 'csp-automation-manager' ); ?>
		</li>
	</ol>

	<h2 class="title"><?php esc_html_e( 'Configuration', 'csp-automation-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'The most important setting is Automation Level, set per surface from the Profiles tab dropdown or the Settings tab: Manual, Automatic (medium+high approvals), Automatic (high approvals only), or Fully automatic. This controls which risk tiers of discovered sources, if any, can be approved without you. Manual is always the safe starting point — nothing is auto-approved until you deliberately raise a surface\'s level. The rest of the Settings tab covers promotion gates, proxy header emission, report endpoint learning, and the scan schedule.', 'csp-automation-manager' ); ?>
	</p>

	<h2 class="title"><?php esc_html_e( 'Readiness and Policy Audit', 'csp-automation-manager' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'The Readiness page (in the sidebar) runs plugin and database health checks — schema integrity, table health, and operational checks — and, separately, offers a destructive full data reset if you need to start over.', 'csp-automation-manager' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'The Policy Audit page (in the sidebar) shows the current effective policy for every surface, the pending review queue, and the full, immutable decision ledger — who approved, rejected, or reverted each source, and why.', 'csp-automation-manager' ); ?>
	</p>

	<?php elseif ( 'profiles' === $tab ) : ?>
	<!-- ── Profiles tab ───────────────────────────────────────────────────── -->
		<?php
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$profiles_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_policy_profiles ORDER BY surface", ARRAY_A );
		$profiles     = ! empty( $profiles_raw ) ? $profiles_raw : array();
		?>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Mode', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Automation', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Last Updated', 'csp-automation-manager' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'csp-automation-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $profiles as $profile ) : ?>
		<tr>
			<td><?php echo esc_html( ucfirst( $profile['surface'] ) ); ?></td>
			<td>
				<span class="wp-csp-mode-badge mode-<?php echo esc_attr( $profile['mode'] ); ?>">
					<?php echo esc_html( $profile['mode'] ); ?>
				</span>
			</td>
			<td>
				<?php
				$surface          = (string) $profile['surface'];
				$surface_config   = $automation_config[ $surface ] ?? \WP_CSP\CSP\Automation_Config::DEFAULT_SURFACE_CONFIG;
				$automation_mode  = (string) ( $surface_config['mode'] ?? \WP_CSP\CSP\Automation_Config::MODE_MANUAL );
				$automation_title = sprintf(
					/* translators: %s: current automation mode label */
					__( 'Automation posture: %s', 'csp-automation-manager' ),
					\WP_CSP\CSP\Automation_Config::mode_label( $automation_mode )
				);
				?>
				<label class="screen-reader-text" for="wp-csp-automation-mode-<?php echo esc_attr( $surface ); ?>">
					<?php echo esc_html( $automation_title ); ?>
				</label>
				<select id="wp-csp-automation-mode-<?php echo esc_attr( $surface ); ?>"
					class="wp-csp-automation-mode"
					data-surface="<?php echo esc_attr( $surface ); ?>"
					title="<?php echo esc_attr( $automation_title ); ?>">
					<?php foreach ( $automation_labels as $mode => $label ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $automation_mode, $mode ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><?php echo esc_html( $profile['updated_at'] ); ?></td>
			<td>
				<?php foreach ( array( 'report-only', 'enforce', 'disabled' ) as $m ) : ?>
					<?php if ( $m !== $profile['mode'] ) : ?>
					<button type="button"
						class="button button-small wp-csp-toggle-mode"
						data-surface="<?php echo esc_attr( $profile['surface'] ); ?>"
						data-mode="<?php echo esc_attr( $m ); ?>">
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $m ) ) ); ?>
					</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		<?php if ( empty( $profiles ) ) : ?>
		<tr><td colspan="5"><?php esc_html_e( 'No profiles found. Deactivate and reactivate the plugin to seed defaults.', 'csp-automation-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php elseif ( 'sources' === $tab ) : ?>
	<!-- ── Sources tab ────────────────────────────────────────────────────── -->
		<?php
		$src_surface      = Table_Query::multi_param( 'src_surface' );
		$src_state        = Table_Query::multi_param( 'src_state' );
		$src_risk         = Table_Query::multi_param( 'src_risk' );
		$src_directive    = Table_Query::multi_param( 'src_directive' );
		$src_host         = Table_Query::text_param( 'src_host' );
		$src_evidence_min = Table_Query::int_param( 'src_evidence_min' );
		$src_seen_from    = Table_Query::text_param( 'src_seen_from' );
		$src_seen_to      = Table_Query::text_param( 'src_seen_to' );
		$src_id           = Table_Query::int_param( 'src_id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$src_directive_options = $wpdb->get_col( "SELECT DISTINCT directive FROM {$wpdb->prefix}csp_source_inventory ORDER BY directive" );
		$src_directive_options = ! empty( $src_directive_options ) ? $src_directive_options : array();

		$src_where = array( '1=1' );
		$src_args  = array();
		foreach (
			array(
				Table_Query::multi_select_where( 'surface', $src_surface ),
				Table_Query::multi_select_where( 'approval_state', $src_state ),
				Table_Query::multi_select_where( 'risk_level', $src_risk ),
				Table_Query::multi_select_where( 'directive', $src_directive ),
				Table_Query::like_where( $wpdb, 'source_host', $src_host ),
				Table_Query::numeric_gte_where( 'evidence_count', $src_evidence_min ),
				Table_Query::date_range_where( 'last_seen_at', $src_seen_from, $src_seen_to ),
			) as $src_fragment
		) {
			if ( null === $src_fragment ) {
				continue;
			}
			$src_where[] = $src_fragment['sql'];
			array_push( $src_args, ...$src_fragment['args'] );
		}
		if ( null !== $src_id ) {
			$src_where[] = 'id = %d';
			$src_args[]  = $src_id;
		}

		$src_where_sql = implode( ' AND ', $src_where );

		$src_sort_whitelist = array(
			'id'        => array(
				'expr'        => 'id',
				'default_dir' => 'desc',
			),
			'surface'   => array(
				'expr'        => 'surface',
				'default_dir' => 'asc',
			),
			'directive' => array(
				'expr'        => 'directive',
				'default_dir' => 'asc',
			),
			'host'      => array(
				'expr'        => 'source_host',
				'default_dir' => 'asc',
			),
			'risk'      => array(
				'expr'        => "CASE risk_level WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END",
				'default_dir' => 'asc',
			),
			'state'     => array(
				'expr'        => 'approval_state',
				'default_dir' => 'asc',
			),
			'evidence'  => array(
				'expr'        => 'evidence_count',
				'default_dir' => 'desc',
			),
			'last_seen' => array(
				'expr'        => 'last_seen_at',
				'default_dir' => 'desc',
			),
		);
		$src_sort           = Table_Query::resolve_sort(
			$src_sort_whitelist,
			'last_seen',
			isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['dir'] ) ? sanitize_text_field( wp_unslash( $_GET['dir'] ) ) : null // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$src_state_args = array_filter(
			array(
				'tab'              => 'sources',
				'sort'             => $src_sort['key'],
				'dir'              => strtolower( $src_sort['dir'] ),
				'src_surface'      => $src_surface,
				'src_state'        => $src_state,
				'src_risk'         => $src_risk,
				'src_directive'    => $src_directive,
				'src_host'         => $src_host,
				'src_evidence_min' => $src_evidence_min,
				'src_seen_from'    => $src_seen_from,
				'src_seen_to'      => $src_seen_to,
				'src_id'           => $src_id,
			)
		);

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}csp_source_inventory WHERE {$src_where_sql}";
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
		$data_sql   = "SELECT * FROM {$wpdb->prefix}csp_source_inventory WHERE {$src_where_sql} " . Table_Query::order_by_sql( $src_sort ) . ' LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data_sql = $wpdb->prepare( $data_sql, ...$query_args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$sources_raw = $wpdb->get_results( $data_sql, ARRAY_A );
		$sources     = ! empty( $sources_raw ) ? $sources_raw : array();
		?>
	<form method="get" action="" class="wp-csp-filter-form">
		<input type="hidden" name="page" value="csp-automation-manager-dashboard" />
		<input type="hidden" name="tab"  value="sources" />
		<label>
			<?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?>
			<select name="src_surface[]" multiple size="4">
				<?php foreach ( $surfaces as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php echo in_array( $s, $src_surface, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'State', 'csp-automation-manager' ); ?>
			<select name="src_state[]" multiple size="3">
				<?php foreach ( array( 'pending', 'approved', 'denied' ) as $st ) : ?>
				<option value="<?php echo esc_attr( $st ); ?>" <?php echo in_array( $st, $src_state, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Risk', 'csp-automation-manager' ); ?>
			<select name="src_risk[]" multiple size="3">
				<?php foreach ( array( 'high', 'medium', 'low' ) as $risk ) : ?>
				<option value="<?php echo esc_attr( $risk ); ?>" <?php echo in_array( $risk, $src_risk, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $risk ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?>
			<select name="src_directive[]" multiple size="4">
				<?php foreach ( $src_directive_options as $d ) : ?>
				<option value="<?php echo esc_attr( $d ); ?>" <?php echo in_array( $d, $src_directive, true ) ? 'selected' : ''; ?>><?php echo esc_html( $d ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Host contains', 'csp-automation-manager' ); ?>
			<input type="text" name="src_host" value="<?php echo esc_attr( $src_host ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'ID', 'csp-automation-manager' ); ?>
			<input type="number" min="1" name="src_id" style="width:80px" value="<?php echo esc_attr( null !== $src_id ? (string) $src_id : '' ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'Evidence at least', 'csp-automation-manager' ); ?>
			<input type="number" min="0" name="src_evidence_min" style="width:80px" value="<?php echo esc_attr( null !== $src_evidence_min ? (string) $src_evidence_min : '' ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'Last seen from', 'csp-automation-manager' ); ?>
			<input type="date" name="src_seen_from" value="<?php echo esc_attr( $src_seen_from ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'to', 'csp-automation-manager' ); ?>
			<input type="date" name="src_seen_to" value="<?php echo esc_attr( $src_seen_to ); ?>" />
		</label>
		<?php submit_button( __( 'Filter', 'csp-automation-manager' ), 'secondary', 'filter_sources', false ); ?>
	</form>

	<table class="widefat fixed striped" style="margin-top:1em">
		<thead>
			<tr>
				<?php
				echo Table_Query::sort_header( __( 'ID', 'csp-automation-manager' ), 'id', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes internally.
				echo Table_Query::sort_header( __( 'Surface', 'csp-automation-manager' ), 'surface', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Directive', 'csp-automation-manager' ), 'directive', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Host', 'csp-automation-manager' ), 'host', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Risk', 'csp-automation-manager' ), 'risk', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'State', 'csp-automation-manager' ), 'state', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Evidence', 'csp-automation-manager' ), 'evidence', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Last Seen', 'csp-automation-manager' ), 'last_seen', $src_sort_whitelist, $src_sort, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
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

		<?php echo Table_Query::pagination( $page_num, $src_pages, $src_state_args, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php elseif ( 'policy-changes' === $tab ) : ?>
	<!-- Policy Changes tab -->
		<?php
		$pc_type        = Table_Query::multi_param( 'pc_type' );
		$pc_event       = Table_Query::multi_param( 'pc_event' );
		$pc_surface     = Table_Query::multi_param( 'pc_surface' );
		$pc_directive   = Table_Query::multi_param( 'pc_directive' );
		$pc_host        = Table_Query::text_param( 'pc_host' );
		$pc_risk        = Table_Query::multi_param( 'pc_risk' );
		$pc_policy_ver  = Table_Query::text_param( 'pc_policy_version' );
		$pc_suppression = Table_Query::text_param( 'pc_suppression' );
		$pc_actor       = Table_Query::multi_param( 'pc_actor' );
		$pc_detail      = Table_Query::text_param( 'pc_detail' );
		$pc_when_from   = Table_Query::text_param( 'pc_when_from' );
		$pc_when_to     = Table_Query::text_param( 'pc_when_to' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$pc_directive_options = $wpdb->get_col( "SELECT DISTINCT directive FROM {$wpdb->prefix}csp_policy_change_decisions ORDER BY directive" );
		$pc_directive_options = ! empty( $pc_directive_options ) ? $pc_directive_options : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$pc_actor_from_decisions = $wpdb->get_col( "SELECT DISTINCT actor_type FROM {$wpdb->prefix}csp_policy_change_decisions" );
		$pc_actor_from_decisions = ! empty( $pc_actor_from_decisions ) ? $pc_actor_from_decisions : array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$pc_actor_from_versions = $wpdb->get_col( "SELECT DISTINCT trigger_type FROM {$wpdb->prefix}csp_policy_versions" );
		$pc_actor_from_versions = ! empty( $pc_actor_from_versions ) ? $pc_actor_from_versions : array();
		$pc_actor_from_versions = array_map(
			static function ( string $trigger_type ): string {
				return 'decision' === $trigger_type ? __( 'system', 'csp-automation-manager' ) : $trigger_type;
			},
			$pc_actor_from_versions
		);
		$pc_actor_options       = array_values(
			array_unique(
				array_merge(
					$pc_actor_from_decisions,
					$pc_actor_from_versions,
					array( __( 'system', 'csp-automation-manager' ), __( 'administrator', 'csp-automation-manager' ) )
				)
			)
		);
		sort( $pc_actor_options );

		$pc_type_options  = array(
			__( 'Decision', 'csp-automation-manager' ),
			__( 'Policy version', 'csp-automation-manager' ),
			__( 'Discovery', 'csp-automation-manager' ),
		);
		$pc_event_options = array(
			__( 'Approved', 'csp-automation-manager' ),
			__( 'Rejected', 'csp-automation-manager' ),
			__( 'Reverted', 'csp-automation-manager' ),
			__( 'Undone', 'csp-automation-manager' ),
			__( 'Snapshot', 'csp-automation-manager' ),
			__( 'Proposed source', 'csp-automation-manager' ),
			__( 'Suppressed proposal', 'csp-automation-manager' ),
		);

		$pc_result = Policy_Events_Builder::fetch(
			$wpdb,
			array(
				'type'           => $pc_type,
				'event'          => $pc_event,
				'surface'        => $pc_surface,
				'directive'      => $pc_directive,
				'host'           => $pc_host,
				'risk'           => $pc_risk,
				'policy_version' => $pc_policy_ver,
				'suppression'    => $pc_suppression,
				'actor'          => $pc_actor,
				'detail'         => $pc_detail,
				'when_from'      => $pc_when_from,
				'when_to'        => $pc_when_to,
			)
		);

		$pc_sort_whitelist = array(
			'when'           => array(
				'expr'        => 'created_at',
				'default_dir' => 'desc',
			),
			'event'          => array(
				'expr'        => 'event',
				'default_dir' => 'asc',
			),
			'type'           => array(
				'expr'        => 'type',
				'default_dir' => 'asc',
			),
			'actor'          => array(
				'expr'        => 'actor',
				'default_dir' => 'asc',
			),
			'surface'        => array(
				'expr'        => 'surface',
				'default_dir' => 'asc',
			),
			'directive'      => array(
				'expr'        => 'directive',
				'default_dir' => 'asc',
			),
			'host'           => array(
				'expr'        => 'source',
				'default_dir' => 'asc',
			),
			'risk'           => array(
				'expr'        => 'risk_level',
				'default_dir' => 'asc',
			),
			'policy_version' => array(
				'expr'        => 'policy_version',
				'default_dir' => 'asc',
			),
			'suppression'    => array(
				'expr'        => 'suppression',
				'default_dir' => 'asc',
			),
			'detail'         => array(
				'expr'        => 'detail',
				'default_dir' => 'asc',
			),
		);
		$pc_sort           = Table_Query::resolve_sort(
			$pc_sort_whitelist,
			'when',
			isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['dir'] ) ? sanitize_text_field( wp_unslash( $_GET['dir'] ) ) : null // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$policy_events_sorted = Policy_Events_Builder::sort( $pc_result['events'], $pc_sort['key'], $pc_sort['dir'] );

		$pc_total      = count( $policy_events_sorted );
		$pc_pages      = max( 1, (int) ceil( $pc_total / $per_page ) );
		$pc_page_num   = min( max( 1, (int) ( isset( $_GET['pc_paged'] ) ? $_GET['pc_paged'] : 1 ) ), $pc_pages );
		$pc_offset     = ( $pc_page_num - 1 ) * $per_page;
		$policy_events = array_slice( $policy_events_sorted, $pc_offset, $per_page );

		$pc_state_args = array_filter(
			array(
				'tab'               => 'policy-changes',
				'sort'              => $pc_sort['key'],
				'dir'               => strtolower( $pc_sort['dir'] ),
				'pc_type'           => $pc_type,
				'pc_event'          => $pc_event,
				'pc_surface'        => $pc_surface,
				'pc_directive'      => $pc_directive,
				'pc_host'           => $pc_host,
				'pc_risk'           => $pc_risk,
				'pc_policy_version' => $pc_policy_ver,
				'pc_suppression'    => $pc_suppression,
				'pc_actor'          => $pc_actor,
				'pc_detail'         => $pc_detail,
				'pc_when_from'      => $pc_when_from,
				'pc_when_to'        => $pc_when_to,
			)
		);
		?>
	<p class="description">
		<?php esc_html_e( 'Discovered candidates appear in For Review first. This timeline shows proposal activity, administrator or automation decisions, suppression state, and the policy snapshots created after material changes.', 'csp-automation-manager' ); ?>
	</p>
		<?php if ( $pc_result['truncated'] ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Showing results from up to 5,000 recent records per activity type. Narrow your filters (Type, Surface, Directive, or a date range) to see the full, correctly-sorted result set.', 'csp-automation-manager' ); ?></p>
	</div>
	<?php endif; ?>
	<details class="wp-csp-filter-form">
		<summary><?php esc_html_e( 'Filters', 'csp-automation-manager' ); ?></summary>
		<form method="get" action="">
			<input type="hidden" name="page" value="csp-automation-manager-dashboard" />
			<input type="hidden" name="tab"  value="policy-changes" />
			<label>
				<?php esc_html_e( 'Type', 'csp-automation-manager' ); ?>
				<select name="pc_type[]" multiple size="3">
					<?php foreach ( $pc_type_options as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt ); ?>" <?php echo in_array( $opt, $pc_type, true ) ? 'selected' : ''; ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Event', 'csp-automation-manager' ); ?>
				<select name="pc_event[]" multiple size="4">
					<?php foreach ( $pc_event_options as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt ); ?>" <?php echo in_array( $opt, $pc_event, true ) ? 'selected' : ''; ?>><?php echo esc_html( $opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?>
				<select name="pc_surface[]" multiple size="4">
					<?php foreach ( $surfaces as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php echo in_array( $s, $pc_surface, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?>
				<select name="pc_directive[]" multiple size="4">
					<?php foreach ( $pc_directive_options as $d ) : ?>
					<option value="<?php echo esc_attr( $d ); ?>" <?php echo in_array( $d, $pc_directive, true ) ? 'selected' : ''; ?>><?php echo esc_html( $d ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Host contains', 'csp-automation-manager' ); ?>
				<input type="text" name="pc_host" value="<?php echo esc_attr( $pc_host ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Risk', 'csp-automation-manager' ); ?>
				<select name="pc_risk[]" multiple size="3">
					<?php foreach ( array( 'high', 'medium', 'low' ) as $risk ) : ?>
					<option value="<?php echo esc_attr( $risk ); ?>" <?php echo in_array( $risk, $pc_risk, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $risk ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Policy version contains', 'csp-automation-manager' ); ?>
				<input type="text" name="pc_policy_version" value="<?php echo esc_attr( $pc_policy_ver ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Suppression', 'csp-automation-manager' ); ?>
				<select name="pc_suppression">
					<option value=""><?php esc_html_e( 'Any', 'csp-automation-manager' ); ?></option>
					<option value="active" <?php selected( $pc_suppression, 'active' ); ?>><?php esc_html_e( 'Active only', 'csp-automation-manager' ); ?></option>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Actor', 'csp-automation-manager' ); ?>
				<select name="pc_actor[]" multiple size="4">
					<?php foreach ( $pc_actor_options as $a ) : ?>
					<option value="<?php echo esc_attr( $a ); ?>" <?php echo in_array( $a, $pc_actor, true ) ? 'selected' : ''; ?>><?php echo esc_html( $a ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Detail contains', 'csp-automation-manager' ); ?>
				<input type="text" name="pc_detail" value="<?php echo esc_attr( $pc_detail ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'When from', 'csp-automation-manager' ); ?>
				<input type="date" name="pc_when_from" value="<?php echo esc_attr( $pc_when_from ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'to', 'csp-automation-manager' ); ?>
				<input type="date" name="pc_when_to" value="<?php echo esc_attr( $pc_when_to ); ?>" />
			</label>
			<?php submit_button( __( 'Filter', 'csp-automation-manager' ), 'secondary', 'filter_policy_changes', false ); ?>
		</form>
	</details>
	<table class="widefat fixed striped" style="margin-top:1em">
		<thead>
			<tr>
				<?php
				echo Table_Query::sort_header( __( 'When', 'csp-automation-manager' ), 'when', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes internally.
				echo Table_Query::sort_header( __( 'Event', 'csp-automation-manager' ), 'event', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Type', 'csp-automation-manager' ), 'type', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Actor', 'csp-automation-manager' ), 'actor', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Surface', 'csp-automation-manager' ), 'surface', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Directive', 'csp-automation-manager' ), 'directive', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Host', 'csp-automation-manager' ), 'host', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Risk', 'csp-automation-manager' ), 'risk', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Policy Version', 'csp-automation-manager' ), 'policy_version', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Suppression', 'csp-automation-manager' ), 'suppression', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Detail', 'csp-automation-manager' ), 'detail', $pc_sort_whitelist, $pc_sort, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
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

		<?php echo Table_Query::pagination( $pc_page_num, $pc_pages, $pc_state_args, $base_url, 'pc_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php elseif ( 'violations' === $tab ) : ?>
	<!-- ── Violations tab ─────────────────────────────────────────────────── -->
		<?php
		$report_endpoint_url = (string) get_option( 'wp_csp_report_endpoint_url', '' );
		if ( '' === trim( $report_endpoint_url ) ) {
			$report_endpoint_url = rest_url( 'csp-manager/v1/report' );
		}

		$v_surface     = Table_Query::multi_param( 'v_surface' );
		$v_directive   = Table_Query::multi_param( 'v_directive' );
		$v_disposition = Table_Query::multi_param( 'v_disposition' );
		$v_blocked     = Table_Query::text_param( 'v_blocked' );
		$v_occ_min     = Table_Query::int_param( 'v_occ_min' );
		$v_seen_from   = Table_Query::text_param( 'v_seen_from' );
		$v_seen_to     = Table_Query::text_param( 'v_seen_to' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$v_directive_options = $wpdb->get_col( "SELECT DISTINCT violated_directive FROM {$wpdb->prefix}csp_violation_reports ORDER BY violated_directive" );
		$v_directive_options = ! empty( $v_directive_options ) ? $v_directive_options : array();

		$viol_where = array( '1=1' );
		$viol_args  = array();
		foreach (
			array(
				Table_Query::multi_select_where( 'profile_surface', $v_surface ),
				Table_Query::multi_select_where( 'violated_directive', $v_directive ),
				Table_Query::multi_select_where( 'disposition', $v_disposition ),
				Table_Query::like_where( $wpdb, 'blocked_uri', $v_blocked ),
				Table_Query::numeric_gte_where( 'occurrence_count', $v_occ_min ),
				Table_Query::date_range_where( 'reported_at', $v_seen_from, $v_seen_to ),
			) as $viol_fragment
		) {
			if ( null === $viol_fragment ) {
				continue;
			}
			$viol_where[] = $viol_fragment['sql'];
			array_push( $viol_args, ...$viol_fragment['args'] );
		}
		$viol_where_sql = implode( ' AND ', $viol_where );

		$viol_sort_whitelist = array(
			'surface'     => array(
				'expr'        => 'profile_surface',
				'default_dir' => 'asc',
			),
			'blocked_uri' => array(
				'expr'        => 'blocked_uri',
				'default_dir' => 'asc',
			),
			'directive'   => array(
				'expr'        => 'violated_directive',
				'default_dir' => 'asc',
			),
			'occurrences' => array(
				'expr'        => 'occurrence_count',
				'default_dir' => 'desc',
			),
			'last_seen'   => array(
				'expr'        => 'reported_at',
				'default_dir' => 'desc',
			),
			'disposition' => array(
				'expr'        => 'disposition',
				'default_dir' => 'asc',
			),
		);
		$viol_sort           = Table_Query::resolve_sort(
			$viol_sort_whitelist,
			'last_seen',
			isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			isset( $_GET['dir'] ) ? sanitize_text_field( wp_unslash( $_GET['dir'] ) ) : null // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$viol_state_args = array_filter(
			array(
				'tab'           => 'violations',
				'sort'          => $viol_sort['key'],
				'dir'           => strtolower( $viol_sort['dir'] ),
				'v_surface'     => $v_surface,
				'v_directive'   => $v_directive,
				'v_disposition' => $v_disposition,
				'v_blocked'     => $v_blocked,
				'v_occ_min'     => $v_occ_min,
				'v_seen_from'   => $v_seen_from,
				'v_seen_to'     => $v_seen_to,
			)
		);

		$viol_count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}csp_violation_reports WHERE {$viol_where_sql}";
		if ( ! empty( $viol_args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$viol_count_sql = $wpdb->prepare( $viol_count_sql, ...$viol_args );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$viol_total = (int) $wpdb->get_var( $viol_count_sql );

		$viol_pages    = max( 1, (int) ceil( $viol_total / $per_page ) );
		$viol_page_num = min( max( 1, (int) ( isset( $_GET['v_paged'] ) ? $_GET['v_paged'] : 1 ) ), $viol_pages );
		$viol_offset   = ( $viol_page_num - 1 ) * $per_page;

		$viol_data_args = array_merge( $viol_args, array( $per_page, $viol_offset ) );
		$viol_data_sql  = "SELECT * FROM {$wpdb->prefix}csp_violation_reports WHERE {$viol_where_sql} " . Table_Query::order_by_sql( $viol_sort ) . ' LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$viol_data_sql = $wpdb->prepare( $viol_data_sql, ...$viol_data_args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$violations_raw = $wpdb->get_results( $viol_data_sql, ARRAY_A );
		$violations     = ! empty( $violations_raw ) ? $violations_raw : array();
		?>
	<form method="get" action="" class="wp-csp-filter-form">
		<input type="hidden" name="page" value="csp-automation-manager-dashboard" />
		<input type="hidden" name="tab" value="violations" />
		<label>
			<?php esc_html_e( 'Surface', 'csp-automation-manager' ); ?>
			<select name="v_surface[]" multiple size="4">
				<?php foreach ( $surfaces as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php echo in_array( $s, $v_surface, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Directive', 'csp-automation-manager' ); ?>
			<select name="v_directive[]" multiple size="4">
				<?php foreach ( $v_directive_options as $d ) : ?>
				<option value="<?php echo esc_attr( $d ); ?>" <?php echo in_array( $d, $v_directive, true ) ? 'selected' : ''; ?>><?php echo esc_html( $d ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Disposition', 'csp-automation-manager' ); ?>
			<select name="v_disposition[]" multiple size="2">
				<?php foreach ( array( 'report', 'enforce' ) as $d ) : ?>
				<option value="<?php echo esc_attr( $d ); ?>" <?php echo in_array( $d, $v_disposition, true ) ? 'selected' : ''; ?>><?php echo esc_html( ucfirst( $d ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Blocked URI contains', 'csp-automation-manager' ); ?>
			<input type="text" name="v_blocked" value="<?php echo esc_attr( $v_blocked ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'Occurrences at least', 'csp-automation-manager' ); ?>
			<input type="number" min="0" name="v_occ_min" style="width:80px" value="<?php echo esc_attr( null !== $v_occ_min ? (string) $v_occ_min : '' ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'Last seen from', 'csp-automation-manager' ); ?>
			<input type="date" name="v_seen_from" value="<?php echo esc_attr( $v_seen_from ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'to', 'csp-automation-manager' ); ?>
			<input type="date" name="v_seen_to" value="<?php echo esc_attr( $v_seen_to ); ?>" />
		</label>
		<?php submit_button( __( 'Filter', 'csp-automation-manager' ), 'secondary', 'filter_violations', false ); ?>
	</form>
	<table class="widefat fixed striped" style="margin-top:1em">
		<thead>
			<tr>
				<?php
				echo Table_Query::sort_header( __( 'Surface', 'csp-automation-manager' ), 'surface', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes internally.
				echo Table_Query::sort_header( __( 'Blocked URI', 'csp-automation-manager' ), 'blocked_uri', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Directive', 'csp-automation-manager' ), 'directive', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Occurrences', 'csp-automation-manager' ), 'occurrences', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Last Seen', 'csp-automation-manager' ), 'last_seen', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Table_Query::sort_header( __( 'Disposition', 'csp-automation-manager' ), 'disposition', $viol_sort_whitelist, $viol_sort, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<th><?php esc_html_e( 'Details', 'csp-automation-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $violations as $v ) : ?>
		<tr>
			<td><?php echo esc_html( $v['profile_surface'] ); ?></td>
			<td><code style="word-break:break-all"><?php echo esc_html( $v['blocked_uri'] ); ?></code></td>
			<td><code><?php echo esc_html( $v['violated_directive'] ); ?></code></td>
			<td><?php echo esc_html( number_format( (int) $v['occurrence_count'] ) ); ?></td>
			<td><?php echo esc_html( $v['reported_at'] ); ?></td>
			<td><?php echo esc_html( $v['disposition'] ); ?></td>
			<td>
				<?php
				$meta_fields = array();
				if ( 0 === strpos( (string) $v['blocked_uri'], 'data:' ) ) {
					$meta_fields[ __( 'Data URI payload', 'csp-automation-manager' ) ] = mb_substr( (string) $v['blocked_uri'], 0, 200 );
				}
				if ( ! empty( $v['document_uri'] ) ) {
					$meta_fields[ __( 'Page URL', 'csp-automation-manager' ) ] = (string) $v['document_uri'];
				}
				if ( ! empty( $v['source_file'] ) ) {
					$location = (string) $v['source_file'];
					if ( '' !== (string) ( $v['line_number'] ?? '' ) ) {
						$location .= ':' . $v['line_number'];
						if ( '' !== (string) ( $v['column_number'] ?? '' ) ) {
							$location .= ':' . $v['column_number'];
						}
					}
					$meta_fields[ __( 'Source file', 'csp-automation-manager' ) ] = $location;
				}
				if ( ! empty( $v['referrer'] ) ) {
					$meta_fields[ __( 'Referrer', 'csp-automation-manager' ) ] = (string) $v['referrer'];
				}
				if ( ! empty( $v['user_agent'] ) ) {
					$meta_fields[ __( 'User agent', 'csp-automation-manager' ) ] = (string) $v['user_agent'];
				}
				if ( ! empty( $v['sample'] ) ) {
					$meta_fields[ __( 'Sample', 'csp-automation-manager' ) ] = (string) $v['sample'];
				}

				$has_meta = ! empty( $v['document_uri'] ) || ! empty( $v['source_file'] ) || ! empty( $v['referrer'] ) || ! empty( $v['user_agent'] ) || ! empty( $v['sample'] );
				?>
				<?php if ( $has_meta ) : ?>
				<span class="dashicons dashicons-info-outline wp-csp-meta-icon" tabindex="0">
					<span class="wp-csp-meta-popover" role="tooltip">
						<?php foreach ( $meta_fields as $meta_label => $meta_value ) : ?>
						<div class="wp-csp-meta-row">
							<strong><?php echo esc_html( $meta_label ); ?>:</strong>
							<code><?php echo esc_html( $meta_value ); ?></code>
						</div>
						<?php endforeach; ?>
					</span>
				</span>
				<?php else : ?>
				<span class="dashicons dashicons-info-outline wp-csp-meta-icon wp-csp-meta-icon--empty" title="<?php esc_attr_e( 'No metadata captured for this violation', 'csp-automation-manager' ); ?>"></span>
				<?php endif; ?>
			</td>
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

		<?php echo Table_Query::pagination( $viol_page_num, $viol_pages, $viol_state_args, $base_url, 'v_paged' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php elseif ( 'scan-log' === $tab ) : ?>
	<!-- ── Scan log tab ───────────────────────────────────────────────────── -->
		<?php
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; only $wpdb->prefix used in query.
		$scan_logs_raw = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}csp_scan_logs ORDER BY started_at DESC LIMIT 20", ARRAY_A );
		$scan_logs     = ! empty( $scan_logs_raw ) ? $scan_logs_raw : array();
		?>
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
			if ( $log['completed_at'] && $log['started_at'] ) {
				$diff     = strtotime( $log['completed_at'] ) - strtotime( $log['started_at'] );
				$duration = $diff . 's';
			}
			?>
		<tr>
			<td><?php echo esc_html( ucfirst( $log['trigger_type'] ) ); ?></td>
			<td><?php echo esc_html( ucfirst( $log['status'] ) ); ?></td>
			<td>+<?php echo esc_html( $log['sources_added'] ); ?> / -<?php echo esc_html( $log['sources_removed'] ); ?></td>
			<td>+<?php echo esc_html( $log['hashes_added'] ); ?> / -<?php echo esc_html( $log['hashes_removed'] ); ?></td>
			<td><?php echo $log['policy_changed'] ? esc_html__( 'Yes', 'csp-automation-manager' ) : '&mdash;'; ?></td>
			<td><?php echo esc_html( $log['started_at'] ); ?></td>
			<td><?php echo esc_html( $duration ); ?></td>
		</tr>
		<?php endforeach; ?>
		<?php if ( empty( $scan_logs ) ) : ?>
		<tr><td colspan="7"><?php esc_html_e( 'No scans run yet.', 'csp-automation-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php elseif ( 'settings' === $tab ) : ?>
	<!-- ── Settings tab ───────────────────────────────────────────────────── -->
		<?php
		$learning_window            = new \WP_CSP\CSP\Learning_Window();
		$learning_status            = $learning_window->is_open() ? __( 'Open', 'csp-automation-manager' ) : __( 'Locked', 'csp-automation-manager' );
		$current_report_endpoint    = esc_url_raw( rest_url( 'csp-manager/v1/report' ) );
		$configured_report_endpoint = (string) get_option( 'wp_csp_report_endpoint_url', '' );
		$configured_policy_header   = (string) get_option( 'wp_csp_policy_header_name', '' );
		$reporting_transport        = \WP_CSP\CSP\Policy_Builder::sanitize_reporting_transport( get_option( 'wp_csp_reporting_transport', 'report-uri' ) );
		$settings_automation_config = ( new \WP_CSP\CSP\Automation_Config() )->all();
		$automation_mode_labels     = \WP_CSP\CSP\Automation_Config::mode_labels();
		$automation_directives      = array( 'default-src', 'img-src', 'font-src', 'media-src', 'manifest-src' );
		$automation_schemes         = array( 'https', 'wss' );
		?>
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
					<th><?php esc_html_e( 'Maximum per run', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Directive scope', 'csp-automation-manager' ); ?></th>
					<th><?php esc_html_e( 'Allowed schemes', 'csp-automation-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $surfaces as $surface ) : ?>
				<?php $surface_config = $settings_automation_config[ $surface ] ?? \WP_CSP\CSP\Automation_Config::DEFAULT_SURFACE_CONFIG; ?>
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

		<?php submit_button(); ?>
	</form>
	<?php endif; ?>

	</div><!-- .tab-content -->
</div><!-- .wp-csp-wrap -->
