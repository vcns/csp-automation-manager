<?php
/**
 * Unit tests for WP_CSP\Admin\Admin_UI.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use WP_CSP\Admin\Admin_UI;
use WP_CSP\Plugin;

class AdminUITest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_globals();
	}

	public function test_plugin_action_links_include_settings_link(): void {
		$ui = $this->make_admin_ui();

		$links = $ui->add_plugin_action_links(
			array(
				'deactivate' => '<a href="#">Deactivate</a>',
			)
		);

		$this->assertArrayHasKey( 'settings', $links );
		$this->assertArrayHasKey( 'reset', $links );
		$this->assertStringContainsString( 'admin.php?page=csp-automation-manager-settings', $links['settings'] );
		$this->assertStringContainsString( 'admin.php?page=csp-automation-manager-readiness#wp-csp-reset', $links['reset'] );
		$this->assertStringContainsString( 'Settings', $links['settings'] );
		$this->assertStringContainsString( 'Reset', $links['reset'] );
		$this->assertSame( 'settings', array_key_first( $links ) );
	}

	public function test_plugin_row_meta_describes_update_posture(): void {
		$ui = $this->make_admin_ui();

		$links = $ui->add_plugin_row_meta(
			array( '<a href="https://example.com">Visit plugin site</a>' ),
			plugin_basename( WP_CSP_FILE )
		);

		$this->assertStringContainsString( 'WordPress.org only', implode( ' ', $links ) );
		$this->assertStringContainsString( 'no custom updater', implode( ' ', $links ) );
	}

	public function test_plugin_row_meta_ignores_other_plugins(): void {
		$ui       = $this->make_admin_ui();
		$original = array( '<a href="https://example.com">Visit plugin site</a>' );

		$links = $ui->add_plugin_row_meta( $original, 'other-plugin/other-plugin.php' );

		$this->assertSame( $original, $links );
	}

	private function make_admin_ui(): Admin_UI {
		$reflection = new ReflectionClass( Plugin::class );

		return new Admin_UI( $reflection->newInstanceWithoutConstructor() );
	}
}
