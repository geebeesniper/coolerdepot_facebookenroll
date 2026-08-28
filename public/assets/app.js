$(function(){
    function detectPlatform(url){
        try{
            const u = new URL((url || '').trim());
            const h = u.hostname.toLowerCase().replace(/^www\./,'');

            if(h === 'facebook.com' || h.endsWith('.facebook.com')) return 'facebook';
            if(h === 'offerup.com' || h.endsWith('.offerup.com') || h === 'offerup.co') return 'offerup';
            if(h === 'craigslist.org' || h.endsWith('.craigslist.org')) return 'craigslist';
        }catch(e){}
        return '';
    }

    function normalizePostUrl(url, platform){
        const raw = (url || '').trim();

        if(platform === 'facebook'){
            const m = raw.match(/https?:\/\/(?:[a-z0-9-]+\.)?facebook\.com\/marketplace\/item\/(\d+)/i);
            if(m){
                return 'https://www.facebook.com/marketplace/item/' + m[1];
            }
        }

        if(platform === 'offerup'){
            const m = raw.match(/https?:\/\/(?:www\.)?offerup\.com\/item\/detail\/([a-z0-9-]+)/i);
            if(m){
                return 'https://offerup.com/item/detail/' + m[1];
            }
        }

        if(platform === 'craigslist'){
            const m = raw.match(/https?:\/\/(?:[a-z0-9-]+\.)?craigslist\.org\/[^\s]*?\/\d{8,}\.html/i);
            if(m){
                return m[0];
            }
        }

        return raw;
    }

    function platformLabel(platform){
        if(platform === 'facebook') return 'Facebook';
        if(platform === 'offerup') return 'OfferUp';
        if(platform === 'craigslist') return 'Craigslist';
        return '';
    }

    function updateDetectedPlatform(){
        const originalUrl = $('#postUrl').val() || '';
        const platform = detectPlatform(originalUrl);
        const url = platform ? normalizePostUrl(originalUrl, platform) : originalUrl;
        const $label = $('#detectedPlatform');

        if(platform && url && url !== originalUrl.trim()){
            $('#postUrl').val(url);
        }

        $('#detectedPlatformValue').val(platform);
        $('#inspectButton').prop('disabled', !platform);

        $label
            .removeClass('facebook offerup craigslist empty-platform')
            .addClass(platform || 'empty-platform')
            .text(platform ? platformLabel(platform) : (url.trim() ? 'Unsupported URL' : 'Paste a supported URL'));

        return platform;
    }

    $('#postUrl').on('input paste change', function(){
        setTimeout(updateDetectedPlatform, 0);
    });

    $('#inspectForm').on('submit',function(e){
        e.preventDefault();

        const platform = updateDetectedPlatform();
        if(!platform){
            alert('Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.');
            return;
        }

        const $b=$('#inspectButton'),
              $p=$('#inspectionProgress'),
              $r=$('#inspectionResult');

        $b.prop('disabled',true).text('Checking...');
        $p.removeClass('hidden').find('div').removeClass().addClass('active');
        $('#inspectionEmpty').addClass('hidden');
        $r.addClass('hidden');
        $('#saveButton').prop('disabled',true);

        $.post(window.CD_BASE_PATH+'/api/inspect',$(this).serialize())
            .done(function(d){
                $('#resultPlatform').text(platformLabel(d.platform) || d.platform || '—');
                $('#resultTitle').text(d.title||'—');
                $('#resultDate').text(d.published_at||'—');
                $('#resultExternalId').text(d.external_post_id||'—');
                $('#resultDescription').text(d.description||'—');

                const u=d.canonical_url||d.resolved_url||'—';
                $('#resultCanonical').html(
                    u==='—'
                    ? '—'
                    : '<a target="_blank" rel="noopener" href="'+$('<div>').text(u).html()+'">'+$('<div>').text(u).html()+'</a>'
                );

                $('#inspectionToken').val(d.inspection_token||'');
                $('#verificationBanner')
                    .attr('class','banner '+(d.ok?'ok':'bad'))
                    .text(d.ok?'VERIFIED ✓':'BLOCKED — '+(d.message||'Verification failed'));

                $r.removeClass('hidden');
                $('#saveButton').prop('disabled',!d.ok);
                $p.find('div').attr('class',d.ok?'done':'failed');

                if(!d.ok) alert(d.message||'Post could not be verified.');
            })
            .fail(function(x){
                alert((x.responseJSON&&x.responseJSON.message)||'Inspection failed.');
            })
            .always(function(){
                $b.prop('disabled',!updateDetectedPlatform()).text('Check Post');
            });
    });

    const savedView=localStorage.getItem('cdsp-sales-post-view')||'grid';

    function setPostView(v){
        $('[data-view]').removeClass('active');
        $('[data-view="'+v+'"]').addClass('active');
        $('#postCollection')
            .toggleClass('post-grid',v==='grid')
            .toggleClass('post-list',v==='list');
    }

    setPostView(savedView);

    $('[data-view]').on('click',function(){
        const v=$(this).data('view');
        localStorage.setItem('cdsp-sales-post-view',v);
        setPostView(v);
    });

    updateDetectedPlatform();


    function loadMoreDailyPosts(){
        const $wrap = $('#dailyPosts');
        const $btn = $('#loadMoreDailyPosts');

        if(!$wrap.length || !$btn.length || $btn.prop('disabled')){
            return;
        }

        const from = $wrap.data('from');
        const to = $wrap.data('to');
        const offset = parseInt($wrap.attr('data-offset') || '0', 10);
        const limit = parseInt($wrap.data('limit') || '3', 10);

        $btn.prop('disabled', true).text('Loading...');
        $('#dailyLoadStatus').text('Loading earlier days...');

        $.get(window.CD_BASE_PATH + '/sales/daily-posts', {
            from: from,
            to: to,
            offset: offset,
            limit: limit
        })
        .done(function(d){
            if(!d || !d.ok){
                $('#dailyLoadStatus').text((d && d.message) || 'Could not load earlier days.');
                return;
            }

            if(d.html){
                $wrap.append(d.html);
            }

            $wrap.attr('data-offset', d.next_offset || offset);

            if(d.has_more){
                $btn.prop('disabled', false).text('Load earlier days');
                $('#dailyLoadStatus').text('');
            }else{
                $btn.prop('disabled', true).hide();
                $('#dailyLoadStatus').text('All days loaded.');
            }
        })
        .fail(function(){
            $btn.prop('disabled', false).text('Load earlier days');
            $('#dailyLoadStatus').text('Could not load earlier days.');
        });
    }

    $('#loadMoreDailyPosts').on('click', loadMoreDailyPosts);

    // Progressive loading: when the button approaches the viewport, fetch the next date batch.
    if($('#loadMoreDailyPosts').length && 'IntersectionObserver' in window){
        const observer = new IntersectionObserver(function(entries){
            if(entries.some(function(entry){ return entry.isIntersecting; })){
                loadMoreDailyPosts();
            }
        }, {rootMargin:'240px 0px'});

        observer.observe(document.getElementById('loadMoreDailyPosts'));
    }



    function syncHtmlNote($root){
        const $editor = $root.find('[data-html-editor]');
        const $source = $root.find('[data-html-source]');

        if(!$editor.hasClass('hidden')){
            $source.val($editor.html());
        }
    }

    $('[data-html-note]').each(function(){
        const $root = $(this);
        const $editor = $root.find('[data-html-editor]');
        const $source = $root.find('[data-html-source]');
        const $toolbar = $root.find('[data-html-toolbar]');
        const $toggle = $root.find('[data-html-editor-toggle]');

        $toggle.on('click', function(){
            const editorOn = !$editor.hasClass('hidden');

            if(editorOn){
                $source.val($editor.html());
                $editor.addClass('hidden');
                $toolbar.addClass('hidden');
                $source.removeClass('hidden');
                $toggle.text('Editor On');
            }else{
                $editor.html($source.val());
                $source.addClass('hidden');
                $editor.removeClass('hidden');
                $toolbar.removeClass('hidden');
                $toggle.text('Editor Off');
                $editor.trigger('focus');
            }
        });

        $toolbar.on('click', '[data-cmd]', function(){
            const cmd = $(this).data('cmd');
            const value = $(this).data('value') || null;

            $editor.trigger('focus');

            if(cmd === 'createLink'){
                const href = window.prompt('Link URL');
                if(href){
                    document.execCommand('createLink', false, href);
                }
                return;
            }

            document.execCommand(cmd, false, value);
        });

        $editor.on('input blur', function(){
            $source.val($editor.html());
        });

        $root.closest('form').on('submit', function(){
            syncHtmlNote($root);
        });
    });


    // v0.1.12 Provider Manager
    (function(){
        const $composer = $('#providerComposer');
        const $form = $('#providerDraftForm');

        if(!$composer.length || !$form.length){
            return;
        }

        $form.on('submit', function(e){
            e.preventDefault();
        });

        const defaults = {
            brightdata: {
                name: 'Bright Data',
                website: 'https://brightdata.com/'
            },
            apify: {
                name: 'Apify',
                website: 'https://apify.com/'
            },
            scrapecreators: {
                name: 'ScrapeCreators',
                website: 'https://scrapecreators.com/'
            },
            generic_json: {
                name: 'Custom API',
                website: ''
            }
        };

        function pageNotice(message, ok){
            const $n = $('#providerPageNotice');
            $n
                .removeClass('hidden ok bad')
                .addClass(ok ? 'ok' : 'bad')
                .text(message);
        }

        function invalidateProviderTest(){
            $('#providerTestTicket').val('');
            $('#providerAddButton').prop('disabled', true);
            $('#providerDraftResult')
                .addClass('hidden')
                .removeClass('ok bad')
                .empty();
        }

        function syncProviderType(){
            const type = $('#providerType').val();
            const d = defaults[type] || defaults.generic_json;

            $('[data-provider-settings]')
                .addClass('hidden')
                .find(':input')
                .prop('disabled', true);

            $('[data-provider-settings="'+type+'"]')
                .removeClass('hidden')
                .find(':input')
                .prop('disabled', false);

            $('.provider-custom-only')
                .toggleClass('hidden', type !== 'generic_json')
                .find(':input')
                .prop('disabled', type !== 'generic_json');

            if(!$('#providerName').data('user-edited')){
                $('#providerName').val(d.name);
            }

            if(!$('#providerWebsite').data('user-edited')){
                $('#providerWebsite').val(d.website);
            }

            invalidateProviderTest();
        }

        $('#providerAddOpen').on('click', function(){
            $composer.removeClass('hidden');
            $composer.get(0).scrollIntoView({behavior:'smooth', block:'start'});
        });

        $('#providerAddClose').on('click', function(){
            $composer.addClass('hidden');
        });

        $('#providerType').on('change', function(){
            $('#providerName').data('user-edited', false);
            $('#providerWebsite').data('user-edited', false);
            syncProviderType();
        });

        $('#providerName, #providerWebsite').on('input', function(){
            $(this).data('user-edited', true);
        });

        $('#providerAuthMode').on('change', function(){
            const v = $(this).val();
            $('#providerAuthNameWrap').toggleClass(
                'hidden',
                !(v === 'header' || v === 'query')
            );
        }).trigger('change');

        $form.on('input change', 'input,select,textarea', function(e){
            if(e.target.id !== 'providerTestTicket'){
                invalidateProviderTest();
            }
        });

        $('#providerTestButton').on('click', function(){
            const $button = $(this);
            const $result = $('#providerDraftResult');

            $button.prop('disabled', true).text('Testing...');
            $('#providerAddButton').prop('disabled', true);

            $.ajax({
                url: $button.data('test-url'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(d){
                if(!d || !d.ok){
                    $result
                        .removeClass('hidden ok')
                        .addClass('bad')
                        .text((d && d.message) || 'Provider test failed.');
                    return;
                }

                $('#providerTestTicket').val(d.ticket || '');
                $('#providerAddButton').prop('disabled', !d.ticket);

                const r = d.result || {};
                const esc = function(v){
                    return $('<div>').text(v == null ? '—' : String(v)).html();
                };

                $result
                    .removeClass('hidden bad')
                    .addClass('ok')
                    .html(
                        '<strong>'+esc(d.message || 'Test passed.')+'</strong>'+
                        '<dl>'+
                            '<dt>Provider</dt><dd>'+esc(r.provider)+'</dd>'+
                            '<dt>Item ID</dt><dd>'+esc(r.item_id)+'</dd>'+
                            '<dt>Title</dt><dd>'+esc(r.title)+'</dd>'+
                            '<dt>Listing date</dt><dd>'+esc(r.listing_date)+'</dd>'+
                            '<dt>Description</dt><dd>'+esc(r.description)+'</dd>'+
                        '</dl>'
                    );
            })
            .fail(function(x){
                const message =
                    (x.responseJSON && x.responseJSON.message)
                    || 'Provider test failed.';

                $result
                    .removeClass('hidden ok')
                    .addClass('bad')
                    .text(message);
            })
            .always(function(){
                $button.prop('disabled', false).text('Test Provider');
            });
        });

        $('#providerAddButton').on('click', function(){
            const $button = $(this);

            if(!$('#providerTestTicket').val()){
                return;
            }

            $button.prop('disabled', true).text('Adding...');

            $.ajax({
                url: $button.data('add-url'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(d){
                if(d && d.ok){
                    window.location.reload();
                    return;
                }
                pageNotice((d && d.message) || 'Could not add provider.', false);
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not add provider.',
                    false
                );
            })
            .always(function(){
                $button.text('Add Provider');
                if($('#providerTestTicket').val()){
                    $button.prop('disabled', false);
                }
            });
        });

        $(document).on('change', '.provider-toggle', function(){
            const $toggle = $(this);
            const $card = $toggle.closest('.provider-card');
            const enabled = $toggle.is(':checked');

            $toggle.prop('disabled', true);

            $.post($toggle.data('toggle-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                id: $card.data('provider-id'),
                enabled: enabled ? '1' : '0'
            })
            .done(function(d){
                if(!d || !d.ok){
                    $toggle.prop('checked', !enabled);
                    pageNotice((d && d.message) || 'Could not update provider.', false);
                    return;
                }

                $card
                    .toggleClass('is-enabled', enabled)
                    .toggleClass('is-disabled', !enabled);

                $toggle.next('span').text(enabled ? 'Enabled' : 'Disabled');
                pageNotice(d.message, true);
            })
            .fail(function(x){
                $toggle.prop('checked', !enabled);
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not update provider.',
                    false
                );
            })
            .always(function(){
                $toggle.prop('disabled', false);
            });
        });

        $(document).on('click', '.provider-delete', function(){
            const $button = $(this);
            const $card = $button.closest('.provider-card');

            if(!$button.hasClass('confirming')){
                $button
                    .addClass('confirming')
                    .text('Remove?');
                setTimeout(function(){
                    $button.removeClass('confirming').text('Remove');
                }, 3500);
                return;
            }

            $button.prop('disabled', true).text('Removing...');

            $.post($button.data('delete-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                id: $card.data('provider-id')
            })
            .done(function(d){
                if(d && d.ok){
                    $card.remove();
                    refreshPriorityNumbers();
                    pageNotice(d.message, true);
                    return;
                }

                pageNotice((d && d.message) || 'Could not remove provider.', false);
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not remove provider.',
                    false
                );
            })
            .always(function(){
                $button.prop('disabled', false).removeClass('confirming').text('Remove');
            });
        });

        function refreshPriorityNumbers(){
            $('#providerSortable .provider-card').each(function(index){
                $(this).find('[data-provider-priority]').text(index + 1);
            });
        }

        function saveProviderOrder(){
            const ids = $('#providerSortable .provider-card').map(function(){
                return $(this).data('provider-id');
            }).get();

            if(!ids.length){
                return;
            }

            $.post($('#providerSortable').data('reorder-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                ids: JSON.stringify(ids)
            })
            .done(function(d){
                if(d && d.ok){
                    pageNotice(d.message, true);
                }else{
                    pageNotice((d && d.message) || 'Could not save provider order.', false);
                }
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not save provider order.',
                    false
                );
            });
        }

        let dragging = null;

        $(document).on('dragstart', '.provider-drag', function(e){
            dragging = $(this).closest('.provider-card').get(0);

            if(!dragging){
                return;
            }

            $(dragging).addClass('dragging');

            if(e.originalEvent && e.originalEvent.dataTransfer){
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData(
                    'text/plain',
                    String($(dragging).data('provider-id'))
                );
            }
        });

        $(document).on('dragover', '.provider-card', function(e){
            e.preventDefault();

            if(!dragging || dragging === this){
                return;
            }

            $('.provider-card').removeClass('drag-over');
            $(this).addClass('drag-over');

            const targetRect = this.getBoundingClientRect();
            const before = e.originalEvent.clientY < targetRect.top + targetRect.height / 2;

            if(before){
                this.parentNode.insertBefore(dragging, this);
            }else{
                this.parentNode.insertBefore(dragging, this.nextSibling);
            }

            refreshPriorityNumbers();
        });

        $(document).on('drop', '.provider-card', function(e){
            e.preventDefault();
        });

        $(document).on('dragend', '.provider-drag', function(){
            if(dragging){
                $(dragging).removeClass('dragging');
            }

            $('.provider-card').removeClass('drag-over');
            dragging = null;
            refreshPriorityNumbers();
            saveProviderOrder();
        });

        syncProviderType();
    })();

});
