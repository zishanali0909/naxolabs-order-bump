<?php
/**
 * Tests for the links to the Pro version.
 *
 * @package NaxolabsOrderBump
 */

class Test_Pro_Links extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'naxoorbu_pro_active' );
		parent::tear_down();
	}

	/** "Go Pro" is added to the Plugins screen links, pointing to the Pro page in a new tab. */
	public function test_go_pro_link() {
		$links = ( new Naxoorbu_Admin() )->plugin_action_links( array( 'deactivate' => 'Deactivate' ) );

		$this->assertSame( 'Deactivate', $links['deactivate'], 'Existing links stay.' );
		$this->assertStringContainsString( 'href="' . Naxoorbu_Admin::PRO_URL . '"', $links['naxoorbu_go_pro'] );
		$this->assertStringContainsString( 'target="_blank"', $links['naxoorbu_go_pro'] );
	}

	/** The "Upgrade to Pro" button of the plugin's screens. */
	public function test_upgrade_button() {
		ob_start();
		Naxoorbu_Admin::render_upgrade_button();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'naxoorbu-btn-pro', $html );
		$this->assertStringContainsString( 'href="' . Naxoorbu_Admin::PRO_URL . '"', $html );
	}

	/** No link to Pro while Pro is active. */
	public function test_hidden_while_pro_is_active() {
		add_filter( 'naxoorbu_pro_active', '__return_true' );

		$links = ( new Naxoorbu_Admin() )->plugin_action_links( array( 'deactivate' => 'Deactivate' ) );
		ob_start();
		Naxoorbu_Admin::render_upgrade_button();

		$this->assertArrayNotHasKey( 'naxoorbu_go_pro', $links );
		$this->assertSame( '', ob_get_clean(), 'No button either.' );
	}
}
