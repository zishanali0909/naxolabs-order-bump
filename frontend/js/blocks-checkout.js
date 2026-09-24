/**
 * Naxolabs Order Bump — Block Checkout Integration
 *
 * Renders order bumps inside WooCommerce Block-based Checkout
 * using wp.element (React) and wc.blocksCheckout slot fills.
 * No build step required — uses createElement directly.
 *
 * @package WPOrderBump
 */
(function () {
    'use strict';

    var el        = wp.element.createElement;
    var useState  = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef    = wp.element.useRef;
    var registerPlugin = wp.plugins.registerPlugin;

    // Get slot fill component — try multiple names for WC version compat
    var blocksCheckout = wc.blocksCheckout || {};
    var OrderMetaSlot  = blocksCheckout.ExperimentalOrderMeta || blocksCheckout.OrderMeta || null;

    // Get bump data passed from PHP via IntegrationInterface::get_script_data()
    var settingsData = {};
    try {
        settingsData = wc.wcSettings.getSetting('naxolabs-order-bump_data', {});
    } catch (e) {
        // Silent fail for missing settings data
    }

    var bumps   = settingsData.bumps || [];
    var ajaxUrl = settingsData.ajaxUrl || '';
    var nonce   = settingsData.nonce || '';

    if (!bumps.length || !OrderMetaSlot) {
        // No bumps or no slot available — exit silently
        return;
    }

    /* ═══════════════════════════════════════════════
       Helper: Replace {{product_name}} in headline
    ═══════════════════════════════════════════════ */
    function replaceProductName(headline, name) {
        return headline.replace(/\{\{product_name\}\}/g, name);
    }

    /* ═══════════════════════════════════════════════
       Helper: Check trigger rules against current cart
       (Note: in block checkout, cart data is available
        via the slot fill props. For simplicity, we show
        all bumps with trigger_type='all' always, and
        do server-side filtering for others.)
    ═══════════════════════════════════════════════ */
    function shouldShowBump(bump, cart) {
        var type = bump.trigger_type || 'all';
        if (type === 'all') return true;

        if (!cart || !cart.cartItems) return true; // Can't filter, show anyway

        var cartProductIds = cart.cartItems.map(function (item) { return item.id; });
        var cartTotal      = parseFloat(cart.cartTotals && cart.cartTotals.total_price ? cart.cartTotals.total_price : 0) / 100;

        if (type === 'specific_product') {
            var triggerProds = bump.trigger_products || [];
            return triggerProds.some(function (id) { return cartProductIds.indexOf(id) !== -1; });
        }
        if (type === 'minimum_order') {
            return cartTotal >= (bump.minimum_order_amount || 0);
        }
        // For category triggers, we'd need category data — show bump and let server filter
        return true;
    }

    /* ═══════════════════════════════════════════════
       SVG Arrow Icon Component
    ═══════════════════════════════════════════════ */
    function ArrowIcon() {
        return el('svg', {
            width: 12, height: 12, viewBox: '0 0 12 12',
            fill: 'none', xmlns: 'http://www.w3.org/2000/svg',
            style: { display: 'block', overflow: 'visible' }
        },
            el('path', {
                d: 'M1 6H11M11 6L7 2M11 6L7 10',
                stroke: '#E15334', strokeWidth: 2,
                strokeLinecap: 'round', strokeLinejoin: 'round'
            })
        );
    }

    /* ═══════════════════════════════════════════════
       Single Product Bump Component
    ═══════════════════════════════════════════════ */
    function SingleProductBump(props) {
        var bump    = props.bump;
        var product = props.product;
        var idx     = props.idx;

        var stateArr = useState(false);
        var checked  = stateArr[0];
        var setChecked = stateArr[1];

        var loadArr = useState(false);
        var loading = loadArr[0];
        var setLoading = loadArr[1];

        var abortRef = useRef(null);

        var skin     = bump.skin || 'skin1';
        var bgColor  = bump.bg_color || '#FFFDE7';
        var txtColor = bump.text_color || '#155724';
        var showBadge = bump.show_badge && bump.badge_text;
        var showOrig  = bump.show_original_price;
        var showImg   = bump.show_product_image;
        var imgPos    = bump.image_position || 'left';
        var imgWidth  = bump.image_width || 96;

        var headline = replaceProductName(bump.headline || '', product.name);

        // Toggle handler
        function handleToggle() {
            if (loading) return;
            setLoading(true);

            var newChecked = !checked;
            setChecked(newChecked); // Optimistic UI

            // Abort previous request
            if (abortRef.current) {
                abortRef.current.abort();
            }

            var controller = new AbortController();
            abortRef.current = controller;

            var formData = new FormData();
            formData.append('action', newChecked ? 'naxoorbu_add_to_cart' : 'naxoorbu_remove_from_cart');
            formData.append('product_id', product.id);
            formData.append('qty', product.qty || 1);
            formData.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                signal: controller.signal,
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                setLoading(false);
                if (res.success) {
                    // Refresh the WooCommerce Blocks cart store
                    try {
                        var dispatch = wp.data.dispatch('wc/store/cart');
                        if (dispatch && dispatch.invalidateResolutionForStoreCart) {
                            dispatch.invalidateResolutionForStoreCart();
                        }
                    } catch (e) {
                        // Fallback: trigger jQuery event
                        jQuery(document.body).trigger('wc_fragment_refresh');
                    }
                } else {
                    // Revert on failure
                    setChecked(!newChecked);
                }
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                setLoading(false);
                setChecked(!newChecked);
            });
        }

        // Price display
        var priceEl = el('div', { className: 'naxoorbu-bump-prices', style: { marginLeft: 'auto', whiteSpace: 'nowrap' } },
            (showOrig && product.regular_price > product.final_price)
                ? el('del', { className: 'naxoorbu-price-orig', style: { marginRight: '4px', opacity: 0.6 } }, product.regular_html)
                : null,
            el('span', { className: 'naxoorbu-price-final', style: { fontWeight: 700 } }, product.final_price_html)
        );

        // Badge
        var badgeEl = showBadge
            ? el('div', { className: 'naxoorbu-bump-badge' }, bump.badge_text)
            : null;

        // Image
        var imgEl = (showImg && product.image)
            ? el('img', {
                src: product.image,
                alt: product.name,
                style: {
                    width: imgWidth + 'px', height: imgWidth + 'px',
                    objectFit: 'cover', borderRadius: '6px', flexShrink: 0
                }
            })
            : null;

        // Loading overlay
        var loadingEl = el('div', {
            className: 'naxoorbu-bump-loading',
            style: { display: loading ? 'flex' : 'none' }
        },
            el('div', { className: 'naxoorbu-bump-spinner' }),
            el('span', null, 'Updating order...')
        );

        // ── SKIN 2 layout ──
        if (skin === 'skin2') {
            return el('div', {
                className: 'naxoorbu-bump-wrap naxoorbu-skin-skin2' + (checked ? ' naxoorbu-bump-is-added' : '') + (showBadge ? ' obp-has-badge' : ''),
                'data-product-id': product.id,
                'data-qty': product.qty
            },
                badgeEl,
                // Image + Description row
                (imgEl || product.description) ? el('div', {
                    style: { display: 'flex', gap: '14px', alignItems: 'flex-start', padding: '12px 16px 10px' }
                },
                    imgEl ? el('div', { style: { flexShrink: 0 } }, imgEl) : null,
                    product.description
                        ? el('div', { style: { flex: 1, minWidth: 0 }, className: 'naxoorbu-bump-description', dangerouslySetInnerHTML: { __html: product.description } })
                        : null
                ) : null,
                // CTA row
                el('div', {
                    style: {
                        borderTop: '1.5px dashed #2bbfbf', padding: '10px 14px',
                        display: 'flex', alignItems: 'center', gap: '8px', background: bgColor
                    }
                },
                    el('div', { className: 'naxoorbu-bump-arrow', style: { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '20px', height: '20px', minWidth: '20px', flexShrink: 0 } }, ArrowIcon()),
                    el('label', {
                        style: { display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', flex: 1, margin: 0 },
                        onClick: function (e) { e.preventDefault(); handleToggle(); }
                    },
                        el('input', { type: 'checkbox', className: 'naxoorbu-bump-checkbox', checked: checked, readOnly: true }),
                        el('span', { className: 'naxoorbu-bump-checkmark' }),
                        el('span', { style: { fontSize: '14px', fontWeight: 800, color: txtColor }, dangerouslySetInnerHTML: { __html: headline } })
                    ),
                    priceEl
                ),
                loadingEl
            );
        }

        // ── SKIN 1 layout (default) ──
        return el('div', {
            className: 'naxoorbu-bump-wrap naxoorbu-skin-skin1' + (checked ? ' naxoorbu-bump-is-added' : '') + (showBadge ? ' obp-has-badge' : ''),
            'data-product-id': product.id,
            'data-qty': product.qty
        },
            badgeEl,
            // Headline row
            el('div', {
                className: 'naxoorbu-bump-headline-row',
                style: {
                    display: 'flex', alignItems: 'center', flexWrap: 'nowrap', gap: '8px',
                    padding: (showBadge ? '28px' : '11px') + ' 12px 11px 12px',
                    background: bgColor, color: txtColor
                }
            },
                el('div', { className: 'naxoorbu-bump-arrow', style: { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '20px', height: '20px', minWidth: '20px', flexShrink: 0, alignSelf: 'center' } }, ArrowIcon()),
                el('label', {
                    className: 'naxoorbu-bump-check-label',
                    style: { display: 'inline-flex', alignItems: 'center', gap: '8px', flex: 1, minWidth: 0, cursor: 'pointer', margin: 0, padding: 0, fontWeight: 'normal' },
                    onClick: function (e) { e.preventDefault(); handleToggle(); }
                },
                    el('input', { type: 'checkbox', className: 'naxoorbu-bump-checkbox', checked: checked, readOnly: true }),
                    el('span', { className: 'naxoorbu-bump-checkmark' }),
                    el('span', { className: 'naxoorbu-bump-headline-text', dangerouslySetInnerHTML: { __html: headline } })
                ),
                priceEl
            ),
            // Body (description + image)
            (product.description || imgEl) ? el('div', { className: 'naxoorbu-bump-body' },
                imgEl
                    ? el('div', { style: { display: 'flex', gap: '12px', alignItems: 'flex-start', flexDirection: imgPos === 'right' ? 'row-reverse' : 'row' } },
                        imgEl,
                        product.description ? el('div', { className: 'naxoorbu-bump-description', style: { flex: 1 }, dangerouslySetInnerHTML: { __html: product.description } }) : null
                    )
                    : product.description ? el('div', { className: 'naxoorbu-bump-description', dangerouslySetInnerHTML: { __html: product.description } }) : null
            ) : null,
            loadingEl
        );
    }

    /* ═══════════════════════════════════════════════
       Order Bump Container — renders all bumps
    ═══════════════════════════════════════════════ */
    function OrderBumpBlockComponent(props) {
        var cart = props.cart || {};
        var elements = [];

        bumps.forEach(function (bump) {
            if (!shouldShowBump(bump, cart)) return;
            // Position filtering: default to before_payment
            var bumpPosition = bump.position || 'before_payment';

            bump.products.forEach(function (product, idx) {
                elements.push(
                    el(SingleProductBump, {
                        key: bump.id + '-' + idx,
                        bump: bump,
                        product: product,
                        idx: idx
                    })
                );
            });
        });

        if (elements.length === 0) return null;

        return el('div', { className: 'naxoorbu-blocks-checkout-wrap', style: { margin: '16px 0' } }, elements);
    }

    /* ═══════════════════════════════════════════════
       Register as WooCommerce Checkout Plugin
    ═══════════════════════════════════════════════ */
    registerPlugin('naxolabs-order-bump-blocks', {
        render: function () {
            return el(OrderMetaSlot, null,
                el(OrderBumpBlockComponent)
            );
        },
        scope: 'woocommerce-checkout',
    });

})();
