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
    var registerPlugin = wp.plugins.registerPlugin;
    var useSelect = wp.data.useSelect;
    var CART_STORE = (wc.wcBlocksData && wc.wcBlocksData.CART_STORE_KEY) || 'wc/store/cart';

    // Get slot fill component — try multiple names for WC version compat
    var blocksCheckout = wc.blocksCheckout || {};
    var OrderMetaSlot  = blocksCheckout.ExperimentalOrderMeta || blocksCheckout.OrderMeta || null;
    var extensionCartUpdate = blocksCheckout.extensionCartUpdate || null;

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
    var storeApiNamespace = settingsData.storeApiNamespace || '';

    // The bump checkbox already shows the item was removed, so skip WooCommerce's
    // '"X" was removed from your cart.' snackbar for bump items (incl. BOGO bonus items).
    if (storeApiNamespace && wp.hooks) {
        wp.hooks.addFilter(
            'woocommerce_show_cart_item_removed_notice',
            'naxolabs-order-bump/hide-bump-removed-notice',
            function (show, cartItem) {
                var ext = cartItem && cartItem.extensions ? cartItem.extensions[storeApiNamespace] : null;
                return (ext && ext.bump_id) ? false : show;
            }
        );
    }

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
       Helper: Add/remove a bump product.
       Uses the Store API cart/extensions endpoint via
       extensionCartUpdate(), which also updates the
       checkout's cart store (items + totals) itself.
       Older WooCommerce without extensionCartUpdate falls
       back to admin-ajax + store invalidation.
    ═══════════════════════════════════════════════ */
    function updateBumpInCart(adding, bump, product) {
        if (extensionCartUpdate && storeApiNamespace) {
            return extensionCartUpdate({
                namespace: storeApiNamespace,
                data: {
                    action: adding ? 'add' : 'remove',
                    product_id: product.id,
                    bump_id: bump.id,
                    qty: product.qty || 1
                }
            });
        }

        var formData = new FormData();
        formData.append('action', adding ? 'naxoorbu_add_to_cart' : 'naxoorbu_remove_from_cart');
        formData.append('product_id', product.id);
        formData.append('bump_id', bump.id);
        formData.append('qty', product.qty || 1);
        formData.append('nonce', nonce);

        return fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.data && res.data.message ? res.data.message : 'Request failed');
                var dispatch = wp.data.dispatch(CART_STORE);
                if (dispatch && dispatch.invalidateResolutionForStore) {
                    dispatch.invalidateResolutionForStore();
                }
            });
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

        // Keep the checkbox in sync with the live cart (e.g. item already added on an earlier visit).
        // Only items added from this bump count; the same product bought at its normal price does not.
        var inCart = useSelect(function (select) {
            var data = select(CART_STORE).getCartData();
            return !!(data && data.items && data.items.some(function (item) {
                var ext = item.extensions ? item.extensions[storeApiNamespace] : null;
                return item.id === product.id && !!ext && ext.bump_id === bump.id;
            }));
        }, [product.id, bump.id]);

        useEffect(function () {
            if (!loading) setChecked(inCart);
        }, [inCart]);

        var skin     = bump.skin || 'skin1';
        // Base layout the skin is built on (Pro skins add CSS on top), same as the classic checkout.
        var layout   = bump.layout || (skin === 'skin2' ? 'skin2' : 'skin1');
        var bgColor  = bump.bg_color || '#FFFDE7';
        var txtColor = bump.text_color || '#155724';
        var badgeText = product.badge_text || bump.badge_text;
        var showBadge = (product.show_badge || bump.show_badge) && badgeText;
        var showOrig  = bump.show_original_price;
        var showImg   = bump.show_product_image;
        var imgPos    = bump.image_position || 'left';
        var imgWidth  = bump.image_width || 96;

        // Product-level headline (e.g. A/B Variant B) wins over the bump-level one
        var headline = replaceProductName(product.headline || bump.headline || '', product.name);

        // Track impression on mount
        useEffect(function () {
            var formData = new FormData();
            formData.append('action', 'naxoorbu_track_impression');
            formData.append('bump_id', bump.id);
            formData.append('product_id', product.id);
            formData.append('nonce', nonce);
            fetch(ajaxUrl, { method: 'POST', body: formData }).catch(function(){});
        }, []);

        // Toggle handler (loading flag blocks double-clicks while a request is in flight)
        function handleToggle() {
            if (loading) return;
            setLoading(true);

            var newChecked = !checked;
            setChecked(newChecked); // Optimistic UI

            updateBumpInCart(newChecked, bump, product)
                .then(function () {
                    setLoading(false);
                })
                .catch(function () {
                    // Revert on failure
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
            ? el('div', { className: 'naxoorbu-bump-badge' }, badgeText)
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

        // Skin colors as CSS variables on the wrapper, like the classic checkout (used by Pro skins).
        var skinVars = { '--naxoorbu-header-color': bgColor, '--naxoorbu-header-text': txtColor };

        // ── SKIN 2 layout ──
        if (layout === 'skin2') {
            return el('div', {
                className: 'naxoorbu-bump-wrap naxoorbu-skin-' + skin + (checked ? ' naxoorbu-bump-is-added' : '') + (showBadge ? ' naxoorbu-has-badge' : ''),
                style: skinVars,
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
            className: 'naxoorbu-bump-wrap naxoorbu-skin-' + skin + (checked ? ' naxoorbu-bump-is-added' : '') + (showBadge ? ' naxoorbu-has-badge' : ''),
            style: skinVars,
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
    function OrderBumpBlockComponent() {
        var elements = [];

        // Bumps and products the shopper should see, decided on the server (trigger, schedule,
        // display rules, products already in the cart) and re-sent by the Store API with every
        // cart/address update. Returned as a string so the selector result stays referentially stable.
        var visibleKey = useSelect(function (select) {
            var data = select(CART_STORE).getCartData();
            var ext  = data && data.extensions ? data.extensions[storeApiNamespace] : null;
            return ext && Array.isArray(ext.visible_products) ? JSON.stringify(ext.visible_products) : null;
        }, []);

        // bump ID => offered product IDs (null = no Store API data).
        var visible = null;
        if (visibleKey !== null) {
            visible = {};
            JSON.parse(visibleKey).forEach(function (entry) {
                visible[entry.bump_id] = entry.product_ids || [];
            });
        }

        bumps.forEach(function (bump) {
            // Without the Store API data (very old WooCommerce), fall back to showing all bumps.
            if (visible !== null && !visible[bump.id]) return;

            bump.products.forEach(function (product, idx) {
                if (visible !== null && visible[bump.id].indexOf(product.id) === -1) return;

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

        // Always render the wrapper, even when empty: frontend.js treats it as the signal
        // that React owns the bumps and the DOM fallback must stay out.
        return el('div', {
            className: 'naxoorbu-blocks-checkout-wrap',
            style: elements.length ? { margin: '16px 0' } : { display: 'none' }
        }, elements);
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
