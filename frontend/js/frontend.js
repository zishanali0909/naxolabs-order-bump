jQuery(function ($) {

    var cartState = {};
    var pending = {};

    /* ═══════════════════════════════════════════════
       Read initial state (classic checkout; the block checkout uses blocks-checkout.js)
    ═══════════════════════════════════════════════ */
    $('.naxoorbu-bump-wrap').each(function () {
        var pid = parseInt($(this).data('product-id'), 10);
        var checked = $(this).find('.naxoorbu-bump-checkbox').is(':checked');
        cartState[pid] = checked;
        if (checked) $(this).addClass('naxoorbu-bump-is-added');
    });

    /* ═══════════════════════════════════════════════
       CHECKBOX CHANGE
       - Disables checkbox during AJAX (prevents double-click)
       - Shows error state on failure
    ═══════════════════════════════════════════════ */
    $(document).on('change', '.naxoorbu-bump-checkbox', function () {
        var $cb = $(this);
        var $wrap = $cb.closest('.naxoorbu-bump-wrap');
        var pid = parseInt($wrap.data('product-id'), 10);
        var qty = parseInt($wrap.data('qty'), 10) || 1;
        var adding = $cb.is(':checked');

        // Bumps inside a form marked data-naxoorbu-refresh="form" (e.g. Pro on the product page)
        // are not added on tick: they go with that form as naxoorbu_form_bumps[] = "bumpId:productId".
        if ($wrap.closest('[data-naxoorbu-refresh]').data('naxoorbu-refresh') === 'form') {
            var value = ($wrap.data('bump-id') || 0) + ':' + pid;
            var $form = $wrap.closest('form');
            $wrap.toggleClass('naxoorbu-bump-is-added', adding);
            $form.find('input[name="naxoorbu_form_bumps[]"]').filter(function () { return this.value === value; }).remove();
            if (adding) {
                $('<input type="hidden" name="naxoorbu_form_bumps[]">').val(value).appendTo($form);
            }
            return;
        }

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
            url: naxoorbuFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action: adding ? 'naxoorbu_add_to_cart' : 'naxoorbu_remove_from_cart',
                product_id: pid,
                bump_id: $wrap.data('bump-id') || 0,
                qty: qty,
                nonce: naxoorbuFrontend.nonce
            },
            success: function (res) {
                delete pending[pid];
                $cb.prop('disabled', false);
                $wrap.removeClass('naxoorbu-bump-loading-state');

                if (res.success) {
                    refreshPage($wrap, adding);
                } else {
                    revertState($cb, $wrap, pid, adding);
                    showBumpError($wrap, res.data && res.data.message ? res.data.message : (naxoorbuFrontend.i18n.error || 'Something went wrong.'));
                }
            },
            error: function (xhr) {
                if (xhr.statusText === 'abort') return;
                delete pending[pid];
                $cb.prop('disabled', false);
                $wrap.removeClass('naxoorbu-bump-loading-state');
                revertState($cb, $wrap, pid, adding);
                showBumpError($wrap, naxoorbuFrontend.i18n.connectionError || 'Connection error. Please try again.');
            }
        });
    });

    /**
     * Refresh what shows the cart, with WooCommerce's own events, by the bump's container
     * (data-naxoorbu-refresh): the checkout (default), the cart page ("cart"), or the classic
     * mini-cart ("fragments"). Other containers handle it on the naxoorbu_bump_updated event.
     */
    function refreshPage($wrap, adding) {
        var mode = $wrap.closest('[data-naxoorbu-refresh]').data('naxoorbu-refresh') || 'checkout';
        if (mode === 'checkout') {
            $('body').trigger('update_checkout');
        } else if (mode === 'cart') {
            $(document.body).trigger('wc_update_cart');
        } else if (mode === 'fragments') {
            $(document.body).trigger('wc_fragment_refresh');
        }
        $(document.body).trigger('naxoorbu_bump_updated', [adding, $wrap]);

        // Same event and detail as blocks-checkout.js, for other scripts (e.g. Pro's GA4 / Meta Pixel events).
        var places = { checkout: 'checkout', cart: 'cart', fragments: 'mini_cart', 'mini-cart': 'mini_cart' };
        document.body.dispatchEvent(new CustomEvent('naxoorbu_bump_toggled', {
            bubbles: true,
            detail: {
                added: adding,
                bumpId: parseInt($wrap.data('bump-id'), 10) || 0,
                bumpName: String($wrap.data('bump-name') || ''),
                productId: parseInt($wrap.data('product-id'), 10) || 0,
                name: String($wrap.data('name') || ''),
                price: parseFloat($wrap.data('price')) || 0,
                quantity: parseInt($wrap.data('qty'), 10) || 1,
                placement: places[mode] || mode
            }
        }));
    }

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
        var $err = $('<div class="naxoorbu-bump-error">').text(message);
        $wrap.append($err);
        setTimeout(function () {
            $err.fadeOut(300, function () { $(this).remove(); });
        }, 3000);
    }

    /* ═══════════════════════════════════════════════
       Restore state after WC refresh
    ═══════════════════════════════════════════════ */
    $(document.body).on('updated_checkout', function () {
        $('.naxoorbu-bump-wrap').each(function () {
            var pid = parseInt($(this).data('product-id'), 10);
            var state = cartState[pid] || false;
            $(this).toggleClass('naxoorbu-bump-is-added', state);
            $(this).find('.naxoorbu-bump-checkbox').prop('checked', state);
        });
    });

});
