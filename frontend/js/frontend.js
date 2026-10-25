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
                    $('body').trigger('update_checkout');
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
