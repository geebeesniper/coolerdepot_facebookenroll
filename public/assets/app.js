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

function normalizeEditorBlock(value){
    value = String(value || 'p').toLowerCase();

    return ['p','h3','h4','blockquote'].includes(value)
        ? value
        : 'p';
}

$('[data-html-note]').each(function(){
    const $root = $(this);
    const $editor = $root.find('[data-html-editor]');
    const $source = $root.find('[data-html-source]');
    const $toolbar = $root.find('[data-html-toolbar]');
    const $tabs = $root.find('[data-note-mode]');
    const $format = $root.find('[data-note-format]');
    const $status = $root.find('[data-note-status]');
    const $linkbar = $root.find('[data-note-linkbar]');
    const $linkInput = $root.find('[data-note-link-input]');
    const $linkNewTab = $root.find('[data-note-link-newtab]');

    let mode = 'visual';
    let savedRange = null;

    function updateMode(nextMode){
        mode = nextMode === 'html' ? 'html' : 'visual';

        if(mode === 'html'){
            syncHtmlNote($root);
            $editor.addClass('hidden');
            $toolbar.addClass('hidden');
            $linkbar.addClass('hidden').attr('aria-hidden', 'true');
            $source.removeClass('hidden');
            $status.text('HTML source');
        }else{
            $editor.html($source.val());
            $source.addClass('hidden');
            $editor.removeClass('hidden');
            $toolbar.removeClass('hidden');
            $status.text('Visual editor');
        }

        $tabs.each(function(){
            const active = $(this).data('note-mode') === mode;

            $(this)
                .toggleClass('active', active)
                .attr('aria-selected', active ? 'true' : 'false');
        });

        if(mode === 'visual'){
            setTimeout(function(){
                $editor.trigger('focus');
            }, 0);
        }else{
            setTimeout(function(){
                $source.trigger('focus');
            }, 0);
        }
    }

    function rememberSelection(){
        const selection = window.getSelection();

        if(!selection || !selection.rangeCount){
            return;
        }

        const range = selection.getRangeAt(0);
        const node = range.commonAncestorContainer;
        const editorNode = $editor.get(0);

        if(!editorNode){
            return;
        }

        if(
            node === editorNode
            || $.contains(editorNode, node.nodeType === 1 ? node : node.parentNode)
        ){
            savedRange = range.cloneRange();
        }
    }

    function restoreSelection(){
        if(!savedRange){
            $editor.trigger('focus');
            return;
        }

        const selection = window.getSelection();

        if(!selection){
            return;
        }

        selection.removeAllRanges();
        selection.addRange(savedRange);
    }

    function runCommand(command, value){
        $editor.trigger('focus');
        restoreSelection();

        document.execCommand(command, false, value || null);

        rememberSelection();
        $source.val($editor.html());
    }

    function findSelectedLink(){
        const selection = window.getSelection();

        if(!selection || !selection.rangeCount){
            return null;
        }

        let node = selection.anchorNode;

        if(node && node.nodeType === 3){
            node = node.parentNode;
        }

        while(node && node !== $editor.get(0)){
            if(
                node.nodeType === 1
                && String(node.tagName).toLowerCase() === 'a'
            ){
                return node;
            }

            node = node.parentNode;
        }

        return null;
    }

    function openLinkbar(){
        rememberSelection();

        const link = findSelectedLink();

        if(link){
            $linkInput.val(link.getAttribute('href') || '');
            $linkNewTab.prop(
                'checked',
                link.getAttribute('target') === '_blank'
            );
        }else{
            $linkInput.val('');
            $linkNewTab.prop('checked', false);
        }

        $linkbar
            .removeClass('hidden')
            .attr('aria-hidden', 'false');

        setTimeout(function(){
            $linkInput.trigger('focus');
        }, 0);
    }

    function closeLinkbar(){
        $linkbar
            .addClass('hidden')
            .attr('aria-hidden', 'true');

        $linkInput.val('');
        $linkNewTab.prop('checked', false);
    }

    $tabs.on('click', function(){
        updateMode($(this).data('note-mode'));
    });

    $format.on('change', function(){
        const block = normalizeEditorBlock($(this).val());
        runCommand('formatBlock', '<' + block + '>');
    });

    $toolbar.on('mousedown', '[data-cmd],[data-note-link]', function(){
        rememberSelection();
    });

    $toolbar.on('click', '[data-cmd]', function(){
        const command = String($(this).data('cmd') || '');
        let value = $(this).data('value') || null;

        if(command === 'formatBlock' && value){
            value = '<' + normalizeEditorBlock(value) + '>';
        }

        runCommand(command, value);
    });

    $toolbar.on('click', '[data-note-link]', function(){
        openLinkbar();
    });

    $root.on('click', '[data-note-link-cancel]', function(){
        closeLinkbar();
        restoreSelection();
    });

    $root.on('click', '[data-note-link-apply]', function(){
        const href = String($linkInput.val() || '').trim();

        if(!href){
            $linkInput.addClass('field-error').trigger('focus');
            return;
        }

        $linkInput.removeClass('field-error');

        restoreSelection();

        const existingLink = findSelectedLink();

        if(existingLink){
            existingLink.setAttribute('href', href);

            if($linkNewTab.is(':checked')){
                existingLink.setAttribute('target', '_blank');
                existingLink.setAttribute(
                    'rel',
                    'noopener noreferrer'
                );
            }else{
                existingLink.removeAttribute('target');
            }
        }else{
            document.execCommand('createLink', false, href);

            const newLink = findSelectedLink();

            if(newLink && $linkNewTab.is(':checked')){
                newLink.setAttribute('target', '_blank');
                newLink.setAttribute(
                    'rel',
                    'noopener noreferrer'
                );
            }
        }

        $source.val($editor.html());
        closeLinkbar();
        $editor.trigger('focus');
    });

    $linkInput.on('input', function(){
        $(this).removeClass('field-error');
    });

    $linkInput.on('keydown', function(event){
        if(event.key === 'Enter'){
            event.preventDefault();
            $root.find('[data-note-link-apply]').trigger('click');
        }

        if(event.key === 'Escape'){
            event.preventDefault();
            closeLinkbar();
            restoreSelection();
        }
    });

    $editor.on('keyup mouseup input blur', function(){
        rememberSelection();
        $source.val($editor.html());
    });

    $source.on('input', function(){
        if(mode === 'html'){
            $status.text('HTML source · modified');
        }
    });

    $root.closest('form').on('submit', function(){
        if(mode === 'html'){
            $editor.html($source.val());
        }else{
            syncHtmlNote($root);
        }
    });

    updateMode('visual');
});


    // v0.1.13 Live Provider Jobs
    (function(){
        const $monitor = $('#providerJobsMonitor');
        const $body = $('#providerJobsBody');
        const $live = $('#providerJobsLive');
        const $liveText = $('#providerJobsLiveText');

        if(!$monitor.length || !$body.length){
            return;
        }

        let timer = null;
        let request = null;
        let lastSignature = '';

        function esc(value){
            return $('<div>').text(
                value == null || value === '' ? '—' : String(value)
            ).html();
        }

        function safeStatus(value){
            const status = String(value || '').toLowerCase();
            return ['starting','running','ready','failed'].includes(status)
                ? status
                : 'starting';
        }

        function statusLabel(status){
            if(status === 'ready') return 'Ready';
            if(status === 'failed') return 'Failed';
            if(status === 'running') return 'Running';
            return 'Starting';
        }

        function renderJobs(jobs){
            jobs = Array.isArray(jobs) ? jobs : [];

            const signature = JSON.stringify(jobs.map(function(job){
                return [
                    job.id,
                    job.updated_at,
                    job.status,
                    job.http,
                    job.error
                ];
            }));

            if(signature === lastSignature){
                return;
            }

            lastSignature = signature;

            if(!jobs.length){
                $body.html(
                    '<tr class="provider-jobs-empty">'+
                        '<td colspan="7">No provider jobs yet.</td>'+
                    '</tr>'
                );
                return;
            }

            const html = jobs.map(function(job){
                const status = safeStatus(job.status);

                return '<tr data-job-id="'+esc(job.id)+'">'+
                    '<td>'+esc(job.created_at)+'</td>'+
                    '<td>'+esc(job.user)+'</td>'+
                    '<td>'+esc(job.provider)+'</td>'+
                    '<td>'+esc(job.item)+'</td>'+
                    '<td><span class="provider-job '+status+'">'+
                        statusLabel(status)+
                    '</span></td>'+
                    '<td>'+esc(job.http)+'</td>'+
                    '<td class="job-error">'+esc(job.error)+'</td>'+
                '</tr>';
            }).join('');

            $body.html(html);
        }

        function setLiveState(state){
            $live
                .removeClass('is-live is-paused is-error')
                .addClass(state);

            if(state === 'is-error'){
                $liveText.text('Reconnect');
            }else if(state === 'is-paused'){
                $liveText.text('Paused');
            }else{
                $liveText.text('Live');
            }
        }

        function refreshProviderJobs(){
            if(document.hidden){
                setLiveState('is-paused');
                return;
            }

            if(request && request.readyState !== 4){
                return;
            }

            request = $.ajax({
                url: $monitor.data('jobs-url'),
                method: 'GET',
                dataType: 'json',
                cache: false
            })
            .done(function(d){
                if(d && d.ok){
                    renderJobs(d.jobs);
                    setLiveState('is-live');
                }else{
                    setLiveState('is-error');
                }
            })
            .fail(function(){
                setLiveState('is-error');
            });
        }

        function startProviderJobsPolling(){
            if(timer){
                clearInterval(timer);
            }

            refreshProviderJobs();
            timer = setInterval(refreshProviderJobs, 2000);
        }

        window.refreshProviderJobs = refreshProviderJobs;

        document.addEventListener('visibilitychange', function(){
            if(document.hidden){
                setLiveState('is-paused');
                return;
            }

            refreshProviderJobs();
        });

        startProviderJobsPolling();
    })();

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

        function clearProviderFieldError(target){
            const $field = typeof target === 'string'
                ? $form.find('[name="'+target+'"]:enabled').first()
                : $(target);

            if(!$field.length){
                return;
            }

            const $wrap = $field.closest('label');
            $field.removeAttr('aria-invalid');
            $wrap.removeClass('provider-field-has-error');
            $wrap.children('.provider-field-error').remove();
        }

        function clearAllProviderFieldErrors(){
            $form
                .find('.provider-field-has-error')
                .removeClass('provider-field-has-error')
                .children('.provider-field-error')
                .remove();

            $form.find('[aria-invalid="true"]').removeAttr('aria-invalid');
        }

        function showProviderFieldError(field, message){
            const $field = $form.find('[name="'+field+'"]:enabled').first();

            if(!$field.length){
                return false;
            }

            clearProviderFieldError($field);

            const $wrap = $field.closest('label');
            $field.attr('aria-invalid', 'true');
            $wrap.addClass('provider-field-has-error');

            $('<small class="provider-field-error" role="alert"></small>')
                .text(message)
                .appendTo($wrap);

            const el = $field.get(0);
            if(el){
                el.scrollIntoView({
                    behavior:'smooth',
                    block:'center'
                });

                setTimeout(function(){
                    try{
                        el.focus({preventScroll:true});
                    }catch(e){
                        el.focus();
                    }
                }, 250);
            }

            return true;
        }

        function validateProviderTestUrl(){
            const value = String($('#providerTestUrl').val() || '').trim();
            const match = value.match(
                /^https?:\/\/(?:[a-z0-9-]+\.)?facebook\.com\/marketplace\/item\/(\d+)(?:[/?#].*)?$/i
            );

            if(!match){
                showProviderFieldError(
                    'test_url',
                    'Enter a complete Facebook Marketplace item URL with its numeric Item ID.'
                );
                return false;
            }

            clearProviderFieldError('test_url');
            return true;
        }

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
                clearProviderFieldError(e.target);
                invalidateProviderTest();
            }
        });

        $('#providerTestButton').on('click', function(){
            const $button = $(this);
            const $result = $('#providerDraftResult');

            clearAllProviderFieldErrors();

            if(!validateProviderTestUrl()){
                $result
                    .addClass('hidden')
                    .removeClass('ok bad')
                    .empty();
                return;
            }

            $button.prop('disabled', true).text('Testing...');
            $('#providerAddButton').prop('disabled', true);

            if(typeof window.refreshProviderJobs === 'function'){
                window.refreshProviderJobs();
                setTimeout(window.refreshProviderJobs, 250);
            }

            $.ajax({
                url: $button.data('test-url'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(d){
                if(!d || !d.ok){
                    const message =
                        (d && d.message) || 'Provider test failed.';

                    if(d && d.field && showProviderFieldError(d.field, message)){
                        $result
                            .addClass('hidden')
                            .removeClass('ok bad')
                            .empty();
                    }else{
                        $result
                            .removeClass('hidden ok')
                            .addClass('bad')
                            .text(message);
                    }
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
                const data = x.responseJSON || {};
                const message =
                    data.message || 'Provider test failed.';

                if(data.field && showProviderFieldError(data.field, message)){
                    $result
                        .addClass('hidden')
                        .removeClass('ok bad')
                        .empty();
                }else{
                    $result
                        .removeClass('hidden ok')
                        .addClass('bad')
                        .text(message);
                }
            })
            .always(function(){
                $button.prop('disabled', false).text('Test Provider');

                if(typeof window.refreshProviderJobs === 'function'){
                    window.refreshProviderJobs();
                    setTimeout(window.refreshProviderJobs, 500);
                }
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


// v0.1.18 Dynamic Sales progress grid
(function(){
    const $grid = $('#salesProgressGrid');
    const $live = $('#adminDashboardLive');

    if(!$grid.length || !$live.length){
        return;
    }

    const targetUrl = $grid.data('target-url');
    const progressUrl = $live.data('progress-url');
    const updatesUrl = $live.data('updates-url');
    const salesPostsUrl = $live.data('sales-posts-url');
    const csrf = $('#adminDashboardCsrf').val();
    const date = String($live.data('date') || '');

    let currentPeriod = String(
        $live.attr('data-period') || 'day'
    );
    let currentPeriodDays = parseInt(
        $live.attr('data-period-days'),
        10
    ) || 1;
    let baselineCount = parseInt(
        $live.attr('data-post-count'),
        10
    ) || 0;
    let baselineMaxId = parseInt(
        $live.attr('data-max-post-id'),
        10
    ) || 0;

    let periodRequest = null;
    let activityRequest = null;
    let activityTimer = null;
    let noticeShown = false;
    let expandedSalesId = 0;
    let expandedRequest = null;

    const $notice = $('#dashboardRefreshNotice');
    const $expanded = $('#salesExpandedPosts');
    const $expandedTitle = $('#salesExpandedTitle');
    const $expandedSubtitle = $('#salesExpandedSubtitle');
    const $expandedList = $('#salesExpandedList');
    const $expandedLoading = $('#salesExpandedLoading');
    const $noticeTitle = $('#dashboardRefreshTitle');
    const $noticeText = $('#dashboardRefreshText');

    function setTargetMessage($card, message, error){
        $card
            .find('[data-target-message]')
            .toggleClass('error', !!error)
            .text(message || '');
    }

    function animateNumber($element, from, to){
        from = parseInt(from, 10) || 0;
        to = parseInt(to, 10) || 0;

        if(from === to){
            $element.text(to);
            return;
        }

        const start = performance.now();
        const duration = 360;

        function frame(now){
            const raw = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - raw, 3);
            const value = Math.round(from + (to - from) * eased);

            $element.text(value);

            if(raw < 1){
                requestAnimationFrame(frame);
            }
        }

        requestAnimationFrame(frame);
    }

    function periodName(period){
        if(period === 'week') return 'Weekly';
        if(period === 'month') return 'Monthly';
        return 'Daily';
    }

    function updateUrlPeriod(period){
        if(!window.history || !window.history.replaceState){
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('period', period);
        window.history.replaceState({}, '', url.toString());
    }

    function updatePeriodButtons(period){
        $('#dashboardPeriodSwitch [data-period]').each(function(){
            const active = $(this).data('period') === period;
            $(this)
                .toggleClass('active', active)
                .attr('aria-pressed', active ? 'true' : 'false');
        });

        $('#dashboardPeriodFormValue').val(period);
    }

    function updateCard($card, row, days, period){
        const oldCount = parseInt(
            $card.attr('data-post-count'),
            10
        ) || 0;
        const count = parseInt(row.post_count, 10) || 0;
        const dailyTarget = Math.max(
            1,
            parseInt(row.daily_target, 10) || 10
        );
        const periodTarget = Math.max(
            1,
            parseInt(row.period_target, 10)
            || dailyTarget * Math.max(1, days)
        );
        const percent = Math.min(
            100,
            parseInt(row.percent, 10) || 0
        );
        const met = !!row.target_met;

        $card
            .attr('data-post-count', count)
            .attr('data-daily-target', dailyTarget)
            .toggleClass('target-met', met);

        animateNumber(
            $card.find('[data-progress-count]'),
            oldCount,
            count
        );

        $card.find('[data-progress-target]').text(periodTarget);
        $card.find('[data-daily-target-label]').text(dailyTarget);
        $card.find('[data-period-days]').text(days);
        $card.find('[data-target-input]').val(dailyTarget);

        $card
            .find('[data-good-count]')
            .text(parseInt(row.good_count, 10) || 0);

        $card
            .find('[data-bad-count]')
            .text(parseInt(row.bad_count, 10) || 0);

        $card
            .find('[data-unreviewed-count]')
            .text(parseInt(row.unreviewed_count, 10) || 0);

        $card
            .find('[data-progress-fill]')
            .css('width', percent + '%');

        $card
            .find('.sales-progress-track')
            .attr('aria-valuemax', periodTarget)
            .attr('aria-valuenow', count);

        $card
            .find('[data-target-badge]')
            .toggleClass('hidden', !met);

        $card
            .removeClass('period-updated');

        void $card.get(0).offsetWidth;

        $card.addClass('period-updated');

        setTimeout(function(){
            $card.removeClass('period-updated');
        }, 800);
    }

    function applyProgress(data){
        currentPeriod = data.period || 'day';
        currentPeriodDays = parseInt(data.days, 10) || 1;
        baselineCount = parseInt(data.post_count, 10) || 0;
        baselineMaxId = parseInt(data.max_post_id, 10) || 0;
        noticeShown = false;
        $notice.addClass('hidden');

        $live
            .attr('data-period', currentPeriod)
            .attr('data-period-days', currentPeriodDays)
            .attr('data-post-count', baselineCount)
            .attr('data-max-post-id', baselineMaxId);

        updatePeriodButtons(currentPeriod);
        updateUrlPeriod(currentPeriod);

        $('#dashboardPeriodLabel').text(
            data.period_label || ''
        );
        $('#dashboardProgressTitle').text(
            periodName(currentPeriod) + ' Posting Progress'
        );
        $('#dashboardProgressSubtitle').text(
            'Daily target × '
            + currentPeriodDays
            + ' = '
            + (data.period_short_label || 'period target')
            + '.'
        );
        $('#dashboardPostCount').text(baselineCount);

        const rows = Array.isArray(data.rows) ? data.rows : [];
        const byId = {};

        rows.forEach(function(row){
            byId[String(row.sales_user_id)] = row;
        });

        $grid.find('.sales-progress-card').each(function(){
            const $card = $(this);
            const id = String($card.data('sales-id'));
            const row = byId[id];

            if(row){
                updateCard(
                    $card,
                    row,
                    currentPeriodDays,
                    currentPeriod
                );
            }
        });
    }

    function loadPeriod(period){
        closeExpandedPosts();

        if(periodRequest && periodRequest.readyState !== 4){
            periodRequest.abort();
        }

        $('#dashboardPeriodSwitch [data-period]')
            .prop('disabled', true);

        $grid.addClass('period-loading');

        periodRequest = $.ajax({
            url: progressUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                date: date,
                period: period,
                _: Date.now()
            }
        })
        .done(function(data){
            if(!data || !data.ok){
                return;
            }

            applyProgress(data);
        })
        .always(function(){
            $grid.removeClass('period-loading');
            $('#dashboardPeriodSwitch [data-period]')
                .prop('disabled', false);
        });
    }

    $('#dashboardPeriodSwitch').on(
        'click',
        '[data-period]',
        function(){
            const period = String($(this).data('period') || 'day');

            if(period === currentPeriod){
                return;
            }

            loadPeriod(period);
        }
    );

    function redrawAfterDailyTargetSave($card, dailyTarget){
        const count = parseInt(
            $card.attr('data-post-count'),
            10
        ) || 0;
        const periodTarget = dailyTarget * currentPeriodDays;
        const percent = Math.min(
            100,
            Math.round((count / periodTarget) * 100)
        );
        const met = count >= periodTarget;

        $card
            .attr('data-daily-target', dailyTarget)
            .toggleClass('target-met', met);

        $card.find('[data-daily-target-label]').text(dailyTarget);
        $card.find('[data-progress-target]').text(periodTarget);
        $card.find('[data-progress-fill]').css(
            'width',
            percent + '%'
        );
        $card
            .find('.sales-progress-track')
            .attr('aria-valuemax', periodTarget)
            .attr('aria-valuenow', count);
        $card
            .find('[data-target-badge]')
            .toggleClass('hidden', !met);
    }


function escapeHtml(value){
    return $('<div>').text(
        value == null ? '' : String(value)
    ).html();
}

function statusHtml(status){
    status = String(status || '').toLowerCase();

    if(status === 'good'){
        return '<span class="status good">Good</span>';
    }

    if(status === 'bad'){
        return '<span class="status bad">Bad</span>';
    }

    return '<span class="sales-expanded-cell">Unreviewed</span>';
}

function closeExpandedPosts(){
    expandedSalesId = 0;

    $grid
        .find('.sales-progress-card.expanded')
        .removeClass('expanded')
        .attr('aria-expanded', 'false');

    $expanded
        .addClass('hidden')
        .removeAttr('data-sales-id');

    $expandedList.empty();
    $expandedLoading.addClass('hidden');

    if(expandedRequest && expandedRequest.readyState !== 4){
        expandedRequest.abort();
    }
}

function renderExpandedPosts(data){
    const posts = Array.isArray(data.posts) ? data.posts : [];

    $expandedTitle.text(
        data.sales.name + ' · ' + data.count + ' post'
        + (data.count === 1 ? '' : 's')
    );

    $expandedSubtitle.text(
        data.period_label
        + ' · #'
        + data.sales.sales_id
    );

    if(!posts.length){
        $expandedList.html(
            '<div class="sales-expanded-empty">'+
                'No verified posts in this period.'+
            '</div>'
        );
        return;
    }

    const html = posts.map(function(post){
        return (
            '<div class="sales-expanded-row">'+
                '<div class="sales-expanded-title">'+
                    '<strong>'+escapeHtml(post.title)+'</strong>'+
                    '<small>'+escapeHtml(post.platform)+'</small>'+
                '</div>'+
                '<div class="sales-expanded-cell">'+
                    escapeHtml(post.published_date)+
                '</div>'+
                '<div class="sales-expanded-cell">'+
                    escapeHtml(post.published_at)+
                '</div>'+
                '<div class="sales-expanded-status">'+
                    statusHtml(post.status)+
                '</div>'+
                '<a class="sales-expanded-review" href="'
                    +escapeHtml(post.review_url)
                    +'">Review</a>'+
            '</div>'
        );
    }).join('');

    $expandedList.html(html);
}

function openExpandedPosts($card){
    const salesId = parseInt(
        $card.attr('data-sales-id'),
        10
    ) || 0;

    if(!salesId){
        return;
    }

    if(expandedSalesId === salesId){
        closeExpandedPosts();
        return;
    }

    if(expandedRequest && expandedRequest.readyState !== 4){
        expandedRequest.abort();
    }

    expandedSalesId = salesId;

    $grid
        .find('.sales-progress-card')
        .removeClass('expanded')
        .attr('aria-expanded', 'false');

    $card
        .addClass('expanded')
        .attr('aria-expanded', 'true');

    $expanded
        .removeClass('hidden')
        .attr('data-sales-id', salesId);

    $expandedTitle.text(
        String($card.attr('data-sales-name') || 'Sales') + ' · Loading'
    );
    $expandedSubtitle.text(
        periodName(currentPeriod) + ' posts'
    );
    $expandedList.empty();
    $expandedLoading.removeClass('hidden');

    const expandedEl = $expanded.get(0);

    if(expandedEl){
        const rect = expandedEl.getBoundingClientRect();

        if(rect.top < 0 || rect.top > window.innerHeight * .82){
            expandedEl.scrollIntoView({
                behavior:'smooth',
                block:'nearest'
            });
        }
    }

    expandedRequest = $.ajax({
        url: salesPostsUrl,
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            sales_id: salesId,
            date: date,
            period: currentPeriod,
            _: Date.now()
        }
    })
    .done(function(data){
        if(
            !data
            || !data.ok
            || expandedSalesId !== salesId
        ){
            return;
        }

        renderExpandedPosts(data);
    })
    .fail(function(xhr, status){
        if(status === 'abort' || expandedSalesId !== salesId){
            return;
        }

        const data = xhr.responseJSON || {};

        $expandedList.html(
            '<div class="sales-expanded-error">'+
                escapeHtml(
                    data.message || 'Could not load Sales posts.'
                )+
            '</div>'
        );
    })
    .always(function(){
        if(expandedSalesId === salesId){
            $expandedLoading.addClass('hidden');
        }
    });
}

$grid.on('click', '[data-card-toggle]', function(event){
    if(
        $(event.target).closest(
            '[data-card-control],a,button,input,select,textarea'
        ).length
    ){
        return;
    }

    openExpandedPosts($(this));
});

$grid.on('keydown', '[data-card-toggle]', function(event){
    if(event.key !== 'Enter' && event.key !== ' '){
        return;
    }

    if(
        $(event.target).closest(
            '[data-card-control],input,button'
        ).length
    ){
        return;
    }

    event.preventDefault();
    openExpandedPosts($(this));
});

$('#salesExpandedClose').on('click', function(){
    closeExpandedPosts();
});

    $(document).on('click', '[data-target-save]', function(){
        const $button = $(this);
        const $card = $button.closest('.sales-progress-card');
        const $input = $card.find('[data-target-input]');
        const salesId = parseInt(
            $card.attr('data-sales-id'),
            10
        ) || 0;
        const target = parseInt($input.val(), 10) || 0;

        setTargetMessage($card, '', false);
        $input.removeClass('field-error');

        if(target < 1 || target > 999){
            $input.addClass('field-error').focus();
            setTargetMessage(
                $card,
                'Target must be 1–999.',
                true
            );
            return;
        }

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: targetUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _csrf: csrf,
                sales_user_id: salesId,
                target: target
            }
        })
        .done(function(data){
            if(!data || !data.ok){
                setTargetMessage(
                    $card,
                    (data && data.message) || 'Could not save.',
                    true
                );
                return;
            }

            const dailyTarget = parseInt(data.target, 10) || 10;
            $input.val(dailyTarget);
            redrawAfterDailyTargetSave($card, dailyTarget);
            setTargetMessage($card, 'Saved', false);
        })
        .fail(function(xhr){
            const data = xhr.responseJSON || {};
            setTargetMessage(
                $card,
                data.message || 'Could not save.',
                true
            );
        })
        .always(function(){
            $button.prop('disabled', false).text('Save');
        });
    });

    $grid.on('input', '[data-target-input]', function(){
        $(this).removeClass('field-error');
        setTargetMessage(
            $(this).closest('.sales-progress-card'),
            '',
            false
        );
    });

    function showRefreshNotice(data){
        if(noticeShown){
            return;
        }

        noticeShown = true;

        const count = parseInt(data.post_count, 10) || 0;
        const delta = Math.max(0, count - baselineCount);

        $noticeTitle.text(
            delta > 0
                ? delta + ' new post'
                    + (delta === 1 ? '' : 's')
                    + ' available'
                : 'New Sales activity is available'
        );

        $noticeText.text(
            'Refresh to load the latest '
            + periodName(currentPeriod).toLowerCase()
            + ' progress.'
        );

        $notice.removeClass('hidden');
    }

    function checkDashboardActivity(){
        if(document.hidden || noticeShown){
            return;
        }

        if(activityRequest && activityRequest.readyState !== 4){
            return;
        }

        activityRequest = $.ajax({
            url: updatesUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                date: date,
                period: currentPeriod,
                _: Date.now()
            }
        })
        .done(function(data){
            if(!data || !data.ok){
                return;
            }

            const postCount = parseInt(data.post_count, 10) || 0;
            const maxPostId = parseInt(data.max_post_id, 10) || 0;

            if(
                postCount > baselineCount
                || maxPostId > baselineMaxId
            ){
                showRefreshNotice(data);
            }
        });
    }

    $('#dashboardRefreshButton').on('click', function(){
        window.location.reload();
    });

    document.addEventListener('visibilitychange', function(){
        if(!document.hidden){
            checkDashboardActivity();
        }
    });

    checkDashboardActivity();
    activityTimer = setInterval(checkDashboardActivity, 5000);
})();
;
});
