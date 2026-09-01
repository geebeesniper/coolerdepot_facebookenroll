(function(){
    'use strict';

    const endpointMeta=document.querySelector('meta[name="cdsp-client-log-url"]');
    const csrfMeta=document.querySelector('meta[name="cdsp-csrf"]');
    const requestIdMeta=document.querySelector('meta[name="cdsp-request-id"]');
    const endpoint=endpointMeta?String(endpointMeta.content||''):'';
    const csrf=csrfMeta?String(csrfMeta.content||''):'';
    const pageRequestId=requestIdMeta?String(requestIdMeta.content||'').slice(0,64):'';
    const recent=new Map();

    if(!endpoint||!csrf){
        return;
    }

    function cleanUrl(value){
        try{
            const url=new URL(String(value||''),window.location.href);
            return url.origin+url.pathname;
        }catch(error){
            return String(value||'').split('?')[0].slice(0,1000);
        }
    }

    function text(value,max){
        const out=String(value==null?'':value);
        return out.length>max?out.slice(0,max)+'…[truncated]':out;
    }

    function fingerprint(payload){
        return [payload.type,payload.message,payload.source,payload.line,payload.http_status].join('|');
    }

    function shouldSend(payload){
        const key=fingerprint(payload);
        const now=Date.now();
        const last=recent.get(key)||0;
        recent.set(key,now);

        // Repeated render-loop failures can otherwise create thousands of rows.
        if(now-last<10000){
            return false;
        }

        if(recent.size>100){
            for(const [item,time] of recent.entries()){
                if(now-time>60000){
                    recent.delete(item);
                }
            }
        }
        return true;
    }

    function report(payload){
        const body={
            type:text(payload.type||'client_error',80),
            message:text(payload.message||'Browser error',2000),
            source:cleanUrl(payload.source||''),
            line:Number(payload.line)||0,
            column:Number(payload.column)||0,
            stack:text(payload.stack||'',8000),
            page_url:cleanUrl(window.location.href),
            page_request_id:pageRequestId,
            http_status:Number(payload.http_status)||0,
            request_url:cleanUrl(payload.request_url||''),
            server_request_id:text(payload.server_request_id||'',64)
        };

        if(!shouldSend(body)){
            return;
        }

        try{
            fetch(endpoint,{
                method:'POST',
                credentials:'same-origin',
                keepalive:true,
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-Token':csrf
                },
                body:JSON.stringify(body)
            }).catch(function(){
                // Never create another client error while reporting one.
            });
        }catch(error){
            // Logging must remain invisible to the application workflow.
        }
    }

    window.addEventListener('error',function(event){
        // Resource-load failures are handled by the capture listener below.
        if(event.target&&event.target!==window){
            return;
        }
        const error=event.error;
        report({
            type:'javascript_error',
            message:event.message||(error&&error.message)||'JavaScript error',
            source:event.filename||'',
            line:event.lineno||0,
            column:event.colno||0,
            stack:error&&error.stack?error.stack:''
        });
    });

    window.addEventListener('unhandledrejection',function(event){
        const reason=event.reason;
        report({
            type:'unhandled_promise_rejection',
            message:reason&&reason.message?reason.message:reason,
            source:'',
            stack:reason&&reason.stack?reason.stack:''
        });
    });

    // Resource failures (script/CSS/image) do not bubble like normal JS
    // errors, so capture them separately at the window level.
    window.addEventListener('error',function(event){
        const target=event.target;
        if(!target||target===window){
            return;
        }
        const source=target.src||target.href||'';
        if(!source){
            return;
        }
        report({
            type:'resource_load_error',
            message:'Browser resource failed to load',
            source:source
        });
    },true);

    let ajaxDiagnosticsBound=false;
    function bindAjaxDiagnostics(){
        if(ajaxDiagnosticsBound||!window.jQuery){
            return;
        }
        ajaxDiagnosticsBound=true;
        window.jQuery(document).ajaxError(function(_event,_xhr,settings,errorThrown){
            const xhr=_xhr||{};
            const request=settings||{};
            if(cleanUrl(request.url||'')===cleanUrl(endpoint)){
                return;
            }
            report({
                type:'ajax_error',
                message:errorThrown||xhr.statusText||'AJAX request failed',
                http_status:xhr.status||0,
                request_url:request.url||'',
                server_request_id:typeof xhr.getResponseHeader==='function'
                    ?(xhr.getResponseHeader('X-CDSP-Request-ID')||'')
                    :''
            });
        });
    }

    // diagnostics.js loads before jQuery so it can observe jQuery itself failing
    // to load. Bind AJAX diagnostics once the rest of the document has loaded.
    bindAjaxDiagnostics();
    document.addEventListener('DOMContentLoaded',bindAjaxDiagnostics,{once:true});

    window.CDSPDiagnostics={report:report};
})();
