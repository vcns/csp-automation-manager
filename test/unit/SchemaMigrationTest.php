<?php
/**
 * Schema activation and migration metadata tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_CSP\Activator;

class SchemaMigrationTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	public function test_fresh_activation_creates_expected_custom_tables(): void {
		Activator::activate();

		$schema = implode( "\n\n", $GLOBALS['_dbdelta_queries'] );

		foreach ( $this->expected_tables() as $table ) {
			$this->assertStringContainsString( "CREATE TABLE {$GLOBALS['wpdb']->prefix}{$table}", $schema );
		}

		$this->assertSame( WP_CSP_DB_VERSION, get_option( 'wp_csp_db_version' ) );
		$this->assertSame( WP_CSP_DB_VERSION, get_option( 'wp_csp_schema_verified_version' ) );
	}

	public function test_schema_v6_violation_rollup_columns_are_declared(): void {
		Activator::activate();

		$schema = implode( "\n\n", $GLOBALS['_dbdelta_queries'] );

		$this->assertStringContainsString( 'first_reported_at datetime DEFAULT NULL', $schema );
		$this->assertStringContainsString( 'last_reported_at datetime DEFAULT NULL', $schema );
		$this->assertStringContainsString( 'UNIQUE KEY fingerprint (fingerprint)', $schema );
	}

	public function test_policy_decision_ledger_columns_are_declared(): void {
		Activator::activate();

		$schema = implode( "\n\n", $GLOBALS['_dbdelta_queries'] );

		$this->assertStringContainsString( 'decision_fingerprint varchar(64) NOT NULL', $schema );
		$this->assertStringContainsString( 'suppression_active tinyint(1) NOT NULL DEFAULT 0', $schema );
		$this->assertStringContainsString( 'KEY suppression_active (suppression_active)', $schema );
	}

	public function test_policy_versions_schema_uses_safe_trigger_lookup_index_name(): void {
		Activator::activate();

		$schema = implode( "\n\n", $GLOBALS['_dbdelta_queries'] );

		$this->assertStringContainsString( 'KEY trigger_lookup (trigger_type, trigger_id)', $schema );
		$this->assertStringNotContainsString( 'KEY trigger (trigger_type, trigger_id)', $schema );
	}

	public function test_schema_v8_sort_and_filter_indexes_are_declared(): void {
		Activator::activate();

		$schema = implode( "\n\n", $GLOBALS['_dbdelta_queries'] );

		$this->assertStringContainsString( 'KEY last_seen_at (last_seen_at)', $schema );
		$this->assertStringContainsString( 'KEY source_host (source_host(191))', $schema );
		$this->assertStringContainsString( 'KEY occurrence_count (occurrence_count)', $schema );
	}

	/**
	 * @dataProvider legacy_schema_version_provider
	 */
	public function test_activation_advances_legacy_schema_versions_to_current( string $legacy_version ): void {
		update_option( 'wp_csp_db_version', $legacy_version );

		Activator::activate();

		$this->assertSame( WP_CSP_DB_VERSION, get_option( 'wp_csp_db_version' ) );
	}

	public function test_repeated_activation_remains_idempotent_for_schema_version(): void {
		Activator::activate();
		Activator::activate();

		$this->assertSame( WP_CSP_DB_VERSION, get_option( 'wp_csp_db_version' ) );
	}

	public function test_missing_table_names_reports_absent_plugin_tables(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array(
			'wp_csp_policy_profiles',
			null,
			'wp_csp_hash_inventory',
			'wp_csp_violation_reports',
			'wp_csp_scan_logs',
			'wp_csp_entitlements',
			'wp_csp_processed_events',
			'wp_csp_audit_log',
			'wp_csp_policy_change_decisions',
			null,
			'wp_csp_decision_rule_evaluations',
		);

		$this->assertSame(
			array( 'wp_csp_source_inventory', 'wp_csp_policy_versions' ),
			Activator::get_missing_table_names()
		);
	}

	public function test_initial_policy_version_seed_stops_when_table_is_missing(): void {
		$GLOBALS['_wpdb_get_var_queue'] = array( null );

		$method = new ReflectionMethod( Activator::class, 'seed_initial_policy_versions' );
		$method->setAccessible( true );
		$method->invoke( null );

		$this->assertSame( array(), $GLOBALS['_wpdb_inserted_rows'] );
		$this->assertSame( array(), $GLOBALS['_wpdb_queries'] );
	}

	public static function legacy_schema_version_provider(): array {
		return array(
			'v1' => array( '1' ),
			'v2' => array( '2' ),
			'v3' => array( '3' ),
			'v4' => array( '4' ),
			'v5' => array( '5' ),
			'v6' => array( '6' ),
			'v7' => array( '7' ),
		);
	}

	private function expected_tables(): array {
		return array(
			'csp_policy_profiles',
			'csp_source_inventory',
			'csp_hash_inventory',
			'csp_violation_reports',
			'csp_scan_logs',
			'csp_entitlements',
			'csp_processed_events',
			'csp_audit_log',
			'csp_policy_change_decisions',
			'csp_policy_versions',
			'csp_decision_rule_evaluations',
		);
	}
}
