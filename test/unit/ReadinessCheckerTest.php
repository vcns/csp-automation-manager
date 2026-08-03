<?php
/**
 * Unit tests for plugin readiness reporting.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_CSP\Activator;
use WP_CSP\Admin\Readiness_Checker;

class ReadinessCheckerTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	public function test_report_contains_plugin_schema_and_runtime_health(): void {
		$get_var_queue = array();
		foreach ( Activator::get_table_suffixes() as $suffix ) {
			$get_var_queue[] = 'wp_' . $suffix;
			$get_var_queue[] = 3;
		}
		$get_var_queue[] = 'wp_csp_policy_profiles';
		$get_var_queue[] = 4;
		$get_var_queue[] = 'wp_csp_policy_versions';
		$get_var_queue[] = 4;

		$GLOBALS['_wpdb_get_var_queue'] = $get_var_queue;
		$GLOBALS['_wp_options']         = array(
			'wp_csp_db_version'         => WP_CSP_DB_VERSION,
			'wp_csp_automation_config'  => array(
				'frontend' => array( 'mode' => 'manual' ),
				'admin'    => array( 'mode' => 'manual' ),
				'login'    => array( 'mode' => 'manual' ),
				'api'      => array( 'mode' => 'manual' ),
			),
			'wp_csp_policy_header_name' => 'X-Origin-CSP-Policy',
		);
		$GLOBALS['_wp_cron']            = array( 'wp_csp_daily_scan' => time() + 3600 );

		$report = ( new Readiness_Checker() )->get_report();

		$this->assertArrayHasKey( 'plugin', $report );
		$this->assertArrayHasKey( 'schema', $report );
		$this->assertArrayHasKey( 'health', $report );
		$this->assertCount( count( Activator::get_table_suffixes() ), $report['schema'] );
		$this->assertSame( 'pass', $report['plugin'][1]['status'] );
		$this->assertSame( 'pass', $report['schema'][0]['status'] );
		$this->assertSame( 'pass', $report['health'][0]['status'] );
		$this->assertStringContainsString( 'X-Origin-CSP-Policy', $report['health'][3]['value'] );
	}
}
