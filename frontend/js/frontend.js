jQuery(function($) {

    var cartState = {};
    var pending   = {};
    var isBlockCheckout = (typeof naxoorbuFrontend !== 'undefined' && naxoorbuFrontend.isBlockCheckout);

    /* ═══════════════════════════════════════════════
       BLOCK CHECKOUT: Inject bumps via MutationObserver
       (bumps are rendered hidden in wp_footer by PHP)
    ═══════════════════════════════════════════════ */
    if (isBlockCheckout) {
        var $hiddenBumps = $('#naxoorbu-block-checkout-bumps');
        if ($hiddenBumps.length && $hiddenBumps.children().length) {
            var injected = false;

            function injectBumps() {
                if (injected) return;

                var $target =
                    $('.wc-block-checkout__actions').first() ||
                    $('.wp-block-woocommerce-checkout-actions-block').first() ||
                    $('.wc-block-components-checkout-place-order-button').first().parent();

                if (!$target || !$target.length) {
                    $target = $('.wc-block-checkout__main .wc-block-components-checkout-step').last();
                }
                if (!$target || !$target.length) {
                    $target = $('.wc-block-checkout__main').first();
                }

                if ($target && $target.length) {
                    injected = true;

                    var $bumps = $hiddenBumps.children().clone(true);
                    $bumps.each(function() { $(this).css('display', ''); });

                    var $wrapper = $('<div>', {
                        'class': 'naxoorbu-block-bumps-container',
                        css: { margin: '16px 0', padding: 0 }
                    });
                    $wrapper.append($bumps);

                    var $actions = $('.wc-block-checkout__actions, .wp-block-woocommerce-checkout-actions-block').first();
                    if ($actions.length) {
                        $actions.before($wrapper);
                    } else {
                        $target.after($wrapper);
                    }

                    $hiddenBumps.remove();

                    $wrapper.find('.naxoorbu-bump-wrap').each(function() {
                        var pid = parseInt($(this).data('product-id'), 10);
                        var checked = $(this).find('.naxoorbu-bump-checkbox').is(':checked');
                        cartState[pid] = checked;
                        if (checked) $(this).addClass('naxoorbu-bump-is-added');
                    });

                    return true;
                }
                return false;
            }

            if (!injectBumps()) {
                var observer = new MutationObserver(function() {
                    if (injectBumps()) {
                        observer.disconnect();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                setTimeout(function() { observer.disconnect(); }, 15000);
            }
        }
    }

    /* ═══════════════════════════════════════════════
       CLASSIC CHECKOUT: Read initial state
    ═══════════════════════════════════════════════ */
    if (!isBlockCheckout) {
        $('.naxoorbu-bump-wrap').each(function() {
            var pid     = parseInt($(this).data('product-id'), 10);
            var checked = $(this).find('.naxoorbu-bump-checkbox').is(':checked');
            cartState[pid] = checked;
            if (checked) $(this).addClass('naxoorbu-bump-is-added');
        });
    }

    /* ═══════════════════════════════════════════════
       CHECKBOX CHANGE — works for BOTH checkout types
       - Disables checkbox during AJAX (prevents double-click)
       - Shows error state on failure
    ═══════════════════════════════════════════════ */
    $(document).on('change', '.naxoorbu-bump-checkbox', function() {
        var $cb    = $(this);
        var $wrap  = $cb.closest('.naxoorbu-bump-wrap');
        var pid    = parseInt($wrap.data('product-id'), 10);
        var qty    = parseInt($wrap.data('qty'), 10) || 1;
        var adding = $cb.is(':checked');

        // Abort previous request for this product
        if (pending[pid]) {
            pending[pid].abort();
            delete pending[pid];
        }

        // Disable checkbox during AJAX — prevent rapid double-clicks
        $cb.prop('disabled', true);
        $wrap.addClass('naxoorbu-bump-loading-state');

        // Instant visual feedback
        cartState[pid] = adding;
        $wrap.toggleClass('naxoorbu-bump-is-added', adding);

        // Clear any previous error
        $wrap.find('.naxoorbu-bump-error').remove();

        // AJAX
        pending[pid] = $.ajax({
            url:    naxoorbuFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action:     adding ? 'naxoorbu_add_to_cart' : 'naxoorbu_remove_from_cart',
                product_id: pid,
                bump_id:    $wrap.data('bump-id') || 0,
                qty:        qty,
                nonce:      naxoorbuFrontend.nonce
            },
            success: function(res) {
                delete pending[pid];
                $cb.prop('disabled', false);
                $wrap.removeClass('naxoorbu-bump-loading-state');

                if (res.success) {
                    if (isBlockCheckout) {
                        refreshBlockCart();
                    } else {
                        $('body').trigger('update_checkout');
                    }
                } else {
                    revertState($cb, $wrap, pid, adding);
                    showBumpError($wrap, res.data && res.data.message ? res.data.message : 'Something went wrong.');
                }
            },
            error: function(xhr) {
                if (xhr.statusText === 'abort') return;
                delete pending[pid];
                $cb.prop('disabled', false);
                $wrap.removeClass('naxoorbu-bump-loading-state');
                revertState($cb, $wrap, pid, adding);
                showBumpError($wrap, 'Connection error. Please try again.');
            }
        });
    });

    /**
     * Revert checkbox and visual state on failure.
     */
    function revertState($cb, $wrap, pid, adding) {
        cartState[pid] = !adding;
        $wrap.toggleClass('naxoorbu-bump-is-added', !adding);
        $cb.prop('checked', !adding);
    }

    /**
     * Show a brief error message inside the bump wrap.
     */
    function showBumpError($wrap, message) {
        var $err = $('<div class="naxoorbu-bump-error">' + message + '</div>');
        $wrap.append($err);
        setTimeout(function() {
            $err.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }

    /* ═══════════════════════════════════════════════
       BLOCK CHECKOUT: Refresh cart totals
    ═══════════════════════════════════════════════ */
    function refreshBlockCart() {
        try {
            if (wp && wp.data && wp.data.dispatch) {
                var cartStore = wp.data.dispatch('wc/store/cart');
                if (cartStore && cartStore.invalidateResolutionForStoreCart) {
                    cartStore.invalidateResolutionForStoreCart();
                    return;
                }
            }
        } catch(e) {}

        try {
            document.body.dispatchEvent(new Event('wc-blocks_added_to_cart'));
        } catch(e) {}

        $(document.body).trigger('wc_fragment_refresh');
    }

    /* ═══════════════════════════════════════════════
       CLASSIC CHECKOUT: Restore state after WC refresh
    ═══════════════════════════════════════════════ */
    $(document.body).on('updated_checkout', function() {
        $('.naxoorbu-bump-wrap').each(function() {
            var pid   = parseInt($(this).data('product-id'), 10);
            var state = cartState[pid] || false;
            $(this).toggleClass('naxoorbu-bump-is-added', state);
            $(this).find('.naxoorbu-bump-checkbox').prop('checked', state);
        });
    });

});
