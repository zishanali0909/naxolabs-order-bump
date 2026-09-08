jQuery(function($) {

    /* ═══════════════════════════════════════
       TABS
    ═══════════════════════════════════════ */
    $('.obp-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.obp-tab').removeClass('active');
        $(this).addClass('active');
        $('.obp-tab-content').hide();
        $('#tab-' + tab).show();
        $('input[name="active_tab"]').val(tab);
    });

    // On page load — show correct tab from URL
    (function() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || 'design';
        $('.obp-tab').removeClass('active');
        $('.obp-tab[data-tab="' + tab + '"]').addClass('active');
        $('.obp-tab-content').hide();
        $('#tab-' + tab).show();
        $('input[name="active_tab"]').val(tab);
    })();

    /* ═══════════════════════════════════════
       STATUS TOGGLE (header)
    ═══════════════════════════════════════ */
    $('#obp-bump-status-top').on('change', function() {
        var isActive = $(this).is(':checked');
        $('#obp-status-field').val(isActive ? 'publish' : 'draft');
        $('#obp-status-label').text(isActive ? (obpAdmin.i18n.active || 'Active') : (obpAdmin.i18n.inactive || 'Inactive'));
    });

    /* ═══════════════════════════════════════
       BADGE TOGGLE — show/hide badge text
    ═══════════════════════════════════════ */
    $('#obp-show-badge-toggle').on('change', function() {
        var isOn = $(this).is(':checked');
        if (isOn) {
            $('#obp-badge-text-wrap').slideDown(200);
            // Show badge in preview
            var text = $('input[name="obp_settings[badge_text]"]').val() || 'Special Offer';
            $('#obp-preview-badge').text(text).fadeIn(200);
            $('#obp-preview-headline').css('padding-top', '18px');
        } else {
            $('#obp-badge-text-wrap').slideUp(200);
            // Hide badge in preview
            $('#obp-preview-badge').fadeOut(200);
            $('#obp-preview-headline').css('padding-top', '');
        }
    });

    // Badge text — live update in preview
    $('input[name="obp_settings[badge_text]"]').on('input', function() {
        var text = $(this).val() || 'Special Offer';
        $('#obp-preview-badge').text(text);
    });

    /* ═══════════════════════════════════════
       HEADLINE — live update in preview
    ═══════════════════════════════════════ */
    $('input[name="obp_settings[headline]"]').on('input', function() {
        var text = $(this).val() || 'Yes! Add {{product_name}} to my order';
        $('#obp-preview-headline-text').html(text);
        // Also update skin2 CTA row
        $('#obp-preview-cta-row span').last().html(text);
    });

    /* ═══════════════════════════════════════
       IMAGE PREVIEW HELPER
       Uses CSS class for bulletproof visibility
       Tracks active product thumb for switching
    ═══════════════════════════════════════ */
    // Track the currently active product's thumbnail
    var activeProductThumb = obpAdmin.firstProductImg || '';
    // Initialize from the active switch button if available
    var $initBtn = $('.obp-desc-switch-btn.obp-switch-active');
    if ($initBtn.length && $initBtn.data('thumb')) {
        activeProductThumb = $initBtn.data('thumb');
    }

    function getPreviewImgSrc() {
        var type = $('#obp-img-type-val').val() || obpAdmin.imageType || 'product';
        if (type === 'custom') {
            return $('input[name="obp_settings[image_custom_url]"]').val() || obpAdmin.customImageUrl || '';
        }
        // Use active product's thumb
        if (activeProductThumb) return activeProductThumb;
        // Fallback: existing img src
        var existingSrc = $('#obp-preview-product-img').attr('src');
        if (existingSrc) return existingSrc;
        // Fallback: product row thumb
        var $thumb = $('.obp-product-row .obp-product-thumb').not('.obp-no-thumb').first();
        if ($thumb.length && $thumb.attr('src')) return $thumb.attr('src');
        return '';
    }

    function showPreviewImage() {
        var src = getPreviewImgSrc();
        var $img = $('#obp-preview-product-img');
        var $wrap = $('#obp-preview-img-wrap');
        if (src) {
            $img.attr('src', src);
            $wrap.addClass('obp-img-visible');
        }
    }

    function hidePreviewImage() {
        $('#obp-preview-img-wrap').removeClass('obp-img-visible');
    }

    /* ═══════════════════════════════════════
       PRODUCT IMAGE TOGGLE — live preview
    ═══════════════════════════════════════ */
    $('#obp-show-img-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#obp-img-options').slideDown(200);
            showPreviewImage();
        } else {
            $('#obp-img-options').slideUp(200);
            hidePreviewImage();
        }
    });

    // Image width — live update in preview
    $('input[name="obp_settings[image_width]"]').on('input', function() {
        var w = parseInt($(this).val()) || 96;
        $('#obp-preview-product-img').css({'width': w+'px', 'height': w+'px'});
    });

    // Image position — live update in preview
    $('select[name="obp_settings[image_position]"]').on('change', function() {
        $('#obp-preview-img-wrap').css('flex-direction', $(this).val() === 'right' ? 'row-reverse' : 'row');
    });

    /* ═══════════════════════════════════════
       IMAGE TYPE TOGGLE (Product / Custom)
    ═══════════════════════════════════════ */
    $(document).on('click', '.obp-imgtype-btn', function() {
        var type = $(this).data('type');
        $('#obp-img-type-val').val(type);
        $('.obp-imgtype-btn').removeClass('obp-switch-active');
        $(this).addClass('obp-switch-active');
        if (type === 'custom') {
            $('#obp-custom-img-wrap').show();
        } else {
            $('#obp-custom-img-wrap').hide();
        }
        if ($('#obp-show-img-toggle').is(':checked')) {
            showPreviewImage();
        }
    });

    // Custom image URL input — live update preview
    $(document).on('input', 'input[name="obp_settings[image_custom_url]"]', function() {
        if ($('#obp-show-img-toggle').is(':checked') && $('#obp-img-type-val').val() === 'custom') {
            showPreviewImage();
        }
    });

    /* ═══════════════════════════════════════
       SKIN PREVIEW UPDATE
    ═══════════════════════════════════════ */
    function obpGetColors(skin) {
        if (skin === 'skin2') {
            return {
                bg:   $('#obp-skin2-bg').val()   || obpAdmin.skin2BgColor   || '#e8f7f9',
                text: $('#obp-skin2-text').val()  || obpAdmin.skin2TextColor  || '#155724'
            };
        }
        return {
            bg:   $('#obp-skin1-bg').val()   || obpAdmin.skin1BgColor   || '#FFFDE7',
            text: $('#obp-skin1-text').val()  || obpAdmin.skin1TextColor  || '#155724'
        };
    }

    function obpUpdatePreviewColors(skin) {
        var c = obpGetColors(skin);
        var $headline = $('#obp-preview-headline')[0];
        var $desc     = $('#obp-preview-desc-area')[0];
        var $cta      = $('#obp-preview-cta-row')[0];
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

    function obpUpdatePreviewSkin(skin) {
        $('#obp-preview-bump').removeClass('obp-preview-skin1 obp-preview-skin2').addClass('obp-preview-' + skin);
        if (skin === 'skin2') {
            $('#obp-colors-skin1').hide();
            $('#obp-colors-skin2').show();
        } else {
            $('#obp-colors-skin1').show();
            $('#obp-colors-skin2').hide();
        }
        obpUpdatePreviewColors(skin);
    }

    // Init skin preview
    var initSkin = (typeof obpAdmin !== 'undefined' && obpAdmin.currentSkin) ? obpAdmin.currentSkin : ($('select[name="obp_settings[skin]"]').val() || 'skin1');
    obpUpdatePreviewSkin(initSkin);

    /* ═══════════════════════════════════════
       INIT: Sync preview with current states
       (runs once on page load)
    ═══════════════════════════════════════ */
    (function initPreviewSync() {
        // Badge sync
        if ($('#obp-show-badge-toggle').is(':checked')) {
            var badgeText = $('input[name="obp_settings[badge_text]"]').val() || 'Special Offer';
            $('#obp-preview-badge').text(badgeText).show();
            $('#obp-preview-headline').css('padding-top', '18px');
        } else {
            $('#obp-preview-badge').hide();
            $('#obp-preview-headline').css('padding-top', '');
        }

        // Image: PHP already set the obp-img-visible class — don't touch!
        // Only hide if toggle is OFF
        if (!$('#obp-show-img-toggle').is(':checked')) {
            hidePreviewImage();
        }
    })();

    // Skin dropdown change
    $('select[name="obp_settings[skin]"]').on('change', function() {
        obpUpdatePreviewSkin($(this).val());
    });

    // Live color change
    $('#obp-skin1-bg, #obp-skin1-text, #obp-skin2-bg, #obp-skin2-text').on('input', function() {
        obpUpdatePreviewColors($('select[name="obp_settings[skin]"]').val() || 'skin1');
    });

    /* ═══════════════════════════════════════
       TRIGGER TYPE (Rules tab)
    ═══════════════════════════════════════ */
    $('#obp-trigger-type').on('change', function() {
        $('.obp-rule-val').hide();
        var v = $(this).val();
        if (v !== 'all') $('#rule-' + v).show();
    });

    /* ═══════════════════════════════════════
       PRODUCT DESCRIPTION SWITCHER (Design tab)
       Clean JS — replaces massive inline onclick
    ═══════════════════════════════════════ */
    $(document).on('click', '.obp-desc-switch-btn', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('index'), 10);

        // Toggle active button
        $('.obp-desc-switch-btn').removeClass('obp-switch-active');
        $(this).addClass('obp-switch-active');

        // Hide all panels, show selected
        $('.obp-desc-panel').each(function() {
            $(this).css({
                position: 'absolute', top: 0, left: 0,
                width: '1px', height: '1px',
                overflow: 'hidden', opacity: 0, pointerEvents: 'none'
            });
        });
        $('#obp-desc-panel-' + idx).css({
            display: 'block', position: 'relative',
            width: '', height: '', overflow: '', opacity: '', pointerEvents: ''
        });

        // Update preview description
        var eid = 'obp_prod_editor_' + idx;
        setTimeout(function() { obpSyncDescToPreview(eid); }, 80);

        // Update preview image to this product's thumb
        var thumbUrl = $(this).data('thumb') || '';
        if (thumbUrl) {
            activeProductThumb = thumbUrl;
            if ($('#obp-show-img-toggle').is(':checked') && ($('#obp-img-type-val').val() || 'product') === 'product') {
                showPreviewImage();
            }
        }
    });

    /* ═══════════════════════════════════════
       PRODUCT SEARCH & ADD (Products tab)
    ═══════════════════════════════════════ */
    var searchTimer;

    $('#obp-product-search').on('input', function() {
        clearTimeout(searchTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#obp-product-results').removeClass('open').html(''); return; }
        searchTimer = setTimeout(function() {
            $.post(obpAdmin.ajaxUrl, { action: 'obp_search_products', q: q, nonce: obpAdmin.nonce }, function(res) {
                if (!res.success || !res.data.length) {
                    $('#obp-product-results').html('<div style="padding:10px;color:#9CA3AF;font-size:13px;">' + (obpAdmin.i18n.noProducts || 'No products found.') + '</div>').addClass('open');
                    return;
                }
                var html = '';
                res.data.forEach(function(p) {
                    var img = p.thumb
                        ? '<img src="'+p.thumb+'">'
                        : '<div class="obp-product-item-noimg">📦</div>';
                    html += '<div class="obp-product-item" data-product=\''+JSON.stringify(p)+'\'>'+img
                        +'<div><div class="obp-product-item-name">'+p.name+'</div>'
                        +'<div class="obp-product-item-price">'+p.regular_html+' → '+p.price_html+'</div></div></div>';
                });
                $('#obp-product-results').html(html).addClass('open');
            }).fail(function() { $('#obp-product-results').html('<div style="padding:10px;color:#EF4444;font-size:13px;">Search failed. Try again.</div>').addClass('open'); });
        }, 200);
    });

    $(document).on('click', '#obp-product-results .obp-product-item', function() {
        var p = $(this).data('product');
        if ($('#obp-products-list .obp-product-row[data-product-id="'+p.id+'"]').length) {
            alert(p.name + ' ' + (obpAdmin.i18n.alreadyAdded || 'is already added.'));
            $('#obp-product-results').removeClass('open');
            $('#obp-product-search').val('');
            return;
        }
        addProductRow(p);
        $('#obp-product-results').removeClass('open');
        $('#obp-product-search').val('');
    });

    function addProductRow(p) {
        var i = $('#obp-products-list .obp-product-row').length;
        var prefix = 'obp_settings[products][' + i + ']';
        var thumb = p.thumb
            ? '<img src="'+p.thumb+'" class="obp-product-thumb">'
            : '<div class="obp-product-thumb obp-no-thumb">📦</div>';
        var stockClass = 'obp-badge-green';
        var stockText  = obpAdmin.i18n.inStock || 'in-stock';

        var html = '<div class="obp-product-row" data-product-id="'+p.id+'">'
            + '<div class="obp-product-row-header">'
            + '<span class="obp-drag-handle">⠿</span>'
            + thumb
            + '<div class="obp-product-row-info">'
            + '<strong>'+p.name+'</strong>'
            + '<span class="obp-muted">Regular: '+p.regular_html+' | Sale: '+p.price_html+'</span>'
            + '</div>'
            + '<div class="obp-product-row-controls">'
            + '<input type="number" name="'+prefix+'[discount]" value="0" class="obp-input obp-input-sm" min="0" style="width:60px" placeholder="0">'
            + '<select name="'+prefix+'[discount_type]" class="obp-select obp-select-sm">'
            + '<option value="percentage">% Sale</option><option value="flat">Flat ₹</option></select>'
            + '<input type="number" name="'+prefix+'[qty]" value="1" class="obp-input obp-input-sm" min="1" style="width:52px">'
            + '<span class="obp-badge '+stockClass+'" style="white-space:nowrap">'+stockText+'</span>'
            + '<button type="button" class="obp-remove-product obp-btn-icon" data-id="'+p.id+'" title="Remove">🗑️</button>'
            + '</div>'
            + '<input type="hidden" name="'+prefix+'[id]" value="'+p.id+'">'
            + '</div>'
            + '</div>';

        $('#obp-products-list').append(html);
        $('#obp-no-products-msg').hide();
        reindexProducts();
        syncDesignTabProducts();
        showNotice(obpAdmin.i18n.productAdded || 'Product added!', 'info');
    }

    /* ── REMOVE PRODUCT ── */
    $(document).on('click', '.obp-remove-product', function() {
        $(this).closest('.obp-product-row').remove();
        reindexProducts();
        syncDesignTabProducts();
        if ($('#obp-products-list .obp-product-row').length === 0)
            $('#obp-no-products-msg').show();
    });

    function reindexProducts() {
        $('#obp-products-list .obp-product-row').each(function(i) {
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
        $('#obp-products-list').sortable({
            handle: '.obp-drag-handle',
            placeholder: 'obp-sortable-placeholder',
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
    $('#obp-trigger-search').on('input', function() {
        clearTimeout(tTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#obp-trigger-results').removeClass('open').html(''); return; }
        tTimer = setTimeout(function() {
            $.post(obpAdmin.ajaxUrl, { action: 'obp_search_products', q: q, nonce: obpAdmin.nonce }, function(res) {
                if (!res.success || !res.data.length) { $('#obp-trigger-results').removeClass('open'); return; }
                var html = '';
                res.data.forEach(function(p) {
                    html += '<div class="obp-product-item" data-id="'+p.id+'" data-name="'+p.name+'">'
                        +'<div class="obp-product-item-name">'+p.name+'</div></div>';
                });
                $('#obp-trigger-results').html(html).addClass('open');
            });
        }, 200);
    });

    $(document).on('click', '#obp-trigger-results .obp-product-item', function() {
        var id = $(this).data('id'), name = $(this).data('name');
        if ($('#obp-trigger-tags .obp-tag[data-id="'+id+'"]').length) {
            $('#obp-trigger-results').removeClass('open');
            $('#obp-trigger-search').val('');
            return;
        }
        $('#obp-trigger-tags').append(
            '<span class="obp-tag" data-id="'+id+'">'+name
            +'<input type="hidden" name="obp_settings[trigger_products][]" value="'+id+'">'
            +'<button type="button" class="obp-tag-remove">×</button></span>'
        );
        $('#obp-trigger-results').removeClass('open');
        $('#obp-trigger-search').val('');
    });

    $(document).on('click', '.obp-tag-remove', function() { $(this).closest('.obp-tag').remove(); });

    // Close dropdowns on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.obp-add-product-row, #obp-trigger-search').length)
            $('.obp-product-dropdown').removeClass('open');
    });

    /* ═══════════════════════════════════════
       DASHBOARD: STATUS TOGGLE
    ═══════════════════════════════════════ */
    $(document).on('change', '.obp-status-toggle', function() {
        var $t = $(this), id = $t.data('id');
        $t.prop('disabled', true);
        $.post(obpAdmin.ajaxUrl, { action: 'obp_toggle_bump', id: id, nonce: obpAdmin.nonce }, function(res) {
            if (!res.success) { $t.prop('checked', !$t.prop('checked')); alert(obpAdmin.i18n.error || 'Error.'); }
        }).fail(function() {
            $t.prop('checked', !$t.prop('checked'));
            alert(obpAdmin.i18n.error || 'Error. Please try again.');
        }).always(function() {
            $t.prop('disabled', false);
        });
    });

    /* ═══════════════════════════════════════
       DASHBOARD: DELETE
    ═══════════════════════════════════════ */
    $(document).on('click', '.obp-action-delete', function(e) {
        e.preventDefault();
        if (!confirm(obpAdmin.i18n.deleteConfirm || 'Delete this Order Bump?')) return;
        var id = $(this).data('id'), nonce = $(this).data('nonce');
        var $btn = $(this);
        $btn.text('Deleting...').css('pointer-events', 'none');
        $.post(obpAdmin.ajaxUrl, { action: 'obp_delete_bump', id: id, nonce: nonce }, function(res) {
            if (res.success) window.location = obpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=wp-order-bump&deleted=1';
            else { alert(obpAdmin.i18n.errorDeleting || 'Error deleting.'); $btn.text('Delete').css('pointer-events', ''); }
        }).fail(function() {
            alert(obpAdmin.i18n.error || 'Error. Please try again.');
            $btn.text('Delete').css('pointer-events', '');
        });
    });

    /* ═══════════════════════════════════════
       MEDIA LIBRARY — Custom Image Select
    ═══════════════════════════════════════ */
    $('#obp-select-img-btn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
            var url = frame.state().get('selection').first().toJSON().url;
            $('#obp-custom-img-url').val(url);
        });
        frame.open();
    });

    /* ==========================================
       LIVE SYNC: Products tab -> Design tab
    ========================================== */
    function syncDesignTabProducts() {
        var products = [];
        $('#obp-products-list .obp-product-row').each(function(i) {
            var name = $(this).find('.obp-product-row-info strong').text();
            var thumb = $(this).find('.obp-product-thumb').attr('src') || '';
            var id = $(this).data('product-id');
            products.push({ idx: i, name: name, thumb: thumb, id: id });
        });

        var $descCard = $('#obp-design-desc-card .obp-card-body');
        var $switcherWrap = $descCard.find('.obp-desc-switcher-wrap');

        if (products.length === 0) {
            $switcherWrap.hide();
            $descCard.find('.obp-desc-panel').hide();
            $descCard.find('.obp-help').hide();
            if (!$descCard.find('.obp-no-prod-hint').length) {
                $descCard.append('<p class="obp-help obp-no-prod-hint" style="padding:12px 0;text-align:center;">First add products in the <strong>Products tab</strong>.</p>');
            }
            $descCard.find('.obp-no-prod-hint').show();
            return;
        }

        $descCard.find('.obp-no-prod-hint').remove();

        if (!$switcherWrap.length) {
            $descCard.html('');
            $descCard.append('<div class="obp-desc-switcher-wrap" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;padding:10px;background:#F5F3FF;border-radius:8px;border:1px solid #E5E2FF;"></div>');
            $descCard.append('<div class="obp-desc-editors-wrap" style="position:relative;"></div>');
            $descCard.append('<p class="obp-help" style="margin-top:6px;">Use short promotional text. HTML supported.</p>');
            $switcherWrap = $descCard.find('.obp-desc-switcher-wrap');
        }

        $switcherWrap.empty();
        products.forEach(function(p, idx) {
            var shortName = p.name.length > 40 ? p.name.substring(0, 40) + '...' : p.name;
            var activeClass = idx === 0 ? ' obp-switch-active' : '';
            $switcherWrap.append('<button type="button" class="obp-desc-switch-btn' + activeClass + '" data-index="' + idx + '" id="obp-swbtn-' + idx + '">' + shortName + '</button>');
        });
        $switcherWrap.show();

        var $editorsWrap = $descCard.find('.obp-desc-editors-wrap');
        if (!$editorsWrap.length) {
            $switcherWrap.after('<div class="obp-desc-editors-wrap" style="position:relative;"></div>');
            $editorsWrap = $descCard.find('.obp-desc-editors-wrap');
        }

        products.forEach(function(p, idx) {
            var panelId = 'obp-desc-panel-' + idx;
            var editorId = 'obp_prod_editor_' + idx;
            if (!$('#' + panelId).length) {
                var display = idx === 0 ? 'display:block;' : 'display:none;';
                $editorsWrap.append('<div id="' + panelId + '" class="obp-desc-panel" style="' + display + '"><textarea id="' + editorId + '" name="obp_settings[products][' + idx + '][description]" rows="8" style="width:100%;min-height:180px;"></textarea></div>');

                // Initialize TinyMCE on the new textarea
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.initialize(editorId, {
                        tinymce: {
                            wpautop: true,
                            toolbar1: 'formatselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,alignleft,aligncenter,alignright,|,bullist,numlist,|,link,unlink,|,removeformat',
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
                                var panelIdx = editorId.replace('obp_prod_editor_', '');
                                var panel = document.getElementById('obp-desc-panel-' + panelIdx);
                                if (panel && panel.style.display !== 'none') {
                                    obpSyncDescToPreview(editorId);
                                }
                            });
                            editor.isNotDirty = true;
                        }
                    }, 500);
                }
            }
        });

        // Remove extra panels for deleted products
        $descCard.find('.obp-desc-panel').each(function() {
            var panelIdx = parseInt($(this).attr('id').replace('obp-desc-panel-', ''));
            if (panelIdx >= products.length) {
                var editorId = 'obp_prod_editor_' + panelIdx;
                if (typeof wp !== 'undefined' && wp.editor) {
                    wp.editor.remove(editorId);
                }
                $(this).remove();
            }
        });

        $descCard.find('.obp-help').not('.obp-no-prod-hint').show();
    }
    /* ═══════════════════════════════════════
       TINYMCE LIVE PREVIEW
    ═══════════════════════════════════════ */
    function obpSyncDescToPreview(editorId) {
        var descTextEl = document.getElementById('obp-preview-desc-text');
        if (!descTextEl) return;
        var content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
            content = tinyMCE.get(editorId).getContent();
        } else {
            var ta = document.getElementById(editorId);
            if (ta) content = ta.value;
        }
        descTextEl.innerHTML = content || '<em style="color:#9CA3AF">' + (obpAdmin.i18n.noDescription || 'No description set.') + '</em>';
    }

    function obpBindTinyMCELive() {
        if (typeof tinyMCE === 'undefined') {
            setTimeout(obpBindTinyMCELive, 300);
            return;
        }

        tinyMCE.on('AddEditor', function(e) {
            var editor = e.editor;
            var eid = editor.id;
            if (eid.indexOf('obp_prod_editor_') !== 0) return;

            editor.on('keyup change input NodeChange', function() {
                var panelIdx = eid.replace('obp_prod_editor_', '');
                var panel = document.getElementById('obp-desc-panel-' + panelIdx);
                if (!panel) return;
                var style = window.getComputedStyle(panel);
                var isVisible = (style.display !== 'none' && style.opacity !== '0' && panel.offsetWidth > 1);
                if (isVisible) obpSyncDescToPreview(eid);
            });
        });

        if (tinyMCE.editors && tinyMCE.editors.length) {
            tinyMCE.editors.forEach(function(editor) {
                var eid = editor.id;
                if (eid.indexOf('obp_prod_editor_') !== 0) return;
                editor.on('keyup change input NodeChange', function() {
                    var panelIdx = eid.replace('obp_prod_editor_', '');
                    var panel = document.getElementById('obp-desc-panel-' + panelIdx);
                    if (!panel) return;
                    var style = window.getComputedStyle(panel);
                    var isVisible = (style.display !== 'none' && style.opacity !== '0' && panel.offsetWidth > 1);
                    if (isVisible) obpSyncDescToPreview(eid);
                });
            });
        }
    }

    obpBindTinyMCELive();

    // Reset TinyMCE dirty flags after init to prevent false "unsaved changes" popup
    setTimeout(function() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.editors) {
            tinyMCE.editors.forEach(function(editor) {
                if (editor.id.indexOf('obp_prod_editor_') === 0) {
                    editor.isNotDirty = true;
                }
            });
        }
        // Remove WordPress default beforeunload warning on our admin pages
        $(window).off('beforeunload.edit-post');
    }, 1000);

    /* ═══════════════════════════════════════
       NOTICE HELPER
    ═══════════════════════════════════════ */
    function showNotice(msg, type) {
        var cls = type === 'info' ? 'obp-notice-info' : 'obp-notice-success';
        var $n = $('<div class="obp-notice '+cls+'">'+msg+'</div>');
        $('.obp-name-bar').before($n);
        setTimeout(function() { $n.fadeOut(400, function() { $(this).remove(); }); }, 3000);
    }

});
