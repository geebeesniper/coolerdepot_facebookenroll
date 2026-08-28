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

});
