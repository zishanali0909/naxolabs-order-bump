<?php
/**
 * Tests for Naxoorbu_Frontend::get_skin_style() — skin, layout and colors
 * shared by the classic and block checkout.
 *
 * @package NaxolabsOrderBump
 */
class Test_Skin_Style extends WP_UnitTestCase {

    /** Test: Skin 1 uses the header-row layout and its saved colors. */
    public function test_skin1() {
        $style = Naxoorbu_Frontend::get_skin_style( [ 'skin' => 'skin1', 'skin1_bg_color' => '#111111', 'skin1_text_color' => '#222222' ] );

        $this->assertSame( [ 'skin' => 'skin1', 'layout' => 'skin1', 'bg_color' => '#111111', 'text_color' => '#222222' ], $style );
    }

    /** Test: Skin 2 uses its own layout and colors, with defaults when unsaved. */
    public function test_skin2() {
        $style = Naxoorbu_Frontend::get_skin_style( [ 'skin' => 'skin2' ] );

        $this->assertSame( 'skin2', $style['layout'] );
        $this->assertSame( '#e8f7f9', $style['bg_color'] );
    }

    /** Test: an unknown skin falls back to Skin 1 entirely. */
    public function test_unknown_skin_falls_back() {
        $style = Naxoorbu_Frontend::get_skin_style( [ 'skin' => 'nope', 'skin1_bg_color' => '#111111' ] );

        $this->assertSame( 'skin1', $style['skin'] );
        $this->assertSame( 'skin1', $style['layout'] );
        $this->assertSame( '#111111', $style['bg_color'] );
    }

    /** Test: an extension can build a skin on the Skin 2 layout. */
    public function test_layout_filter() {
        $add = function ( $skins ) {
            $skins[] = 'skin9';
            return $skins;
        };
        $layout = function ( $layout, $skin ) {
            return 'skin9' === $skin ? 'skin2' : $layout;
        };
        add_filter( 'naxoorbu_allowed_skins', $add );
        add_filter( 'naxoorbu_skin_layout', $layout, 10, 2 );

        $this->assertSame( 'skin2', Naxoorbu_Frontend::get_skin_style( [ 'skin' => 'skin9' ] )['layout'] );

        remove_filter( 'naxoorbu_allowed_skins', $add );
        remove_filter( 'naxoorbu_skin_layout', $layout, 10 );
    }
}
