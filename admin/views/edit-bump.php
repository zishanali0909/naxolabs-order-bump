<?php
/**
 * Edit Bump Template — Naxolabs Order Bump
 *
 * Variables available from Naxoorbu_Admin::render_edit_page():
 *   $edit_id, $bump, $meta, $saved_products, $all_categories, $tab
 *
 * @package NaxolabsOrderBump
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap naxoorbu-wrap">
    <h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'Edit Bump', 'naxolabs-order-bump' ); ?></h1>
    <hr class="wp-header-end">
    <div class="naxoorbu-header">
        <div class="naxoorbu-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=naxolabs-order-bump' ) ); ?>" class="naxoorbu-back-btn">
                ← <?php esc_html_e( 'Back', 'naxolabs-order-bump' ); ?>
            </a>
            <div class="naxoorbu-logo">⚡</div>
            <div>
                <h1>
                    <?php
                    if ( $edit_id ) {
                        /* translators: %s: bump title */
                        /* translators: %s: bump post title */
                printf( esc_html__( 'Edit: %s', 'naxolabs-order-bump' ), esc_html( $bump->post_title ) );
                    } else {
                        esc_html_e( 'New Order Bump', 'naxolabs-order-bump' );
                    }
                    ?>
                </h1>
                <p><?php esc_html_e( 'Configure your checkout order bump', 'naxolabs-order-bump' ); ?></p>
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <label class="naxoorbu-toggle naxoorbu-toggle-lg">
                <input type="checkbox" id="naxoorbu-bump-status-top" <?php checked( $meta['bump_status'], 'publish' ); ?>>
                <span class="naxoorbu-toggle-slider"></span>
            </label>
            <span id="naxoorbu-status-label" style="font-weight:700;font-size:13px;">
                <?php echo $meta['bump_status'] === 'publish' ? esc_html__( 'Active', 'naxolabs-order-bump' ) : esc_html__( 'Inactive', 'naxolabs-order-bump' ); ?>
            </span>
            <button form="naxoorbu-edit-form" type="submit" class="naxoorbu-btn naxoorbu-btn-primary">💾 <?php esc_html_e( 'Save', 'naxolabs-order-bump' ); ?></button>
        </div>
    </div>

    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status flag from redirect, no data modification
    $naxoorbu_status = isset( $_GET['naxoorbu_status'] ) ? sanitize_key( $_GET['naxoorbu_status'] ) : '';
    if ( $naxoorbu_status === 'saved' ) : ?>
        <div class="naxoorbu-notice naxoorbu-notice-success" style="margin-top:10px;">✅ <?php esc_html_e( 'Order Bump saved successfully.', 'naxolabs-order-bump' ); ?></div>
    <?php elseif ( $naxoorbu_status === 'saved_no_products' ) : ?>
        <div class="naxoorbu-notice naxoorbu-notice-info" style="margin-top:10px;">⚠️ <?php esc_html_e( 'Saved, but no products added yet. Go to Products tab to add products.', 'naxolabs-order-bump' ); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="naxoorbu-edit-form">
        <?php wp_nonce_field( 'naxoorbu_save_bump', 'naxoorbu_nonce' ); ?>
        <input type="hidden" name="action"  value="naxoorbu_save_bump">
        <input type="hidden" name="bump_id" value="<?php echo intval( $edit_id ); ?>">
        <input type="hidden" name="active_tab" value="<?php echo esc_attr( $tab ); ?>">
        <input type="hidden" name="naxoorbu_settings[bump_status]" id="naxoorbu-status-field" value="<?php echo esc_attr( $meta['bump_status'] ); ?>">

        <div class="naxoorbu-name-bar">
            <label class="naxoorbu-label"><?php esc_html_e( 'Bump Name (Internal):', 'naxolabs-order-bump' ); ?></label>
            <input type="text" name="bump_title"
                   value="<?php echo esc_attr( $bump ? $bump->post_title : '' ); ?>"
                   class="naxoorbu-input naxoorbu-input-name"
                   placeholder="<?php esc_attr_e( 'e.g. Accounting Upsell', 'naxolabs-order-bump' ); ?>" required>
        </div>

        <div class="naxoorbu-tabs-wrap">
            <div class="naxoorbu-tabs">
                <a href="#" class="naxoorbu-tab <?php echo $tab === 'design' ? 'active' : ''; ?>" data-tab="design">📐 <?php esc_html_e( 'Design', 'naxolabs-order-bump' ); ?></a>
                <a href="#" class="naxoorbu-tab <?php echo $tab === 'products' ? 'active' : ''; ?>" data-tab="products">🛍️ <?php esc_html_e( 'Products', 'naxolabs-order-bump' ); ?></a>
                <a href="#" class="naxoorbu-tab <?php echo $tab === 'rules' ? 'active' : ''; ?>" data-tab="rules">🎯 <?php esc_html_e( 'Rules', 'naxolabs-order-bump' ); ?></a>
            </div>
        </div>

        <!-- ═══ TAB: DESIGN ═══ -->
        <div class="naxoorbu-tab-content" id="tab-design" style="<?php echo $tab !== 'design' ? 'display:none' : ''; ?>">
            <div class="naxoorbu-edit-layout">
                <div class="naxoorbu-edit-main">

                    <!-- CTA -->
                    <div class="naxoorbu-card">
                        <div class="naxoorbu-card-header">📣 <?php esc_html_e( 'Call To Action Text', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body">
                            <input type="text" name="naxoorbu_settings[headline]"
                                   value="<?php echo esc_attr( $meta['headline'] ); ?>"
                                   maxlength="200"
                                   class="naxoorbu-input"
                                   placeholder="<?php esc_attr_e( 'Yes! Add {{product_name}} to my order', 'naxolabs-order-bump' ); ?>">
                            <p class="naxoorbu-help">
                                <?php
                                /* translators: %s: template tag */
                                /* translators: %s: template tag code */
                    printf( esc_html__( 'Use %s to show product name dynamically.', 'naxolabs-order-bump' ), '<code>{{product_name}}</code>' );
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Per-product description -->
                    <div class="naxoorbu-card" id="naxoorbu-design-desc-card">
                        <div class="naxoorbu-card-header">📝 <?php esc_html_e( 'Product Descriptions', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body" style="padding:14px 16px;">
                            <?php if ( ! empty( $saved_products ) ) : ?>
                                <?php
                                // Store descriptions as JSON for JS preview
                                $desc_json = [];
                                foreach ( $saved_products as $pi => $pp ) {
                                    $desc_json[ $pi ] = $pp['description'] ?? '';
                                }
                                ?>

                                <!-- Product switcher buttons — NO inline onclick, handled by admin.js -->
                                <div class="naxoorbu-desc-switcher-wrap" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;padding:10px;background:#F5F3FF;border-radius:8px;border:1px solid #E5E2FF;">
                                    <?php foreach ( $saved_products as $pi => $pp ) :
                                        $name = esc_html( mb_strlen( $pp['name'] ) > 40 ? mb_substr( $pp['name'], 0, 40 ) . '…' : $pp['name'] );
                                    ?>
                                        <button type="button"
                                                class="naxoorbu-desc-switch-btn <?php echo $pi === 0 ? 'naxoorbu-switch-active' : ''; ?>"
                                                data-index="<?php echo intval( $pi ); ?>"
                                                data-thumb="<?php echo esc_url( $pp['thumb'] ?? '' ); ?>"
                                                id="naxoorbu-swbtn-<?php echo intval( $pi ); ?>">
                                            <?php echo esc_html( $name ); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- WP Editor per product -->
                                <div style="position:relative;">
                                    <?php foreach ( $saved_products as $pi => $pp ) :
                                        $eid   = 'naxoorbu_prod_editor_' . $pi;
                                        $fname = 'naxoorbu_settings[products][' . $pi . '][description]';
                                        $wrap_style = $pi === 0
                                            ? 'display:block;'
                                            : 'position:absolute;top:0;left:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;';
                                    ?>
                                        <div id="naxoorbu-desc-panel-<?php echo intval( $pi ); ?>" class="naxoorbu-desc-panel" style="<?php echo esc_attr( $wrap_style ); ?>">
                                            <?php wp_editor(
                                                $pp['description'] ?? '',
                                                $eid,
                                                [
                                                    'textarea_name' => $fname,
                                                    'textarea_rows' => 8,
                                                    'media_buttons' => false,
                                                    'teeny'         => false,
                                                    'quicktags'     => true,
                                                    'editor_height' => 220,
                                                    'tinymce'       => [
                                                        'toolbar1' => 'formatselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,|,bullist,numlist,|,link,unlink,|,removeformat',
                                                        'toolbar2' => '',
                                                        'block_formats' => 'Paragraph=p;Heading 3=h3;Heading 4=h4;Heading 5=h5',
                                                        'fontsize_formats' => '12px 13px 14px 16px 18px 20px 24px',
                                                    ],
                                                ]
                                            ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <p class="naxoorbu-help" style="margin-top:6px;">
                                    <?php esc_html_e( 'Use short promotional text. HTML supported: <b>, <i>, <ul>, <li> etc.', 'naxolabs-order-bump' ); ?>
                                </p>

                            <?php else : ?>
                                <p class="naxoorbu-help" style="padding:12px 0;text-align:center;">
                                    <?php
                                    echo wp_kses_post(
                                        sprintf(
                                            /* translators: %1$s: opening HTML tag, %2$s: closing HTML tag */
                                            __( 'First add products in the %1$sProducts tab%2$s.', 'naxolabs-order-bump' ),
                                            '<strong>', '</strong>'
                                        )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Display Options -->
                    <div class="naxoorbu-card">
                        <div class="naxoorbu-card-header">🎨 <?php esc_html_e( 'Display Options', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body naxoorbu-grid-2">
                            <div class="naxoorbu-field">
                                <label class="naxoorbu-label"><?php esc_html_e( 'Position on Checkout', 'naxolabs-order-bump' ); ?></label>
                                <select name="naxoorbu_settings[position]" class="naxoorbu-select">
                                    <option value="before_payment" <?php selected( $meta['position'], 'before_payment' ); ?>><?php esc_html_e( 'Before Payment Button (Default)', 'naxolabs-order-bump' ); ?></option>
                                    <option value="after_payment" <?php selected( $meta['position'], 'after_payment' ); ?>><?php esc_html_e( 'After Payment Button', 'naxolabs-order-bump' ); ?></option>
                                </select>
                            </div>
                            <div class="naxoorbu-field">
                                <label class="naxoorbu-label"><?php esc_html_e( 'Skin / Layout', 'naxolabs-order-bump' ); ?></label>
                                <select name="naxoorbu_settings[skin]" class="naxoorbu-select">
                                    <option value="skin1" <?php selected( $meta['skin'], 'skin1' ); ?>><?php esc_html_e( 'Skin 1', 'naxolabs-order-bump' ); ?></option>
                                    <option value="skin2" <?php selected( $meta['skin'], 'skin2' ); ?>><?php esc_html_e( 'Skin 2', 'naxolabs-order-bump' ); ?></option>
                                </select>
                            </div>
                            <div class="naxoorbu-field">
                                <label class="naxoorbu-label"><?php esc_html_e( 'Header Color', 'naxolabs-order-bump' ); ?></label>
                                <!-- Skin 1 colors -->
                                <div id="naxoorbu-colors-skin1" style="<?php echo ( $meta['skin'] ?? 'skin1' ) === 'skin2' ? 'display:none' : 'display:flex'; ?>;align-items:center;gap:16px;flex-wrap:nowrap;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;color:#374151;"><?php esc_html_e( 'Background', 'naxolabs-order-bump' ); ?></span>
                                        <input type="color" name="naxoorbu_settings[skin1_bg_color]" id="naxoorbu-skin1-bg" value="<?php echo esc_attr( $meta['skin1_bg_color'] ?? '#FFFDE7' ); ?>" style="width:40px;height:32px;border:1px solid #E5E2FF;border-radius:6px;cursor:pointer;padding:2px;">
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;color:#374151;"><?php esc_html_e( 'Text', 'naxolabs-order-bump' ); ?></span>
                                        <input type="color" name="naxoorbu_settings[skin1_text_color]" id="naxoorbu-skin1-text" value="<?php echo esc_attr( $meta['skin1_text_color'] ?? '#155724' ); ?>" style="width:40px;height:32px;border:1px solid #E5E2FF;border-radius:6px;cursor:pointer;padding:2px;">
                                    </div>
                                </div>
                                <!-- Skin 2 colors -->
                                <div id="naxoorbu-colors-skin2" style="<?php echo ( $meta['skin'] ?? 'skin1' ) === 'skin1' ? 'display:none' : 'display:flex'; ?>;align-items:center;gap:16px;flex-wrap:nowrap;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;color:#374151;"><?php esc_html_e( 'Background', 'naxolabs-order-bump' ); ?></span>
                                        <input type="color" name="naxoorbu_settings[skin2_bg_color]" id="naxoorbu-skin2-bg" value="<?php echo esc_attr( $meta['skin2_bg_color'] ?? '#e8f7f9' ); ?>" style="width:40px;height:32px;border:1px solid #E5E2FF;border-radius:6px;cursor:pointer;padding:2px;">
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;color:#374151;"><?php esc_html_e( 'Text', 'naxolabs-order-bump' ); ?></span>
                                        <input type="color" name="naxoorbu_settings[skin2_text_color]" id="naxoorbu-skin2-text" value="<?php echo esc_attr( $meta['skin2_text_color'] ?? '#155724' ); ?>" style="width:40px;height:32px;border:1px solid #E5E2FF;border-radius:6px;cursor:pointer;padding:2px;">
                                    </div>
                                </div>
                            </div>
                            <div class="naxoorbu-field">
                                <label class="naxoorbu-label"><?php esc_html_e( 'Options', 'naxolabs-order-bump' ); ?></label>
                                <label class="naxoorbu-checkbox-label">
                                    <input type="checkbox" name="naxoorbu_settings[show_original_price]" value="1" <?php checked( $meta['show_original_price'] ); ?>>
                                    <?php esc_html_e( 'Show Original (Strikethrough) Price', 'naxolabs-order-bump' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Badge Options -->
                    <div class="naxoorbu-card">
                        <div class="naxoorbu-card-header">🏷️ <?php esc_html_e( 'Badge / Ribbon', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                                <div>
                                    <strong style="font-size:13px;"><?php esc_html_e( 'Show Badge', 'naxolabs-order-bump' ); ?></strong>
                                    <p class="naxoorbu-help" style="margin:3px 0 0;"><?php esc_html_e( 'Display a ribbon/badge on the order bump (e.g. "Special Offer").', 'naxolabs-order-bump' ); ?></p>
                                </div>
                                <label class="naxoorbu-toggle" style="cursor:pointer;">
                                    <input type="checkbox"
                                           name="naxoorbu_settings[show_badge]"
                                           value="1"
                                           id="naxoorbu-show-badge-toggle"
                                           <?php checked( $meta['show_badge'] ?? 0 ); ?>>
                                    <span class="naxoorbu-toggle-slider"></span>
                                </label>
                            </div>
                            <div id="naxoorbu-badge-text-wrap" style="<?php echo empty( $meta['show_badge'] ) ? 'display:none;' : 'display:block;'; ?>border-top:1px solid #F0EEFF;padding-top:14px;">
                                <label class="naxoorbu-label"><?php esc_html_e( 'Badge Text', 'naxolabs-order-bump' ); ?></label>
                                <input type="text" name="naxoorbu_settings[badge_text]"
                                       value="<?php echo esc_attr( $meta['badge_text'] ?? 'Special Offer' ); ?>"
                                       maxlength="50"
                                       class="naxoorbu-input"
                                       placeholder="<?php esc_attr_e( 'Special Offer', 'naxolabs-order-bump' ); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Product Image Option -->
                    <div class="naxoorbu-card">
                        <div class="naxoorbu-card-header">🖼️ <?php esc_html_e( 'Product Image', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                                <div>
                                    <strong style="font-size:13px;"><?php esc_html_e( 'Show Product Image', 'naxolabs-order-bump' ); ?></strong>
                                    <p class="naxoorbu-help" style="margin:3px 0 0;"><?php esc_html_e( 'When enabled, the product image will be displayed on the checkout page.', 'naxolabs-order-bump' ); ?></p>
                                </div>
                                <label class="naxoorbu-toggle" style="cursor:pointer;">
                                    <input type="checkbox"
                                           name="naxoorbu_settings[show_product_image]"
                                           value="1"
                                           id="naxoorbu-show-img-toggle"
                                           <?php checked( $meta['show_product_image'] ?? 0 ); ?>>
                                    <span class="naxoorbu-toggle-slider"></span>
                                </label>
                            </div>

                            <div id="naxoorbu-img-options" style="<?php echo empty( $meta['show_product_image'] ) ? 'display:none;' : 'display:block;'; ?>border-top:1px solid #F0EEFF;padding-top:14px;">
                                <div class="naxoorbu-grid-2" style="gap:12px;">

                                    <div class="naxoorbu-field">
                                        <label class="naxoorbu-label"><?php esc_html_e( 'Image Type', 'naxolabs-order-bump' ); ?></label>
                                        <input type="hidden" name="naxoorbu_settings[image_type]" id="naxoorbu-img-type-val" value="<?php echo esc_attr( $meta['image_type'] ?? 'product' ); ?>">
                                        <div style="display:flex;border:1.5px solid #E5E2FF;border-radius:8px;overflow:hidden;">
                                            <button type="button" id="naxoorbu-imgtype-product"
                                                    class="naxoorbu-imgtype-btn <?php echo ( $meta['image_type'] ?? 'product' ) === 'product' ? 'naxoorbu-switch-active' : ''; ?>"
                                                    data-type="product"
                                                    style="flex:1;text-align:center;padding:9px 0;font-size:13px;font-weight:700;cursor:pointer;border:none;">
                                                <?php esc_html_e( 'Product', 'naxolabs-order-bump' ); ?>
                                            </button>
                                            <button type="button" id="naxoorbu-imgtype-custom"
                                                    class="naxoorbu-imgtype-btn <?php echo ( $meta['image_type'] ?? 'product' ) === 'custom' ? 'naxoorbu-switch-active' : ''; ?>"
                                                    data-type="custom"
                                                    style="flex:1;text-align:center;padding:9px 0;font-size:13px;font-weight:700;cursor:pointer;border:none;border-left:1.5px solid #E5E2FF;">
                                                <?php esc_html_e( 'Custom', 'naxolabs-order-bump' ); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="naxoorbu-field">
                                        <label class="naxoorbu-label"><?php esc_html_e( 'Width (px)', 'naxolabs-order-bump' ); ?></label>
                                        <input type="number" name="naxoorbu_settings[image_width]" value="<?php echo esc_attr( $meta['image_width'] ?? 96 ); ?>" class="naxoorbu-input" min="50" max="200" placeholder="96">
                                    </div>

                                    <div class="naxoorbu-field">
                                        <label class="naxoorbu-label"><?php esc_html_e( 'Image Position', 'naxolabs-order-bump' ); ?></label>
                                        <select name="naxoorbu_settings[image_position]" class="naxoorbu-select">
                                            <option value="left" <?php selected( $meta['image_position'] ?? 'left', 'left' ); ?>><?php esc_html_e( 'Left', 'naxolabs-order-bump' ); ?></option>
                                            <option value="right" <?php selected( $meta['image_position'] ?? 'left', 'right' ); ?>><?php esc_html_e( 'Right', 'naxolabs-order-bump' ); ?></option>
                                        </select>
                                    </div>

                                    <div class="naxoorbu-field" id="naxoorbu-custom-img-wrap" style="<?php echo ( $meta['image_type'] ?? 'product' ) !== 'custom' ? 'display:none;' : ''; ?>">
                                        <label class="naxoorbu-label"><?php esc_html_e( 'Custom Image URL', 'naxolabs-order-bump' ); ?></label>
                                        <div style="display:flex;gap:6px;">
                                            <input type="text" name="naxoorbu_settings[image_custom_url]" value="<?php echo esc_attr( $meta['image_custom_url'] ?? '' ); ?>" class="naxoorbu-input" id="naxoorbu-custom-img-url" placeholder="https://...">
                                            <button type="button" class="naxoorbu-btn naxoorbu-btn-secondary" id="naxoorbu-select-img-btn" style="white-space:nowrap;padding:8px 12px;">📁 <?php esc_html_e( 'Select', 'naxolabs-order-bump' ); ?></button>
                                        </div>
                                        <?php if ( ! empty( $meta['image_custom_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $meta['image_custom_url'] ); ?>" style="margin-top:8px;max-width:80px;border-radius:4px;">
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Sidebar: Preview -->
                <div class="naxoorbu-edit-sidebar">
                    <div class="naxoorbu-card">
                        <div class="naxoorbu-card-header">👁️ <?php esc_html_e( 'Preview', 'naxolabs-order-bump' ); ?></div>
                        <div class="naxoorbu-card-body" style="padding:10px;">
                            <div class="naxoorbu-preview-wrap" id="naxoorbu-preview-wrap-box">
                                <?php
                                $cur_skin  = $meta['skin'] ?? 'skin1';
                                $prev_bg   = $cur_skin === 'skin2' ? ( $meta['skin2_bg_color'] ?? '#e8f7f9' ) : ( $meta['skin1_bg_color'] ?? '#FFFDE7' );
                                $prev_text = $cur_skin === 'skin2' ? ( $meta['skin2_text_color'] ?? '#155724' ) : ( $meta['skin1_text_color'] ?? '#155724' );
                                ?>
                                <div class="naxoorbu-preview-bump naxoorbu-preview-<?php echo esc_attr( $cur_skin ); ?>" id="naxoorbu-preview-bump" data-skin="<?php echo esc_attr( $cur_skin ); ?>" style="position:relative;overflow:visible;">
                                    <!-- Preview Badge -->
                                    <div id="naxoorbu-preview-badge" class="naxoorbu-preview-badge" style="<?php echo ! empty( $meta['show_badge'] ) ? '' : 'display:none;'; ?>position:absolute;top:-8px;left:12px;background:#E15334;color:#fff;font-size:10px;font-weight:700;padding:3px 10px;border-radius:4px;z-index:2;text-transform:uppercase;letter-spacing:0.5px;">
                                        <?php echo esc_html( $meta['badge_text'] ?? 'Special Offer' ); ?>
                                    </div>
                                    <div class="naxoorbu-preview-headline" id="naxoorbu-preview-headline" style="background:<?php echo esc_attr( $prev_bg ); ?>;color:<?php echo esc_attr( $prev_text ); ?>;<?php echo ! empty( $meta['show_badge'] ) ? 'padding-top:18px;' : ''; ?>">
                                        <span class="naxoorbu-preview-arrow">➡</span>
                                        <span class="naxoorbu-preview-check">☐</span>
                                        <span id="naxoorbu-preview-headline-text"><?php echo wp_kses_post( $meta['headline'] ); ?></span>
                                    </div>
                                    <div id="naxoorbu-preview-desc-area" class="naxoorbu-preview-desc" style="<?php echo $cur_skin === 'skin2' ? 'background:' . esc_attr( $prev_bg ) . ';color:' . esc_attr( $prev_text ) . ';' : ''; ?>">
                                        <?php
                                        $show_img_preview = ! empty( $meta['show_product_image'] );
                                        $prev_img_url     = '';
                                        $prev_img_type    = $meta['image_type'] ?? 'product';

                                        if ( $show_img_preview ) {
                                            // Custom image URL
                                            if ( $prev_img_type === 'custom' && ! empty( $meta['image_custom_url'] ) ) {
                                                $prev_img_url = $meta['image_custom_url'];
                                            }
                                            // Product image
                                            elseif ( ! empty( $saved_products ) ) {
                                                // Try 1: Direct attachment URL
                                                $first_prod = wc_get_product( $saved_products[0]['id'] ?? 0 );
                                                if ( $first_prod ) {
                                                    $img_id = $first_prod->get_image_id();
                                                    if ( $img_id ) {
                                                        $prev_img_url = wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
                                                    }
                                                    // Try 2: Extract from WC get_image()
                                                    if ( ! $prev_img_url ) {
                                                        $img_html = $first_prod->get_image( 'woocommerce_thumbnail' );
                                                        if ( preg_match( '/src=["\']([^"\']+)/', $img_html, $m ) ) {
                                                            $prev_img_url = $m[1];
                                                        }
                                                    }
                                                }
                                                // Try 3: saved_products thumb
                                                if ( ! $prev_img_url && ! empty( $saved_products[0]['thumb'] ) ) {
                                                    $prev_img_url = $saved_products[0]['thumb'];
                                                }
                                                // Try 4: WooCommerce placeholder
                                                if ( ! $prev_img_url ) {
                                                    $prev_img_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
                                                }
                                            }
                                        }

                                        $prev_img_width = intval( $meta['image_width'] ?? 96 );
                                        $prev_img_pos   = $meta['image_position'] ?? 'left';
                                        ?>
                                        <div id="naxoorbu-preview-img-wrap" class="naxoorbu-preview-desc <?php echo ( $show_img_preview && $prev_img_url ) ? 'naxoorbu-img-visible' : ''; ?>" data-pos="<?php echo esc_attr($prev_img_pos); ?>">
                                            <img id="naxoorbu-preview-product-img"
                                                 src="<?php echo $prev_img_url ? esc_url( $prev_img_url ) : ''; ?>"
                                                 style="width:<?php echo intval( $prev_img_width ); ?>px;height:auto;border-radius:4px;float:<?php echo $prev_img_pos === 'right' ? 'right' : 'left'; ?>;margin:<?php echo $prev_img_pos === 'right' ? '0 0 6px 8px' : '0 8px 6px 0'; ?>;">
                                            <span id="naxoorbu-preview-desc-text">
                                            <?php
                                            if ( ! empty( $saved_products ) ) {
                                                $d = $saved_products[0]['description'] ?? '';
                                                echo $d ? wp_kses_post( wpautop($d) ) : '<em style="color:#9CA3AF">' . esc_html__( 'No description set.', 'naxolabs-order-bump' ) . '</em>';
                                            } else {
                                                echo '<em style="color:#9CA3AF">' . esc_html__( 'Add products to see preview.', 'naxolabs-order-bump' ) . '</em>';
                                            }
                                            ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="naxoorbu-preview-cta-row" id="naxoorbu-preview-cta-row" style="display:none;">
                                        <span style="color:#E15334;">➡</span>
                                        <span>☐</span>
                                        <span><?php echo wp_kses_post( $meta['headline'] ); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ TAB: PRODUCTS ═══ -->
        <div class="naxoorbu-tab-content" id="tab-products" style="<?php echo $tab !== 'products' ? 'display:none' : ''; ?>">
            <div class="naxoorbu-card">
                <div class="naxoorbu-card-header">
                    🛍️ <?php esc_html_e( 'All Products', 'naxolabs-order-bump' ); ?>
                    <span style="font-size:12px;font-weight:400;color:#6B7280;">— <?php esc_html_e( 'Each product can have its own description', 'naxolabs-order-bump' ); ?></span>
                </div>
                <div class="naxoorbu-card-body">

                    <!-- Product Search -->
                    <div class="naxoorbu-add-product-row">
                        <input type="text" id="naxoorbu-product-search" class="naxoorbu-input"
                               placeholder="🔍 <?php esc_attr_e( 'Search & add product...', 'naxolabs-order-bump' ); ?>"
                               autocomplete="off">
                        <div id="naxoorbu-product-results" class="naxoorbu-product-dropdown"></div>
                    </div>

                    <!-- No products message -->
                    <div id="naxoorbu-no-products-msg" class="naxoorbu-no-products" <?php echo ! empty( $saved_products ) ? 'style="display:none"' : ''; ?>>
                        <?php esc_html_e( 'No products added yet. Search above to add.', 'naxolabs-order-bump' ); ?>
                    </div>

                    <!-- Products list -->
                    <div id="naxoorbu-products-list">
                        <?php foreach ( $saved_products as $i => $p ) :
                            $field_prefix = 'naxoorbu_settings[products][' . $i . ']';
                            include NAXOORBU_PATH . 'admin/views/product-row.php';
                        endforeach; ?>
                    </div>

                    <!-- Behaviour -->
                    <div style="margin-top:24px;border-top:1px solid #F0EEFF;padding-top:18px;">
                        <label class="naxoorbu-label" style="margin-bottom:10px;display:block;"><?php esc_html_e( 'Behaviour', 'naxolabs-order-bump' ); ?></label>
                        <label class="naxoorbu-radio-label">
                            <input type="radio" name="naxoorbu_settings[behaviour]" value="add" <?php checked( $meta['behaviour'] ?? 'add', 'add' ); ?>>
                            <?php esc_html_e( 'Add Order Bumps to Cart Items', 'naxolabs-order-bump' ); ?>
                        </label>
                        <label class="naxoorbu-radio-label">
                            <input type="radio" name="naxoorbu_settings[behaviour]" value="replace" <?php checked( $meta['behaviour'] ?? 'add', 'replace' ); ?>>
                            <?php esc_html_e( 'Replace Cart Items with Order Bump', 'naxolabs-order-bump' ); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ TAB: RULES ═══ -->
        <div class="naxoorbu-tab-content" id="tab-rules" style="<?php echo $tab !== 'rules' ? 'display:none' : ''; ?>">
            <div class="naxoorbu-card">
                <div class="naxoorbu-card-header">🎯 <?php esc_html_e( 'Display Rules — When to show?', 'naxolabs-order-bump' ); ?></div>
                <div class="naxoorbu-card-body">
                    <div class="naxoorbu-rule-row">
                        <span class="naxoorbu-rule-label"><?php esc_html_e( 'For', 'naxolabs-order-bump' ); ?></span>
                        <select name="naxoorbu_settings[trigger_type]" class="naxoorbu-select naxoorbu-rule-select" id="naxoorbu-trigger-type">
                            <option value="all" <?php selected( $meta['trigger_type'], 'all' ); ?>><?php esc_html_e( 'No Rules (Always Show)', 'naxolabs-order-bump' ); ?></option>
                            <option value="specific_product" <?php selected( $meta['trigger_type'], 'specific_product' ); ?>><?php esc_html_e( 'Cart Item(s)', 'naxolabs-order-bump' ); ?></option>
                            <option value="category" <?php selected( $meta['trigger_type'], 'category' ); ?>><?php esc_html_e( 'Cart Category(s)', 'naxolabs-order-bump' ); ?></option>
                            <option value="minimum_order" <?php selected( $meta['trigger_type'], 'minimum_order' ); ?>><?php esc_html_e( 'Cart Total', 'naxolabs-order-bump' ); ?></option>
                        </select>

                        <div class="naxoorbu-rule-val" id="rule-specific_product" style="<?php echo $meta['trigger_type'] !== 'specific_product' ? 'display:none' : ''; ?>">
                            <span class="naxoorbu-rule-hint"><?php esc_html_e( 'matches any of', 'naxolabs-order-bump' ); ?></span>
                            <div style="position:relative;flex:1">
                                <input type="text" id="naxoorbu-trigger-search" class="naxoorbu-input" placeholder="<?php esc_attr_e( 'Search products...', 'naxolabs-order-bump' ); ?>" autocomplete="off">
                                <div id="naxoorbu-trigger-results" class="naxoorbu-product-dropdown"></div>
                            </div>
                            <div id="naxoorbu-trigger-tags" style="width:100%;margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
                                <?php foreach ( ( $meta['trigger_products'] ?? [] ) as $tp_id ) :
                                    $tp = wc_get_product( $tp_id );
                                    if ( ! $tp ) continue;
                                ?>
                                    <span class="naxoorbu-tag" data-id="<?php echo intval( $tp_id ); ?>">
                                        <?php echo esc_html( $tp->get_name() ); ?>
                                        <input type="hidden" name="naxoorbu_settings[trigger_products][]" value="<?php echo intval( $tp_id ); ?>">
                                        <button type="button" class="naxoorbu-tag-remove">×</button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="naxoorbu-rule-val" id="rule-category" style="<?php echo $meta['trigger_type'] !== 'category' ? 'display:none' : ''; ?>">
                            <span class="naxoorbu-rule-hint"><?php esc_html_e( 'matches any of', 'naxolabs-order-bump' ); ?></span>
                            <?php $trig_cats = is_array( $meta['trigger_categories'] ) ? $meta['trigger_categories'] : []; ?>
                            <div class="naxoorbu-cat-select-wrap">
                                <div class="naxoorbu-cat-selected-tags" id="naxoorbu-cat-tags">
                                    <?php foreach ( $all_categories as $cat ) :
                                        if ( in_array( $cat->term_id, $trig_cats ) ) : ?>
                                            <span class="naxoorbu-cat-tag" data-id="<?php echo intval( $cat->term_id ); ?>">
                                                <?php echo esc_html( $cat->name ); ?>
                                                <button type="button" class="naxoorbu-cat-tag-remove">&times;</button>
                                            </span>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                                <div class="naxoorbu-cat-dropdown-toggle" id="naxoorbu-cat-toggle">
                                    <span class="naxoorbu-cat-placeholder"><?php esc_html_e( 'Select categories...', 'naxolabs-order-bump' ); ?></span>
                                    <span class="naxoorbu-cat-arrow">&#9662;</span>
                                </div>
                                <div class="naxoorbu-cat-dropdown" id="naxoorbu-cat-dropdown" style="display:none;">
                                    <input type="text" class="naxoorbu-cat-search" id="naxoorbu-cat-search" placeholder="<?php esc_attr_e( 'Search...', 'naxolabs-order-bump' ); ?>">
                                    <div class="naxoorbu-cat-list">
                                        <?php foreach ( $all_categories as $cat ) :
                                            $checked = in_array( $cat->term_id, $trig_cats ) ? 'checked' : '';
                                        ?>
                                        <label class="naxoorbu-cat-item">
                                            <input type="checkbox" name="naxoorbu_settings[trigger_categories][]"
                                                   value="<?php echo intval( $cat->term_id ); ?>" <?php echo esc_attr( $checked ); ?>>
                                            <span class="naxoorbu-cat-check"></span>
                                            <?php echo esc_html( $cat->name ); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="naxoorbu-rule-val" id="rule-minimum_order" style="<?php echo $meta['trigger_type'] !== 'minimum_order' ? 'display:none' : ''; ?>">
                            <span class="naxoorbu-rule-hint"><?php esc_html_e( 'is greater than ', 'naxolabs-order-bump' ); ?></span>
                            <input type="number" name="naxoorbu_settings[minimum_order_amount]"
                                   value="<?php echo esc_attr( $meta['minimum_order_amount'] ); ?>"
                                   class="naxoorbu-input" placeholder="500" style="width:140px">
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>
