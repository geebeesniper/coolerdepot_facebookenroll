/**
 * File / 文件：public/assets/diagnostics.js
 * EN: Browser diagnostics bridge that reports client-side failures to central logging.
 * 中文：该文件负责浏览器诊断，并将前端错误上报到中央日志。
 * Maintenance / 维护：Feature ownership and error paths should stay explicit and centrally diagnosable.
 * 维护要求：功能归属与错误路径必须清晰，并可进入中央诊断。
 */
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

    /**
     * EN: Remove sensitive or unstable URL details before diagnostics are reported.
     * 中文：在上报诊断信息前移除 URL 中的敏感或不稳定细节。
     *
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function cleanUrl(value){
        try{
            const url=new URL(String(value||''),window.location.href);
            return url.origin+url.pathname;
        }catch(error){
            return String(value||'').split('?')[0].slice(0,1000);
        }
    }

    /**
     * EN: Perform the text behavior used by the client diagnostics.
     * 中文：执行client diagnostics 使用的“text”行为。
     *
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     * @param {*} max Max value used by this function. / 本函数使用的“max”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function text(value,max){
        const out=String(value==null?'':value);
        return out.length>max?out.slice(0,max)+'…[truncated]':out;
    }

    /**
     * EN: Build a stable client-side fingerprint used to deduplicate diagnostics.
     * 中文：构建用于诊断信息去重的稳定客户端指纹。
     *
     * @param {Object|*} payload Payload value used by this function. / 本函数使用的“payload”参数值。
     *
     * @returns {Array} Array result produced by this UI helper. / 本 UI 辅助函数生成的数组结果。
     */
    function fingerprint(payload){
        return [payload.type,payload.message,payload.source,payload.line,payload.http_status].join('|');
    }

    /**
     * EN: Check the should send behavior used by the client diagnostics.
     * 中文：检查client diagnostics 使用的“should send”行为。
     *
     * @param {Object|*} payload Payload value used by this function. / 本函数使用的“payload”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
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

    /**
     * EN: Submit or persist the report behavior used by the client diagnostics.
     * 中文：提交或保存client diagnostics 使用的“report”行为。
     *
     * @param {Object|*} payload Payload value used by this function. / 本函数使用的“payload”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
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
    /**
     * EN: Bind the bind ajax diagnostics behavior used by the client diagnostics.
     * 中文：绑定client diagnostics 使用的“bind ajax diagnostics”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
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
