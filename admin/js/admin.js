jQuery(function($) {

    /* ═══════════════════════════════════════
       TABS
    ═══════════════════════════════════════ */
    $('.naxoorbu-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.naxoorbu-tab').removeClass('active');
        $(this).addClass('active');
        $('.naxoorbu-tab-content').hide();
        $('#tab-' + tab).show();
        $('input[name="active_tab"]').val(tab);
    });

    // On page load — show correct tab from URL
    (function() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || 'design';
        $('.naxoorbu-tab').removeClass('active');
        $('.naxoorbu-tab[data-tab="' + tab + '"]').addClass('active');
        $('.naxoorbu-tab-content').hide();
        $('#tab-' + tab).show();
        $('input[name="active_tab"]').val(tab);
    })();

    /* ═══════════════════════════════════════
       STATUS TOGGLE (header)
    ═══════════════════════════════════════ */
    $('#naxoorbu-bump-status-top').on('change', function() {
        var isActive = $(this).is(':checked');
        $('#naxoorbu-status-field').val(isActive ? 'publish' : 'draft');
        $('#naxoorbu-status-label').text(isActive ? (naxoorbuAdmin.i18n.active || 'Active') : (naxoorbuAdmin.i18n.inactive || 'Inactive'));
    });

    /* ═══════════════════════════════════════
       BADGE TOGGLE — show/hide badge text
    ═══════════════════════════════════════ */
    $('#naxoorbu-show-badge-toggle').on('change', function() {
        var isOn = $(this).is(':checked');
        if (isOn) {
            $('#naxoorbu-badge-text-wrap').slideDown(200);
            // Show badge in preview
            var text = $('input[name="naxoorbu_settings[badge_text]"]').val() || 'Special Offer';
            $('#naxoorbu-preview-badge').text(text).fadeIn(200);
            $('#naxoorbu-preview-headline').css('padding-top', '18px');
        } else {
            $('#naxoorbu-badge-text-wrap').slideUp(200);
            // Hide badge in preview
            $('#naxoorbu-preview-badge').fadeOut(200);
            $('#naxoorbu-preview-headline').css('padding-top', '');
        }
    });

    // Badge text — live update in preview
    $('input[name="naxoorbu_settings[badge_text]"]').on('input', function() {
        var text = $(this).val() || 'Special Offer';
        $('#naxoorbu-preview-badge').text(text);
    });

    /* ═══════════════════════════════════════
       HEADLINE — live update in preview
    ═══════════════════════════════════════ */
    $('input[name="naxoorbu_settings[headline]"]').on('input', function() {
        var text = $(this).val() || 'Yes! Add {{product_name}} to my order';
        $('#naxoorbu-preview-headline-text').html(text);
        // Also update skin2 CTA row
        $('#naxoorbu-preview-cta-row span').last().html(text);
    });

    /* ═══════════════════════════════════════
       IMAGE PREVIEW HELPER
       Uses CSS class for bulletproof visibility
       Tracks active product thumb for switching
    ═══════════════════════════════════════ */
    // Track the currently active product's thumbnail
    var activeProductThumb = naxoorbuAdmin.firstProductImg || '';
    // Initialize from the active switch button if available
    var $initBtn = $('.naxoorbu-desc-switch-btn.naxoorbu-switch-active');
    if ($initBtn.length && $initBtn.data('thumb')) {
        activeProductThumb = $initBtn.data('thumb');
    }

    function getPreviewImgSrc() {
        var type = $('#naxoorbu-img-type-val').val() || naxoorbuAdmin.imageType || 'product';
        if (type === 'custom') {
            return $('input[name="naxoorbu_settings[image_custom_url]"]').val() || naxoorbuAdmin.customImageUrl || '';
        }
        // Use active product's thumb
        if (activeProductThumb) return activeProductThumb;
        // Fallback: existing img src
        var existingSrc = $('#naxoorbu-preview-product-img').attr('src');
        if (existingSrc) return existingSrc;
        // Fallback: product row thumb
        var $thumb = $('.naxoorbu-product-row .naxoorbu-product-thumb').not('.naxoorbu-no-thumb').first();
        if ($thumb.length && $thumb.attr('src')) return $thumb.attr('src');
        return '';
    }

    function showPreviewImage() {
        var src = getPreviewImgSrc();
        var $img = $('#naxoorbu-preview-product-img');
        var $wrap = $('#naxoorbu-preview-img-wrap');
        if (src) {
            $img.attr('src', src);
            $wrap.addClass('naxoorbu-img-visible');
        }
    }

    function hidePreviewImage() {
        $('#naxoorbu-preview-img-wrap').removeClass('naxoorbu-img-visible');
    }

    /* ═══════════════════════════════════════
       PRODUCT IMAGE TOGGLE — live preview
    ═══════════════════════════════════════ */
    $('#naxoorbu-show-img-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#naxoorbu-img-options').slideDown(200);
            showPreviewImage();
        } else {
            $('#naxoorbu-img-options').slideUp(200);
            hidePreviewImage();
        }
    });

    // Image width — live update in preview
    $('input[name="naxoorbu_settings[image_width]"]').on('input', function() {
        var w = parseInt($(this).val()) || 96;
        $('#naxoorbu-preview-product-img').css({'width': w+'px', 'height': w+'px'});
    });

    // Image position — live update in preview
    $('select[name="naxoorbu_settings[image_position]"]').on('change', function() {
        var pos = $(this).val();
        var $img = $('#naxoorbu-preview-product-img');
        $img.css({ 'float': pos === 'right' ? 'right' : 'left', 'margin': pos === 'right' ? '0 0 6px 8px' : '0 8px 6px 0' });
    });

    /* ═══════════════════════════════════════
       IMAGE TYPE TOGGLE (Product / Custom)
    ═══════════════════════════════════════ */
    $(document).on('click', '.naxoorbu-imgtype-btn', function() {
        var type = $(this).data('type');
        $('#naxoorbu-img-type-val').val(type);
        $('.naxoorbu-imgtype-btn').removeClass('naxoorbu-switch-active');
        $(this).addClass('naxoorbu-switch-active');
        if (type === 'custom') {
            $('#naxoorbu-custom-img-wrap').show();
        } else {
            $('#naxoorbu-custom-img-wrap').hide();
        }
        if ($('#naxoorbu-show-img-toggle').is(':checked')) {
            showPreviewImage();
        }
    });

    // Custom image URL input — live update preview
    $(document).on('input', 'input[name="naxoorbu_settings[image_custom_url]"]', function() {
        if ($('#naxoorbu-show-img-toggle').is(':checked') && $('#naxoorbu-img-type-val').val() === 'custom') {
            showPreviewImage();
        }
    });

    /* ═══════════════════════════════════════
       SKIN PREVIEW UPDATE
    ═══════════════════════════════════════ */
    function obpGetColors(skin) {
        if (skin === 'skin2') {
            return {
                bg:   $('#naxoorbu-skin2-bg').val()   || naxoorbuAdmin.skin2BgColor   || '#e8f7f9',
                text: $('#naxoorbu-skin2-text').val()  || naxoorbuAdmin.skin2TextColor  || '#155724'
            };
        }
        return {
            bg:   $('#naxoorbu-skin1-bg').val()   || naxoorbuAdmin.skin1BgColor   || '#FFFDE7',
            text: $('#naxoorbu-skin1-text').val()  || naxoorbuAdmin.skin1TextColor  || '#155724'
        };
    }

    function naxoorbuUpdatePreviewColors(skin) {
        var c = obpGetColors(skin);
        var $headline = $('#naxoorbu-preview-headline')[0];
        var $desc     = $('#naxoorbu-preview-desc-area')[0];
        var $cta      = $('#naxoorbu-preview-cta-row')[0];
        if (!$headline) return;

        if (skin === 'skin2') {
            $headline.style.removeProperty('background');
            $headline.style.removeProperty('color');
            $desc.style.removeProperty('background');
            $desc.style.removeProperty('color');
            $cta.style.setProperty('background', c.bg, 'important');
            $cta.style.setProperty('color', c.text, 'important');
        } else {
            $headline.style.setProperty('background', c.bg, 'important');
            $headline.style.setProperty('color', c.text, 'important');
            $desc.style.removeProperty('background');
            $desc.style.removeProperty('color');
            $cta.style.removeProperty('background');
            $cta.style.removeProperty('color');
        }
    }

    function naxoorbuUpdatePreviewSkin(skin) {
        $('#naxoorbu-preview-bump').removeClass('naxoorbu-preview-skin1 naxoorbu-preview-skin2').addClass('naxoorbu-preview-' + skin);
        if (skin === 'skin2') {
            $('#naxoorbu-colors-skin1').hide();
            $('#naxoorbu-colors-skin2').show();
        } else {
            $('#naxoorbu-colors-skin1').show();
            $('#naxoorbu-colors-skin2').hide();
        }
        naxoorbuUpdatePreviewColors(skin);
    }

    // Init skin preview
    var initSkin = (typeof naxoorbuAdmin !== 'undefined' && naxoorbuAdmin.currentSkin) ? naxoorbuAdmin.currentSkin : ($('select[name="naxoorbu_settings[skin]"]').val() || 'skin1');
    naxoorbuUpdatePreviewSkin(initSkin);

    /* ═══════════════════════════════════════
       INIT: Sync preview with current states
       (runs once on page load)
    ═══════════════════════════════════════ */
    (function initPreviewSync() {
        // Badge sync
        if ($('#naxoorbu-show-badge-toggle').is(':checked')) {
            var badgeText = $('input[name="naxoorbu_settings[badge_text]"]').val() || 'Special Offer';
            $('#naxoorbu-preview-badge').text(badgeText).show();
            $('#naxoorbu-preview-headline').css('padding-top', '18px');
        } else {
            $('#naxoorbu-preview-badge').hide();
            $('#naxoorbu-preview-headline').css('padding-top', '');
        }

        // Image: PHP already set the naxoorbu-img-visible class — don't touch!
        // Only hide if toggle is OFF
        if (!$('#naxoorbu-show-img-toggle').is(':checked')) {
            hidePreviewImage();
        }
    })();

    // Skin dropdown change
    $('select[name="naxoorbu_settings[skin]"]').on('change', function() {
        naxoorbuUpdatePreviewSkin($(this).val());
    });

    // Live color change
    $('#naxoorbu-skin1-bg, #naxoorbu-skin1-text, #naxoorbu-skin2-bg, #naxoorbu-skin2-text').on('input', function() {
        naxoorbuUpdatePreviewColors($('select[name="naxoorbu_settings[skin]"]').val() || 'skin1');
    });

    /* ═══════════════════════════════════════
       TRIGGER TYPE (Rules tab)
    ═══════════════════════════════════════ */
    $('#naxoorbu-trigger-type').on('change', function() {
        $('.naxoorbu-rule-val').hide();
        var v = $(this).val();
        if (v !== 'all') $('#rule-' + v).show();
    });

    /* ═══════════════════════════════════════
       PRODUCT DESCRIPTION SWITCHER (Design tab)
       Clean JS — replaces massive inline onclick
    ═══════════════════════════════════════ */
    $(document).on('click', '.naxoorbu-desc-switch-btn', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('index'), 10);

        // Toggle active button
        $('.naxoorbu-desc-switch-btn').removeClass('naxoorbu-switch-active');
        $(this).addClass('naxoorbu-switch-active');

        // Hide all panels, show selected
        $('.naxoorbu-desc-panel').each(function() {
            $(this).css({
                position: 'absolute', top: 0, left: 0,
                width: '1px', height: '1px',
                overflow: 'hidden', opacity: 0, pointerEvents: 'none'
            });
        });
        $('#naxoorbu-desc-panel-' + idx).css({
            display: 'block', position: 'relative',
            width: '', height: '', overflow: '', opacity: '', pointerEvents: ''
        });

        // Update preview description
        var eid = 'naxoorbu_prod_editor_' + idx;
        setTimeout(function() { naxoorbuSyncDescToPreview(eid); }, 80);

        // Update preview image to this product's thumb
        var thumbUrl = $(this).data('thumb') || '';
        if (thumbUrl) {
            activeProductThumb = thumbUrl;
            if ($('#naxoorbu-show-img-toggle').is(':checked') && ($('#naxoorbu-img-type-val').val() || 'product') === 'product') {
                showPreviewImage();
            }
        }
    });

    /* ═══════════════════════════════════════
       PRODUCT SEARCH & ADD (Products tab)
    ═══════════════════════════════════════ */
    var searchTimer;

    $('#naxoorbu-product-search').on('input', function() {
        clearTimeout(searchTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#naxoorbu-product-results').removeClass('open').html(''); return; }
        searchTimer = setTimeout(function() {
            $.post(naxoorbuAdmin.ajaxUrl, { action: 'naxoorbu_search_products', q: q, nonce: naxoorbuAdmin.nonce }, function(res) {
                if (!res.success || !res.data.length) {
                    $('#naxoorbu-product-results').html('<div style="padding:10px;color:#9CA3AF;font-size:13px;">' + (naxoorbuAdmin.i18n.noProducts || 'No products found.') + '</div>').addClass('open');
                    return;
                }
                var html = '';
                res.data.forEach(function(p) {
                    var img = p.thumb
                        ? '<img src="'+p.thumb+'">'
                        : '<div class="naxoorbu-product-item-noimg">📦</div>';
                    html += '<div class="naxoorbu-product-item" data-product=\''+JSON.stringify(p)+'\'>'+img
                        +'<div><div class="naxoorbu-product-item-name">'+p.name+'</div>'
                        +'<div class="naxoorbu-product-item-price">'+p.regular_html+' → '+p.price_html+'</div></div></div>';
                });
                $('#naxoorbu-product-results').html(html).addClass('open');
            }).fail(function() { $('#naxoorbu-product-results').html('<div style="padding:10px;color:#EF4444;font-size:13px;">Search failed. Try again.</div>').addClass('open'); });
        }, 200);
    });

    $(document).on('click', '#naxoorbu-product-results .naxoorbu-product-item', function() {
        var p = $(this).data('product');
        if ($('#naxoorbu-products-list .naxoorbu-product-row[data-product-id="'+p.id+'"]').length) {
            alert(p.name + ' ' + (naxoorbuAdmin.i18n.alreadyAdded || 'is already added.'));
            $('#naxoorbu-product-results').removeClass('open');
            $('#naxoorbu-product-search').val('');
            return;
        }
        // Check product limit per bump
        var maxProducts = parseInt(naxoorbuAdmin.maxProducts) || 2;
        if ($('#naxoorbu-products-list .naxoorbu-product-row').length >= maxProducts) {
            alert(naxoorbuAdmin.i18n.maxProductsMsg || 'Maximum ' + maxProducts + ' products per bump allowed.');
            $('#naxoorbu-product-results').removeClass('open');
            $('#naxoorbu-product-search').val('');
            return;
        }
        // Check product limit per bump
        var maxProducts = parseInt(naxoorbuAdmin.maxProducts) || 2;
        if ($('#naxoorbu-products-list .naxoorbu-product-row').length >= maxProducts) {
            alert(naxoorbuAdmin.i18n.maxProductsMsg || 'Maximum ' + maxProducts + ' products per bump allowed.');
            $('#naxoorbu-product-results').removeClass('open');
            $('#naxoorbu-product-search').val('');
            return;
        }
        addProductRow(p);
        $('#naxoorbu-product-results').removeClass('open');
        $('#naxoorbu-product-search').val('');
    });

    function addProductRow(p) {
        var i = $('#naxoorbu-products-list .naxoorbu-product-row').length;
        var prefix = 'naxoorbu_settings[products][' + i + ']';
        var thumb = p.thumb
            ? '<img src="'+p.thumb+'" class="naxoorbu-product-thumb">'
            : '<div class="naxoorbu-product-thumb naxoorbu-no-thumb">📦</div>';
        var stockClass = 'naxoorbu-badge-green';
        var stockText  = naxoorbuAdmin.i18n.inStock || 'in-stock';

        var html = '<div class="naxoorbu-product-row" data-product-id="'+p.id+'">'
            + '<div class="naxoorbu-product-row-header">'
            + '<span class="naxoorbu-drag-handle">⠿</span>'
            + thumb
            + '<div class="naxoorbu-product-row-info">'
            + '<strong>'+p.name+'</strong>'
            + '<span class="naxoorbu-muted">Regular: '+p.regular_html+' | Sale: '+p.price_html+'</span>'
            + '</div>'
            + '<div class="naxoorbu-product-row-controls">'
            + '<input type="number" name="'+prefix+'[discount]" value="0" class="naxoorbu-input naxoorbu-input-sm" min="0" style="width:60px" placeholder="0">'
            + '<select name="'+prefix+'[discount_type]" class="naxoorbu-select naxoorbu-select-sm">'
            + '<option value="percentage">% Sale</option><option value="flat">Flat ₹</option></select>'
            + '<input type="number" name="'+prefix+'[qty]" value="1" class="naxoorbu-input naxoorbu-input-sm" min="1" style="width:52px">'
            + '<span class="naxoorbu-badge '+stockClass+'" style="white-space:nowrap">'+stockText+'</span>'
            + '<button type="button" class="naxoorbu-remove-product naxoorbu-btn-icon" data-id="'+p.id+'" title="Remove">🗑️</button>'
            + '</div>'
            + '<input type="hidden" name="'+prefix+'[id]" value="'+p.id+'">'
            + '</div>'
            + '</div>';

        $('#naxoorbu-products-list').append(html);
        $('#naxoorbu-no-products-msg').hide();
        reindexProducts();
        syncDesignTabProducts();
        showNotice(naxoorbuAdmin.i18n.productAdded || 'Product added!', 'info');
    }

    /* ── REMOVE PRODUCT ── */
    $(document).on('click', '.naxoorbu-remove-product', function() {
        $(this).closest('.naxoorbu-product-row').remove();
        reindexProducts();
        syncDesignTabProducts();
        if ($('#naxoorbu-products-list .naxoorbu-product-row').length === 0)
            $('#naxoorbu-no-products-msg').show();
    });

    function reindexProducts() {
        $('#naxoorbu-products-list .naxoorbu-product-row').each(function(i) {
            $(this).find('input, select').each(function() {
                var n = $(this).attr('name');
                if (n) $(this).attr('name', n.replace(/\[products\]\[\d+\]/, '[products][' + i + ']'));
            });
        });
    }

    /* ═══════════════════════════════════════
       DRAG & DROP PRODUCT REORDER
    ═══════════════════════════════════════ */
    if ($.fn.sortable) {
        $('#naxoorbu-products-list').sortable({
            handle: '.naxoorbu-drag-handle',
            placeholder: 'naxoorbu-sortable-placeholder',
            opacity: 0.7,
            tolerance: 'pointer',
            update: function() {
                reindexProducts();
            }
        });
    }

    /* ═══════════════════════════════════════
       TRIGGER PRODUCT SEARCH
    ═══════════════════════════════════════ */
    var tTimer;
    $('#naxoorbu-trigger-search').on('input', function() {
        clearTimeout(tTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#naxoorbu-trigger-results').removeClass('open').html(''); return; }
        tTimer = setTimeout(function() {
            $.post(naxoorbuAdmin.ajaxUrl, { action: 'naxoorbu_search_products', q: q, nonce: naxoorbuAdmin.nonce }, function(res) {
                if (!res.success || !res.data.length) { $('#naxoorbu-trigger-results').removeClass('open'); return; }
                var html = '';
                res.data.forEach(function(p) {
                    html += '<div class="naxoorbu-product-item" data-id="'+p.id+'" data-name="'+p.name+'">'
                        +'<div class="naxoorbu-product-item-name">'+p.name+'</div></div>';
                });
                $('#naxoorbu-trigger-results').html(html).addClass('open');
            });
        }, 200);
    });

    $(document).on('click', '#naxoorbu-trigger-results .naxoorbu-product-item', function() {
        var id = $(this).data('id'), name = $(this).data('name');
        if ($('#naxoorbu-trigger-tags .naxoorbu-tag[data-id="'+id+'"]').length) {
            $('#naxoorbu-trigger-results').removeClass('open');
            $('#naxoorbu-trigger-search').val('');
            return;
        }
        $('#naxoorbu-trigger-tags').append(
            '<span class="naxoorbu-tag" data-id="'+id+'">'+name
            +'<input type="hidden" name="naxoorbu_settings[trigger_products][]" value="'+id+'">'
            +'<button type="button" class="naxoorbu-tag-remove">×</button></span>'
        );
        $('#naxoorbu-trigger-results').removeClass('open');
        $('#naxoorbu-trigger-search').val('');
    });

    $(document).on('click', '.naxoorbu-tag-remove', function() { $(this).closest('.naxoorbu-tag').remove(); });

    // Close dropdowns on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.naxoorbu-add-product-row, #naxoorbu-trigger-search').length)
            $('.naxoorbu-product-dropdown').removeClass('open');
    });

    /* ═══════════════════════════════════════
       DASHBOARD: STATUS TOGGLE
    ═══════════════════════════════════════ */
    $(document).on('change', '.naxoorbu-status-toggle', function() {
        var $t = $(this), id = $t.data('id');
        $t.prop('disabled', true);
        $.post(naxoorbuAdmin.ajaxUrl, { action: 'naxoorbu_toggle_bump', id: id, nonce: naxoorbuAdmin.nonce }, function(res) {
            if (!res.success) { $t.prop('checked', !$t.prop('checked')); alert(naxoorbuAdmin.i18n.error || 'Error.'); }
        }).fail(function() {
            $t.prop('checked', !$t.prop('checked'));
            alert(naxoorbuAdmin.i18n.error || 'Error. Please try again.');
        }).always(function() {
            $t.prop('disabled', false);
        });
    });

    /* ═══════════════════════════════════════
       DASHBOARD: DELETE
    ═══════════════════════════════════════ */
    $(document).on('click', '.naxoorbu-action-delete', function(e) {
        e.preventDefault();
        if (!confirm(naxoorbuAdmin.i18n.deleteConfirm || 'Delete this Order Bump?')) return;
        var id = $(this).data('id'), nonce = $(this).data('nonce');
        var $btn = $(this);
        $btn.text('Deleting...').css('pointer-events', 'none');
        $.post(naxoorbuAdmin.ajaxUrl, { action: 'naxoorbu_delete_bump', id: id, nonce: nonce }, function(res) {
            if (res.success) window.location = naxoorbuAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=naxolabs-order-bump&deleted=1';
            else { alert(naxoorbuAdmin.i18n.errorDeleting || 'Error deleting.'); $btn.text('Delete').css('pointer-events', ''); }
        }).fail(function() {
            alert(naxoorbuAdmin.i18n.error || 'Error. Please try again.');
            $btn.text('Delete').css('pointer-events', '');
        });
    });

    /* ═══════════════════════════════════════
       MEDIA LIBRARY — Custom Image Select
    ═══════════════════════════════════════ */
    $('#naxoorbu-select-img-btn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
            var url = frame.state().get('selection').first().toJSON().url;
            $('#naxoorbu-custom-img-url').val(url);
        });
        frame.open();
    });

    /* ==========================================
       LIVE SYNC: Products tab -> Design tab
    ========================================== */
    function syncDesignTabProducts() {
        var products = [];
        $('#naxoorbu-products-list .naxoorbu-product-row').each(function(i) {
            var name = $(this).find('.naxoorbu-product-row-info strong').text();
            var thumb = $(this).find('.naxoorbu-product-thumb').attr('src') || '';
            var id = $(this).data('product-id');
            products.push({ idx: i, name: name, thumb: thumb, id: id });
        });

        var $descCard = $('#naxoorbu-design-desc-card .naxoorbu-card-body');
        var $switcherWrap = $descCard.find('.naxoorbu-desc-switcher-wrap');

        if (products.length === 0) {
            $switcherWrap.hide();
            $descCard.find('.naxoorbu-desc-panel').hide();
            $descCard.find('.naxoorbu-help').hide();
            if (!$descCard.find('.naxoorbu-no-prod-hint').length) {
                $descCard.append('<p class="naxoorbu-help naxoorbu-no-prod-hint" style="padding:12px 0;text-align:center;">First add products in the <strong>Products tab</strong>.</p>');
            }
            $descCard.find('.naxoorbu-no-prod-hint').show();
            return;
        }

        $descCard.find('.naxoorbu-no-prod-hint').remove();

        if (!$switcherWrap.length) {
            $descCard.html('');
            $descCard.append('<div class="naxoorbu-desc-switcher-wrap" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;padding:10px;background:#F5F3FF;border-radius:8px;border:1px solid #E5E2FF;"></div>');
            $descCard.append('<div class="naxoorbu-desc-editors-wrap" style="position:relative;"></div>');
            $descCard.append('<p class="naxoorbu-help" style="margin-top:6px;">Use short promotional text. HTML supported.</p>');
            $switcherWrap = $descCard.find('.naxoorbu-desc-switcher-wrap');
        }

        $switcherWrap.empty();
        products.forEach(function(p, idx) {
            var shortName = p.name.length > 40 ? p.name.substring(0, 40) + '...' : p.name;
            var activeClass = idx === 0 ? ' naxoorbu-switch-active' : '';
            $switcherWrap.append('<button type="button" class="naxoorbu-desc-switch-btn' + activeClass + '" data-index="' + idx + '" id="naxoorbu-swbtn-' + idx + '">' + shortName + '</button>');
        });
        $switcherWrap.show();

        var $editorsWrap = $descCard.find('.naxoorbu-desc-editors-wrap');
        if (!$editorsWrap.length) {
            $switcherWrap.after('<div class="naxoorbu-desc-editors-wrap" style="position:relative;"></div>');
            $editorsWrap = $descCard.find('.naxoorbu-desc-editors-wrap');
        }

        products.forEach(function(p, idx) {
            var panelId = 'naxoorbu-desc-panel-' + idx;
            var editorId = 'naxoorbu_prod_editor_' + idx;
            if (!$('#' + panelId).length) {
                var display = idx === 0 ? 'display:block;' : 'display:none;';
                $editorsWrap.append('<div id="' + panelId + '" class="naxoorbu-desc-panel" style="' + display + '"><textarea id="' + editorId + '" name="naxoorbu_settings[products][' + idx + '][description]" rows="8" style="width:100%;min-height:180px;"></textarea></div>');

                // Initialize TinyMCE on the new textarea
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.initialize(editorId, {
                        tinymce: {
                            wpautop: true,
                            toolbar1: 'formatselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,|,bullist,numlist,|,link,unlink,|,removeformat',
                            plugins: 'textcolor,lists,link',
                            block_formats: 'Paragraph=p;Heading 3=h3;Heading 4=h4;Heading 5=h5',
                            fontsize_formats: '12px 13px 14px 16px 18px 20px 24px',
                            toolbar2: '',
                            height: 220
                        },
                        quicktags: true,
                        mediaButtons: false
                    });

                    // Bind live preview sync
                    setTimeout(function() {
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                            var editor = tinyMCE.get(editorId);
                            editor.on('keyup change input NodeChange', function() {
                                var panelIdx = editorId.replace('naxoorbu_prod_editor_', '');
                                var panel = document.getElementById('naxoorbu-desc-panel-' + panelIdx);
                                if (panel && panel.style.display !== 'none') {
                                    naxoorbuSyncDescToPreview(editorId);
                                }
                            });
                            editor.isNotDirty = true;
                        }
                    }, 500);
                }
            }
        });

        // Remove extra panels for deleted products
        $descCard.find('.naxoorbu-desc-panel').each(function() {
            var panelIdx = parseInt($(this).attr('id').replace('naxoorbu-desc-panel-', ''));
            if (panelIdx >= products.length) {
                var editorId = 'naxoorbu_prod_editor_' + panelIdx;
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.remove(editorId);
                }
                $(this).remove();
            }
        });

        $descCard.find('.naxoorbu-help').not('.naxoorbu-no-prod-hint').show();
    }
    /* ═══════════════════════════════════════
       TINYMCE LIVE PREVIEW
    ═══════════════════════════════════════ */
    function naxoorbuSyncDescToPreview(editorId) {
        var descTextEl = document.getElementById('naxoorbu-preview-desc-text');
        if (!descTextEl) return;
        var content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
            content = tinyMCE.get(editorId).getContent();
        } else {
            var ta = document.getElementById(editorId);
            if (ta) content = ta.value;
        }
        descTextEl.innerHTML = content || '<em style="color:#9CA3AF">' + (naxoorbuAdmin.i18n.noDescription || 'No description set.') + '</em>';
    }

    // Sync active editor to preview on product switch
    $(document).on('click', '.naxoorbu-prod-switch-btn', function() {
        var idx = $(this).data('index');
        var editorId = 'naxoorbu_prod_editor_' + idx;
        setTimeout(function() {
            naxoorbuSyncDescToPreview(editorId);
        }, 200);
    });

    // Also sync on textarea change (Code view)
    $(document).on('input change', 'textarea[id^="naxoorbu_prod_editor_"]', function() {
        naxoorbuSyncDescToPreview(this.id);
    });

    function obpBindTinyMCELive() {
        if (typeof tinyMCE === 'undefined') {
            setTimeout(obpBindTinyMCELive, 300);
            return;
        }

        tinyMCE.on('AddEditor', function(e) {
            var editor = e.editor;
            var eid = editor.id;
            if (eid.indexOf('naxoorbu_prod_editor_') !== 0) return;

            editor.on('keyup change input NodeChange', function() {
                var panelIdx = eid.replace('naxoorbu_prod_editor_', '');
                var panel = document.getElementById('naxoorbu-desc-panel-' + panelIdx);
                if (!panel) return;
                var style = window.getComputedStyle(panel);
                var isVisible = (style.display !== 'none' && style.opacity !== '0' && panel.offsetWidth > 1);
                if (isVisible) naxoorbuSyncDescToPreview(eid);
            });
        });

        if (tinyMCE.editors && tinyMCE.editors.length) {
            tinyMCE.editors.forEach(function(editor) {
                var eid = editor.id;
                if (eid.indexOf('naxoorbu_prod_editor_') !== 0) return;
                editor.on('keyup change input NodeChange', function() {
                    var panelIdx = eid.replace('naxoorbu_prod_editor_', '');
                    var panel = document.getElementById('naxoorbu-desc-panel-' + panelIdx);
                    if (!panel) return;
                    var style = window.getComputedStyle(panel);
                    var isVisible = (style.display !== 'none' && style.opacity !== '0' && panel.offsetWidth > 1);
                    if (isVisible) naxoorbuSyncDescToPreview(eid);
                });
            });
        }
    }

    obpBindTinyMCELive();

    // Prevent false "unsaved changes" popup
    function obpClearDirtyFlags() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.editors) {
            tinyMCE.editors.forEach(function(editor) {
                editor.isNotDirty = true;
            });
        }
        $(window).off('beforeunload');
        window.onbeforeunload = null;
    }
    // Clear on page load
    setTimeout(obpClearDirtyFlags, 500);
    setTimeout(obpClearDirtyFlags, 1500);
    // Clear on form submit
    $('form#naxoorbu-edit-form, form.naxoorbu-edit-form').on('submit', function() {
        obpClearDirtyFlags();
    });
    // Also clear when Save button is clicked
    $(document).on('click', '.naxoorbu-btn-save, [type="submit"]', function() {
        obpClearDirtyFlags();
    });

    /* ═══════════════════════════════════════
       NOTICE HELPER
    ═══════════════════════════════════════ */
    function showNotice(msg, type) {
        var cls = type === 'info' ? 'naxoorbu-notice-info' : 'naxoorbu-notice-success';
        var $n = $('<div class="naxoorbu-notice '+cls+'">'+msg+'</div>');
        $('.naxoorbu-name-bar').before($n);
        setTimeout(function() { $n.fadeOut(400, function() { $(this).remove(); }); }, 3000);
    }



    /* ===== Category Multi-Select Dropdown ===== */
    (function() {
        var $toggle = $('#naxoorbu-cat-toggle');
        var $dropdown = $('#naxoorbu-cat-dropdown');
        var $tags = $('#naxoorbu-cat-tags');
        var $search = $('#naxoorbu-cat-search');

        if (!$toggle.length) return;

        // Toggle dropdown
        $toggle.on('click', function() {
            var isOpen = $dropdown.is(':visible');
            $dropdown.toggle(!isOpen);
            $toggle.toggleClass('naxoorbu-cat-open', !isOpen);
            if (!isOpen) $search.val('').trigger('input').focus();
        });

        // Close on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.naxoorbu-cat-select-wrap').length) {
                $dropdown.hide();
                $toggle.removeClass('naxoorbu-cat-open');
            }
        });

        // Search filter
        $search.on('input', function() {
            var q = $(this).val().toLowerCase();
            $dropdown.find('.naxoorbu-cat-item').each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(q) > -1);
            });
        });

        // Checkbox change — add/remove tag
        $dropdown.on('change', 'input[type="checkbox"]', function() {
            var $cb = $(this);
            var id = $cb.val();
            var name = $cb.closest('.naxoorbu-cat-item').text().trim();

            if ($cb.is(':checked')) {
                // Add tag
                var tag = '<span class="naxoorbu-cat-tag" data-id="' + id + '">' +
                          name +
                          '<button type="button" class="naxoorbu-cat-tag-remove">&times;</button>' +
                          '</span>';
                $tags.append(tag);
            } else {
                // Remove tag
                $tags.find('.naxoorbu-cat-tag[data-id="' + id + '"]').remove();
            }
            updatePlaceholder();
        });

        // Remove tag click
        $tags.on('click', '.naxoorbu-cat-tag-remove', function() {
            var $tag = $(this).closest('.naxoorbu-cat-tag');
            var id = $tag.data('id');
            // Uncheck checkbox
            $dropdown.find('input[value="' + id + '"]').prop('checked', false);
            $tag.remove();
            updatePlaceholder();
        });

        function updatePlaceholder() {
            var count = $tags.find('.naxoorbu-cat-tag').length;
            if (count > 0) {
                $toggle.find('.naxoorbu-cat-placeholder').text(count + ' ' + (count === 1 ? 'category' : 'categories') + ' selected');
            } else {
                $toggle.find('.naxoorbu-cat-placeholder').text('Select categories...');
            }
        }
        updatePlaceholder();
    })();



    /* ===== Overlap Warning: Bump Products vs Trigger Products ===== */
    function obpCheckProductOverlap() {
        // Get bump product IDs
        var bumpIds = [];
        $('#naxoorbu-products-list .naxoorbu-product-row').each(function() {
            bumpIds.push(String($(this).data('product-id')));
        });

        // Get trigger product IDs (Cart Items rule)
        var triggerIds = [];
        $('#naxoorbu-trigger-tags .naxoorbu-tag').each(function() {
            triggerIds.push(String($(this).data('id')));
        });

        // Find overlapping IDs
        var overlap = bumpIds.filter(function(id) {
            return triggerIds.indexOf(id) > -1;
        });

        // Remove old warnings
        $('.naxoorbu-overlap-warn').remove();

        if (overlap.length === 0) return;

        // Show inline warning on each overlapping product row
        overlap.forEach(function(id) {
            var $row = $('#naxoorbu-products-list .naxoorbu-product-row[data-product-id="' + id + '"]');
            if ($row.length && !$row.find('.naxoorbu-overlap-warn').length) {
                $row.append(
                    '<div class="naxoorbu-overlap-warn">' +
                    '<span class="naxoorbu-warn-icon">⚠️</span> ' +
                    'This product is also in your trigger rules. Customer already has it in cart when bump shows.' +
                    '</div>'
                );
            }

            // Also mark the trigger tag
            var $tag = $('#naxoorbu-trigger-tags .naxoorbu-tag[data-id="' + id + '"]');
            if ($tag.length && !$tag.hasClass('naxoorbu-tag-warn')) {
                $tag.addClass('naxoorbu-tag-warn');
            }
        });

        // Show page-level notice (only once)
        if (!$('.naxoorbu-overlap-page-warn').length) {
            var names = [];
            overlap.forEach(function(id) {
                var $row = $('#naxoorbu-products-list .naxoorbu-product-row[data-product-id="' + id + '"]');
                var name = $row.find('.naxoorbu-product-name').text().trim() || 'Product #' + id;
                names.push(name);
            });
            var msg = '<div class="naxoorbu-overlap-page-warn">' +
                      '<span class="naxoorbu-warn-icon">⚠️</span> <strong>Overlap detected:</strong> ' +
                      names.join(', ') +
                      ' — same product is both a bump offer and a trigger condition. ' +
                      'Customer will see this product offered when they already have it in cart.' +
                      '</div>';
            $('.naxoorbu-name-bar').after(msg);
        }
    }

    // Run on page load
    setTimeout(obpCheckProductOverlap, 500);

    // Run when products added/removed
    $(document).on('DOMNodeInserted DOMNodeRemoved', '#naxoorbu-products-list, #naxoorbu-trigger-tags', function() {
        setTimeout(obpCheckProductOverlap, 100);
    });

    // Run when trigger type changes
    $(document).on('change', '#naxoorbu-trigger-type', function() {
        setTimeout(obpCheckProductOverlap, 200);
    });

});
