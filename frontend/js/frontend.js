jQuery(function($) {

    var cartState = {};
    var pending   = {};
    var isBlockCheckout = (typeof obpFrontend !== 'undefined' && obpFrontend.isBlockCheckout);

    /* ═══════════════════════════════════════════════
       BLOCK CHECKOUT: Inject bumps via MutationObserver
       (bumps are rendered hidden in wp_footer by PHP)
    ═══════════════════════════════════════════════ */
    if (isBlockCheckout) {
        var $hiddenBumps = $('#obp-block-checkout-bumps');
        if ($hiddenBumps.length && $hiddenBumps.children().length) {
            var injected = false;

            function injectBumps() {
                if (injected) return;

                // Try multiple selectors for different WC Blocks versions
                var $target =
                    $('.wc-block-checkout__actions').first() ||
                    $('.wp-block-woocommerce-checkout-actions-block').first() ||
                    $('.wc-block-components-checkout-place-order-button').first().parent();

                // Fallback: try the main checkout form
                if (!$target || !$target.length) {
                    $target = $('.wc-block-checkout__main .wc-block-components-checkout-step').last();
                }

                // Another fallback: any element inside checkout main
                if (!$target || !$target.length) {
                    $target = $('.wc-block-checkout__main').first();
                }

                if ($target && $target.length) {
                    injected = true;

                    // Clone bumps, make visible, and insert before payment actions
                    var $bumps = $hiddenBumps.children().clone(true);
                    $bumps.each(function() { $(this).css('display', ''); });

                    var $wrapper = $('<div>', {
                        'class': 'obp-block-bumps-container',
                        css: { margin: '16px 0', padding: 0 }
                    });
                    $wrapper.append($bumps);

                    // Insert before the actions/payment section
                    var $actions = $('.wc-block-checkout__actions, .wp-block-woocommerce-checkout-actions-block').first();
                    if ($actions.length) {
                        $actions.before($wrapper);
                    } else {
                        $target.after($wrapper);
                    }

                    $hiddenBumps.remove();

                    // Initialize cart state for injected bumps
                    $wrapper.find('.obp-bump-wrap').each(function() {
                        var pid = parseInt($(this).data('product-id'), 10);
                        var checked = $(this).find('.obp-bump-checkbox').is(':checked');
                        cartState[pid] = checked;
                        if (checked) $(this).addClass('obp-bump-is-added');
                    });

                    return true;
                }
                return false;
            }

            // Try immediately
            if (!injectBumps()) {
                // Use MutationObserver to wait for block checkout to render
                var observer = new MutationObserver(function(mutations) {
                    if (injectBumps()) {
                        observer.disconnect();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });

                // Safety timeout — stop observing after 15 seconds
                setTimeout(function() { observer.disconnect(); }, 15000);
            }
        }
    }

    /* ═══════════════════════════════════════════════
       CLASSIC CHECKOUT: Read initial state
    ═══════════════════════════════════════════════ */
    if (!isBlockCheckout) {
        $('.obp-bump-wrap').each(function() {
            var pid     = parseInt($(this).data('product-id'), 10);
            var checked = $(this).find('.obp-bump-checkbox').is(':checked');
            cartState[pid] = checked;
            if (checked) $(this).addClass('obp-bump-is-added');
        });
    }

    /* ═══════════════════════════════════════════════
       CHECKBOX CHANGE — works for BOTH checkout types
    ═══════════════════════════════════════════════ */
    $(document).on('change', '.obp-bump-checkbox', function() {
        var $cb    = $(this);
        var $wrap  = $cb.closest('.obp-bump-wrap');
        var pid    = parseInt($wrap.data('product-id'), 10);
        var qty    = parseInt($wrap.data('qty'), 10) || 1;
        var adding = $cb.is(':checked');

        // Abort previous request for this product
        if (pending[pid]) {
            pending[pid].abort();
            delete pending[pid];
        }

        // Instant visual feedback
        cartState[pid] = adding;
        $wrap.toggleClass('obp-bump-is-added', adding);

        // AJAX
        pending[pid] = $.ajax({
            url:    obpFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action:     adding ? 'obp_add_to_cart' : 'obp_remove_from_cart',
                product_id: pid,
                qty:        qty,
                nonce:      obpFrontend.nonce
            },
            success: function(res) {
                delete pending[pid];
                if (res.success) {
                    if (isBlockCheckout) {
                        // Refresh WooCommerce Blocks cart store
                        refreshBlockCart();
                    } else {
                        // Classic checkout: trigger WC refresh
                        $('body').trigger('update_checkout');
                    }
                } else {
                    // Revert on failure
                    revertState($cb, $wrap, pid, adding);
                }
            },
            error: function(xhr) {
                if (xhr.statusText === 'abort') return;
                delete pending[pid];
                revertState($cb, $wrap, pid, adding);
            }
        });
    });

    function revertState($cb, $wrap, pid, adding) {
        cartState[pid] = !adding;
        $wrap.toggleClass('obp-bump-is-added', !adding);
        $cb.prop('checked', !adding);
    }

    /* ═══════════════════════════════════════════════
       BLOCK CHECKOUT: Refresh cart totals
    ═══════════════════════════════════════════════ */
    function refreshBlockCart() {
        // Method 1: WordPress data store (most reliable)
        try {
            if (wp && wp.data && wp.data.dispatch) {
                var cartStore = wp.data.dispatch('wc/store/cart');
                if (cartStore && cartStore.invalidateResolutionForStoreCart) {
                    cartStore.invalidateResolutionForStoreCart();
                    return;
                }
            }
        } catch(e) {}

        // Method 2: Dispatch a custom event that WC Blocks listens to
        try {
            document.body.dispatchEvent(new Event('wc-blocks_added_to_cart'));
        } catch(e) {}

        // Method 3: jQuery fragment refresh (older WC)
        $(document.body).trigger('wc_fragment_refresh');
    }

    /* ═══════════════════════════════════════════════
       CLASSIC CHECKOUT: Restore state after WC refresh
    ═══════════════════════════════════════════════ */
    $(document.body).on('updated_checkout', function() {
        $('.obp-bump-wrap').each(function() {
            var pid   = parseInt($(this).data('product-id'), 10);
            var state = cartState[pid] || false;
            $(this).toggleClass('obp-bump-is-added', state);
            $(this).find('.obp-bump-checkbox').prop('checked', state);
        });
    });

});
