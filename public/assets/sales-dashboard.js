/**
 * File / 文件：public/assets/sales-dashboard.js
 * EN: Sales Dashboard browser controller, including chart and dashboard interactions.
 * 中文：该文件负责 Sales Dashboard 的浏览器交互，包括图表与页面行为。
 * Maintenance / 维护：Feature ownership and error paths should stay explicit and centrally diagnosable.
 * 维护要求：功能归属与错误路径必须清晰，并可进入中央诊断。
 */
(function(){
    'use strict';

    /**
     * EN: Run dashboard initialization after the document is ready.
     * 中文：在文档就绪后执行 Dashboard 初始化。
     *
     * @param {*} fn Fn value used by this function. / 本函数使用的“fn”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function ready(fn){
        // Match app.js's ready queue so legacy handlers are bound before
        // this controller detaches them, even with deferred script loading.
        if(window.jQuery){
            window.jQuery(fn);
            return;
        }
        if(document.readyState==='loading'){
            document.addEventListener(
                'DOMContentLoaded',
                fn,
                {once:true}
            );
        }else{
            fn();
        }
    }

    ready(function(){
        const root=document.getElementById(
            'salesPortalDashboard'
        );

        if(!root){
            return;
        }

        const $=window.jQuery||null;

        /*
         * app.js accumulated several generations of Sales range/chart
         * handlers. This module is the one authoritative controller.
         */
        if($){
            $('#salesRangeFrom').off(
                'input change'
            );
            $('#salesRangeTo').off(
                'input change'
            );
            $('#salesBackToday').off(
                'click'
            );
            $('#salesRangeForm').off(
                'submit'
            );

            $(document).off(
                'click',
                '[data-sales-period]'
            );
            $(document).off(
                'click',
                '[data-sales-platform-filter]'
            );
            $(document).off(
                'click',
                '[data-sales-day-filter]'
            );
            $(document).off(
                'mouseenter focus',
                '.sales-chart-day'
            );
            $(document).off(
                'mouseleave blur',
                '.sales-chart-day'
            );
            $(document).off(
                'click',
                '.sales-chart-day'
            );

            $('#loadMoreDailyPosts').off(
                'click'
            );
        }

        const fromInput=
            document.getElementById(
                'salesRangeFrom'
            );

        const toInput=
            document.getElementById(
                'salesRangeTo'
            );

        const periodSwitch=
            document.getElementById(
                'salesPeriodSwitch'
            );

        const channelFilter=
            document.getElementById(
                'salesPlatformFilter'
            );

        const chartBars=
            document.getElementById(
                'salesChartBars'
            );

        const chartCanvas=
            document.getElementById(
                'salesChartCanvas'
            );

        const chartScroll=
            document.getElementById(
                'salesChartScroll'
            );

        const chartPanel=
            document.getElementById(
                'salesActivityChartPanel'
            );

        const yTicks=
            document.getElementById(
                'salesChartYAxisTicks'
            );

        const gridLines=
            document.getElementById(
                'salesChartGridLines'
            );

        const targetLine=
            document.getElementById(
                'salesChartTargetLine'
            );

        const targetValue=
            document.getElementById(
                'salesChartTargetLineValue'
            );

        const targetCopy=
            document.getElementById(
                'salesChartTargetCopy'
            );

        const chartTitle=
            document.getElementById(
                'salesChartPeriodTitle'
            );

        const dailyPosts=
            document.getElementById(
                'dailyPosts'
            );

        const dailyStage=
            document.getElementById(
                'salesDailyStage'
            );

        const dailyEmpty=
            document.getElementById(
                'dailyPostsEmpty'
            );

        const rangeStatus=
            document.getElementById(
                'salesRangeStatus'
            );

        const backToday=
            document.getElementById(
                'salesBackToday'
            );

        const loadMore=
            document.getElementById(
                'loadMoreDailyPosts'
            );

        const tooltip=
            document.getElementById(
                'salesChartTooltip'
            );

        if(
            !fromInput
            ||!toInput
            ||!chartBars
            ||!chartCanvas
        ){
            return;
        }

        const state={
            from:String(
                fromInput.value||''
            ),
            to:String(
                toInput.value||''
            ),
            today:String(
                root.getAttribute(
                    'data-today'
                )||''
            ),
            period:String(
                root.getAttribute(
                    'data-range-period'
                )||'custom'
            ),
            channel:String(
                root.getAttribute(
                    'data-channel'
                )||'all'
            ).toLowerCase(),
            target:10,
            targets:{},
            rows:[],
            requestSeq:0,
            controller:null,
            loadingTimer:null,
            postFilter:'all'
        };

        const chartHeight=280;
        const xAxisHeight=32;
        const plotHeight=
            chartHeight-xAxisHeight;

        /**
         * EN: Escape text before inserting it into HTML output.
         * 中文：在将文本插入 HTML 输出前进行转义。
         *
         * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function escapeHtml(value){
            return String(value??'')
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;');
        }

        /**
         * EN: Parse an ISO date string into a local Date value used by the dashboard.
         * 中文：将 ISO 日期字符串解析为 Dashboard 使用的本地 Date 值。
         *
         * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function parseIso(value){
            if(
                !/^\d{4}-\d{2}-\d{2}$/
                    .test(String(value||''))
            ){
                return null;
            }

            const date=new Date(
                value+'T12:00:00'
            );

            return Number.isNaN(
                date.getTime()
            )
                ?null
                :date;
        }

        /**
         * EN: Format a Date value as the ISO-style date key used by API requests and filters.
         * 中文：将 Date 值格式化为 API 请求和筛选使用的 ISO 日期键。
         *
         * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function iso(date){
            const y=date.getFullYear();
            const m=String(
                date.getMonth()+1
            ).padStart(2,'0');
            const d=String(
                date.getDate()
            ).padStart(2,'0');

            return y+'-'+m+'-'+d;
        }

        /**
         * EN: Perform the date range behavior used by the sales dashboard.
         * 中文：执行sales dashboard 使用的“date range”行为。
         *
         * @param {*} from From value used by this function. / 本函数使用的“from”参数值。
         * @param {*} to To value used by this function. / 本函数使用的“to”参数值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function dateRange(from,to){
            const start=parseIso(from);
            const end=parseIso(to);
            const dates=[];

            if(!start||!end){
                return dates;
            }

            let guard=0;
            let cursor=new Date(start);

            while(
                cursor<=end
                &&guard<1000
            ){
                dates.push(
                    iso(cursor)
                );
                cursor.setDate(
                    cursor.getDate()+1
                );
                guard++;
            }

            return dates;
        }

        /**
         * EN: Format or normalize the short date behavior used by the sales dashboard.
         * 中文：格式化或规范化sales dashboard 使用的“short date”行为。
         *
         * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function shortDate(value){
            const date=parseIso(value);

            if(!date){
                return value;
            }

            return (
                (date.getMonth()+1)
                +'/'
                +date.getDate()
            );
        }

        /**
         * EN: Format or normalize the normalize range behavior used by the sales dashboard.
         * 中文：格式化或规范化sales dashboard 使用的“normalize range”行为。
         *
         * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function normalizeRange(changed){
            let from=String(
                fromInput.value||''
            );
            let to=String(
                toInput.value||''
            );

            if(
                !parseIso(from)
                ||!parseIso(to)
            ){
                return null;
            }

            const max=String(
                toInput.getAttribute('max')
                ||state.today
                ||''
            );

            if(max&&to>max){
                to=max;
                toInput.value=to;
            }

            if(max&&from>max){
                from=max;
                fromInput.value=from;
            }

            if(
                changed==='from'
                &&from>to
            ){
                to=from;
                toInput.value=to;
            }else if(
                changed==='to'
                &&to<from
            ){
                from=to;
                fromInput.value=from;
            }else if(from>to){
                from=to;
                fromInput.value=from;
            }

            fromInput.setAttribute(
                'max',
                to
            );

            toInput.setAttribute(
                'min',
                from
            );

            if(max){
                toInput.setAttribute(
                    'max',
                    max
                );
            }

            state.from=from;
            state.to=to;

            updateBackToday();

            return {
                from:from,
                to:to
            };
        }

        /**
         * EN: Perform the preset range behavior used by the sales dashboard.
         * 中文：执行sales dashboard 使用的“preset range”行为。
         *
         * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
         * @param {*} anchorValue Anchor value value used by this function. / 本函数使用的“anchor value”参数值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function presetRange(
            period,
            anchorValue
        ){
            let anchor=parseIso(
                anchorValue
            );

            const today=parseIso(
                state.today
            );

            if(!anchor){
                anchor=today;
            }

            if(!anchor){
                return null;
            }

            if(
                today
                &&anchor>today
            ){
                anchor=new Date(today);
            }

            const to=new Date(anchor);
            let from=new Date(anchor);

            if(period==='single'){
                from=new Date(to);
            }else if(period==='day'){
                from.setDate(
                    from.getDate()-2
                );
            }else if(period==='week'){
                from.setDate(
                    from.getDate()-6
                );
            }else if(period==='month'){
                const day=to.getDate();
                const previousMonthStart=
                    new Date(
                        to.getFullYear(),
                        to.getMonth()-1,
                        1,
                        12,0,0
                    );

                const previousMonthLast=
                    new Date(
                        to.getFullYear(),
                        to.getMonth(),
                        0,
                        12,0,0
                    );

                from=new Date(
                    previousMonthStart
                        .getFullYear(),
                    previousMonthStart
                        .getMonth(),
                    Math.min(
                        day,
                        previousMonthLast
                            .getDate()
                    ),
                    12,0,0
                );

                from.setDate(
                    from.getDate()+1
                );
            }else{
                return {
                    from:state.from,
                    to:state.to
                };
            }

            return {
                from:iso(from),
                to:iso(to)
            };
        }

        /**
         * EN: Perform the title for period behavior used by the sales dashboard.
         * 中文：执行sales dashboard 使用的“title for period”行为。
         *
         * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
         */
        function titleForPeriod(){
            if(state.period==='single'){
                return '1-Day Post Progress';
            }
            if(state.period==='day'){
                return '3-Day Post Progress';
            }

            if(state.period==='week'){
                return 'Weekly Post Progress';
            }

            if(state.period==='month'){
                return 'Monthly Post Progress';
            }

            return 'Custom Range Progress';
        }

        /**
         * EN: Update the set period behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“set period”行为。
         *
         * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function setPeriod(period){
            state.period=String(
                period||'custom'
            );

            root.setAttribute(
                'data-range-period',
                state.period
            );

            if(periodSwitch){
                periodSwitch
                    .querySelectorAll(
                        '[data-sales-period]'
                    )
                    .forEach(function(button){
                        const active=
                            button.getAttribute(
                                'data-sales-period'
                            )===state.period;

                        button.classList.toggle(
                            'active',
                            active
                        );

                        button.setAttribute(
                            'aria-pressed',
                            active
                                ?'true'
                                :'false'
                        );
                    });
            }

            if(chartTitle){
                const titleKey={
                    single:'oneDayProgressTitle',
                    day:'dailyProgressTitle',
                    week:'weeklyProgressTitle',
                    month:'monthlyProgressTitle',
                    custom:'customProgressTitle'
                }[state.period]||'customProgressTitle';
                chartTitle.setAttribute('data-sales-i18n',titleKey);
                chartTitle.textContent=window.cdspSalesLanguage
                    ?window.cdspSalesLanguage.translate(titleKey)
                    :titleForPeriod();
            }
        }

        /**
         * EN: Update the set channel behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“set channel”行为。
         *
         * @param {*} channel Channel value used by this function. / 本函数使用的“channel”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function setChannel(channel){
            state.channel=String(
                channel||'all'
            ).toLowerCase();

            root.setAttribute(
                'data-channel',
                state.channel
            );

            if(channelFilter){
                channelFilter
                    .querySelectorAll(
                        '[data-sales-platform-filter]'
                    )
                    .forEach(function(button){
                        const active=
                            String(
                                button.getAttribute(
                                    'data-sales-platform-filter'
                                )||''
                            ).toLowerCase()
                            ===state.channel;

                        button.classList.toggle(
                            'active',
                            active
                        );

                        button.setAttribute(
                            'aria-pressed',
                            active
                                ?'true'
                                :'false'
                        );
                    });
            }
        }

        /**
         * EN: Update the update back today behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“update back today”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function updateBackToday(){
            if(!backToday){
                return;
            }

            const max=String(
                toInput.getAttribute(
                    'max'
                )
                ||state.today
                ||''
            );

            const atLatest=Boolean(
                (state.today
                    &&state.to===state.today)
                ||(max&&state.to===max)
            );

            backToday.classList.toggle(
                'hidden',
                atLatest
            );
        }

        /**
         * EN: Build the aggregate behavior used by the sales dashboard.
         * 中文：构建sales dashboard 使用的“aggregate”行为。
         *
         * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
         *
         * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
         */
        function aggregate(date){
            const result={
                post_count:0,
                good_count:0,
                bad_count:0,
                unreviewed_count:0
            };

            state.rows.forEach(
                function(row){
                    if(
                        String(row.date||'')
                        !==date
                    ){
                        return;
                    }

                    result.post_count+=
                        parseInt(
                            row.post_count,
                            10
                        )||0;

                    result.good_count+=
                        parseInt(
                            row.good_count,
                            10
                        )||0;

                    result.bad_count+=
                        parseInt(
                            row.bad_count,
                            10
                        )||0;

                    result.unreviewed_count+=
                        parseInt(
                            row.unreviewed_count,
                            10
                        )||0;
                }
            );

            return result;
        }

        /**
         * EN: Resolve the date-effective Daily Target for one chart day.
         * 中文：解析某个图表日期当天实际生效的 Daily Target。
         *
         * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
         * @returns {number} Effective Daily Target for the date. / 当天生效的 Daily Target。
         */
        function dailyTargetForDate(date){
            const raw=state.targets&&Object.prototype.hasOwnProperty.call(state.targets,date)
                ?state.targets[date]
                :state.target;
            return Math.max(1,parseInt(raw,10)||state.target||10);
        }

        /**
         * EN: Perform the tick step behavior used by the sales dashboard.
         * 中文：执行sales dashboard 使用的“tick step”行为。
         *
         * @param {*} maxValue Max value value used by this function. / 本函数使用的“max value”参数值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function tickStep(maxValue){
            const rough=
                Math.max(
                    1,
                    Number(maxValue)||1
                )/6;

            if(rough<=1){
                return 1;
            }

            if(rough<=2){
                return 2;
            }

            if(rough<=3){
                return 3;
            }

            if(rough<=5){
                return 5;
            }

            const magnitude=
                Math.pow(
                    10,
                    Math.floor(
                        Math.log10(
                            rough
                        )
                    )
                );

            const normalized=
                rough/magnitude;

            if(normalized<=1){
                return magnitude;
            }

            if(normalized<=2){
                return 2*magnitude;
            }

            if(normalized<=5){
                return 5*magnitude;
            }

            return 10*magnitude;
        }

        /**
         * EN: Render the render axis behavior used by the sales dashboard.
         * 中文：渲染sales dashboard 使用的“render axis”行为。
         *
         * @param {*} cap Cap value used by this function. / 本函数使用的“cap”参数值。
         * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function renderAxis(cap,targets){
            const step=
                tickStep(cap);
            const targetSet=new Set(
                (Array.isArray(targets)?targets:[targets])
                    .map(function(value){
                        return String(Math.max(1,parseInt(value,10)||0));
                    })
            );

            const values=[];

            for(
                let value=0;
                value<=cap+0.0001;
                value+=step
            ){
                values.push(
                    Number(
                        value.toFixed(4)
                    )
                );
            }

            if(
                !values.length
                ||Math.abs(
                    values[
                        values.length-1
                    ]-cap
                )>0.0001
            ){
                values.push(cap);
            }

            let tickHtml='';
            let gridHtml='';

            const seen=new Set();

            values.forEach(
                function(value){
                    const key=
                        String(value);

                    if(seen.has(key)){
                        return;
                    }

                    seen.add(key);

                    const top=
                        plotHeight
                        *(1-(value/cap));

                    const label=
                        Number.isInteger(
                            value
                        )
                            ?String(value)
                            :String(
                                Number(
                                    value.toFixed(1)
                                )
                            );

                    tickHtml+=
                        '<span'
                        +' class="sales-chart-y-tick'
                        +(targetSet.has(String(Math.round(value)))
                            ?' target'
                            :'')
                        +'"'
                        +' style="top:'
                        +top
                        +'px">'
                        +escapeHtml(label)
                        +'</span>';

                    gridHtml+=
                        '<span'
                        +' class="sales-chart-grid-line'
                        +(targetSet.has(String(Math.round(value)))
                            ?' target'
                            :'')
                        +'"'
                        +' style="top:'
                        +top
                        +'px"></span>';
                }
            );

            if(yTicks){
                yTicks.innerHTML=
                    tickHtml;
            }

            if(gridLines){
                gridLines.innerHTML=
                    gridHtml;
            }
        }

        let chartRenderKey='';

        /**
         * EN: Render the render chart behavior used by the sales dashboard.
         * 中文：渲染sales dashboard 使用的“render chart”行为。
         *
         * @param {Object|*} options Optional settings that control this function. / 控制本函数行为的可选设置。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function renderChart(options){
            const animate=!options||options.animate!==false;
            state.from=String(
                fromInput.value||state.from
            );

            state.to=String(
                toInput.value||state.to
            );

            const dates=dateRange(
                state.from,
                state.to
            );

            const dayTargets=dates.map(dailyTargetForDate);
            const maxTarget=dayTargets.length
                ?Math.max.apply(null,dayTargets)
                :Math.max(1,parseInt(state.target,10)||10);
            const minTarget=dayTargets.length
                ?Math.min.apply(null,dayTargets)
                :maxTarget;
            let maxPosts=0;
            dates.forEach(function(date){
                maxPosts=Math.max(
                    maxPosts,
                    Math.max(0,parseInt(aggregate(date).post_count,10)||0)
                );
            });
            const cap=Math.max(
                1,
                maxTarget*1.2,
                maxPosts+1
            );

            // Ignore duplicate layout notifications without interrupting growth.
            const renderKey=JSON.stringify([
                state.from,state.to,state.channel,dayTargets,state.rows,
                chartScroll?chartScroll.clientWidth:chartPanel?chartPanel.clientWidth:720
            ]);
            setPeriod(state.period);
            if(!animate&&renderKey===chartRenderKey){return;}
            chartRenderKey=renderKey;
            if(tooltip){tooltip.classList.add('hidden');}

            renderAxis(
                cap,
                Array.from(new Set(dayTargets))
            );

            const targetSummary=minTarget===maxTarget
                ?String(maxTarget)
                :String(minTarget)+'–'+String(maxTarget);

            if(targetValue){
                targetValue.textContent=targetSummary;
            }
            if(targetCopy){
                targetCopy.textContent=targetSummary;
            }

            if(targetLine){
                targetLine.classList.add('hidden');
                targetLine.removeAttribute('style');
            }

            if(!dates.length){
                chartBars.innerHTML='';
                return;
            }

            const available=
                Math.max(
                    320,
                    Math.floor(
                        (
                            chartScroll
                                ?chartScroll
                                    .clientWidth
                                :chartPanel
                                    ?chartPanel
                                        .clientWidth
                                    :720
                        )-2
                    )
                );

            let minSlot;

            if(dates.length<=3){
                minSlot=82;
            }else if(dates.length<=7){
                minSlot=52;
            }else{
                minSlot=34;
            }

            const natural=
                available/dates.length;

            const needsScroll=
                natural<minSlot;

            const width=needsScroll
                ?Math.max(
                    available,
                    dates.length*minSlot
                )
                :available;

            const slot=
                width/dates.length;

            let barWidth;

            if(dates.length<=3){
                barWidth=Math.min(
                    74,
                    Math.max(
                        46,
                        slot*.46
                    )
                );
            }else if(dates.length<=7){
                barWidth=Math.min(
                    48,
                    Math.max(
                        24,
                        slot*.45
                    )
                );
            }else{
                barWidth=Math.min(
                    34,
                    Math.max(
                        12,
                        slot*.58
                    )
                );
            }

            let html='';

            dates.forEach(function(date){
                const raw=
                    aggregate(date);

                const actual=Math.max(
                    0,
                    parseInt(
                        raw.post_count,
                        10
                    )||0
                );
                const dayTarget=dailyTargetForDate(date);
                const targetTop=plotHeight*(1-(dayTarget/cap));
                const targetIndex=dates.indexOf(date);
                const previousTarget=targetIndex>0
                    ?dailyTargetForDate(dates[targetIndex-1])
                    :null;
                const showTargetLabel=
                    targetIndex===0
                    ||targetIndex===dates.length-1
                    ||previousTarget!==dayTarget;

                const good=Math.min(
                    actual,
                    Math.max(
                        0,
                        parseInt(
                            raw.good_count,
                            10
                        )||0
                    )
                );

                const bad=Math.min(
                    Math.max(
                        0,
                        actual-good
                    ),
                    Math.max(
                        0,
                        parseInt(
                            raw.bad_count,
                            10
                        )||0
                    )
                );

                const unreviewed=
                    Math.max(
                        0,
                        actual-good-bad
                    );

                const visibleTotal=
                    Math.min(
                        actual,
                        cap
                    );

                const scale=
                    actual>0
                        ?visibleTotal/actual
                        :0;

                const goodH=
                    (good*scale/cap)*100;

                const badH=
                    (bad*scale/cap)*100;

                const unreviewedH=
                    (unreviewed*scale/cap)*100;

                html+=
                    '<div'
                    +' class="sales-chart-day"'
                    +' tabindex="0"'
                    +' data-chart-date="'
                    +escapeHtml(date)
                    +'"'
                    +' data-chart-total="'
                    +actual
                    +'"'
                    +' data-chart-good="'
                    +good
                    +'"'
                    +' data-chart-bad="'
                    +bad
                    +'"'
                    +' data-chart-unreviewed="'
                    +unreviewed
                    +'"'
                    +' data-chart-target="'
                    +dayTarget
                    +'">'
                    +'<div'
                    +' class="sales-chart-day-plot">'
                    +'<span class="sales-chart-day-target" style="top:'
                    +targetTop
                    +'px">'
                    +(showTargetLabel
                        ?'<span>Daily target '+dayTarget+'</span>'
                        :'')
                    +'</span>'
                    +'<div'
                    +' class="sales-chart-stack">'
                    +'<div class="sales-chart-stack-fill'
                    +(animate?' sales-chart-grow':'')
                    +'">'
                    +'<span'
                    +' class="sales-chart-segment good"'
                    +' data-chart-segment="good"'
                    +' data-chart-segment-count="'+good+'"'
                    +' style="height:'
                    +goodH
                    +'%"></span>'
                    +'<span'
                    +' class="sales-chart-segment bad"'
                    +' data-chart-segment="bad"'
                    +' data-chart-segment-count="'+bad+'"'
                    +' style="height:'
                    +badH
                    +'%"></span>'
                    +'<span'
                    +' class="sales-chart-segment unreviewed"'
                    +' data-chart-segment="unreviewed"'
                    +' data-chart-segment-count="'+unreviewed+'"'
                    +' style="height:'
                    +unreviewedH
                    +'%"></span>'
                    +'</div>'
                    +'</div>'
                    +'</div>'
                    +'<span'
                    +' class="sales-chart-x-label">'
                    +escapeHtml(
                        shortDate(date)
                    )
                    +'</span>'
                    +'</div>';
            });

            chartBars.innerHTML=
                html;

            chartCanvas.style.width=
                Math.round(width)
                +'px';

            chartCanvas.style.height=
                chartHeight+'px';

            chartBars.style
                .gridTemplateColumns=
                'repeat('
                +dates.length
                +',minmax(0,1fr))';

            chartBars.style
                .setProperty(
                    '--sales-chart-bar-width',
                    Math.round(
                        barWidth
                    )+'px'
                );

            if(chartPanel){
                chartPanel.setAttribute(
                    'data-range-days',
                    String(
                        dates.length
                    )
                );

                chartPanel.setAttribute(
                    'data-chart-from',
                    state.from
                );

                chartPanel.setAttribute(
                    'data-chart-to',
                    state.to
                );
            }
        }

        /*
         * Old ResizeObserver callbacks call window.renderSalesChart().
         * Point that global at this authoritative implementation too.
         */
        window.renderSalesChart=
            renderChart;

        /**
         * EN: Build the range status label behavior used by the sales dashboard.
         * 中文：构建sales dashboard 使用的“range status label”行为。
         *
         * @param {*} filter Filter value used by this function. / 本函数使用的“filter”参数值。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function rangeStatusLabel(filter){
            const key={all:'allPosts',good:'good',bad:'issues',unreviewed:'unreviewed'}[filter]||'allPosts';
            if(window.cdspSalesLanguage){return window.cdspSalesLanguage.translate(key);}
            return {all:'All',good:'Good',bad:'Bad',unreviewed:'Unreviewed'}[filter]||'All';
        }

        /**
         * EN: Update the apply range post filter behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“apply range post filter”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function applyRangePostFilter(){
            const section=document.getElementById('salesRangePostSection');
            if(!section){return;}
            const allowed=['all','good','bad','unreviewed'];
            const filter=allowed.includes(state.postFilter)?state.postFilter:'all';
            state.postFilter=filter;
            const cards=Array.from(section.querySelectorAll('.sales-range-post-grid > .sales-self-post-card'));
            const counts={all:cards.length,good:0,bad:0,unreviewed:0};
            let visible=0;
            cards.forEach(function(card){
                const raw=String(card.getAttribute('data-sales-post-status')||'').trim().toLowerCase();
                const status=raw==='good'||raw==='bad'?raw:'unreviewed';
                counts[status]++;
                const show=filter==='all'||status===filter;
                // Native hidden also removes filtered cards from keyboard focus.
                card.hidden=!show;
                card.classList.toggle('sales-card-filtered-out',!show);
                card.setAttribute('aria-hidden',show?'false':'true');
                if(show){visible++;}
            });
            section.querySelectorAll('[data-sales-post-filter]').forEach(function(button){
                const value=String(button.getAttribute('data-sales-post-filter')||'all');
                const active=value===filter;
                const count=counts[value]||0;
                button.classList.toggle('active',active);
                button.setAttribute('aria-pressed',active?'true':'false');
                button.setAttribute('aria-controls','salesRangePostGrid');
                button.setAttribute('title',rangeStatusLabel(value)+': '+count);
                button.setAttribute('aria-label',rangeStatusLabel(value)+': '+count);
                const badge=button.querySelector('strong');
                if(badge){badge.textContent=String(count);}
            });
            section.setAttribute('data-active-post-filter',filter);
            const filterEmpty=section.querySelector('[data-sales-post-filter-empty]');
            const rangeEmpty=section.querySelector('[data-sales-range-empty]');
            if(filterEmpty){
                const copy=filterEmpty.querySelector('[data-sales-post-filter-empty-copy]');
                if(copy){copy.textContent=window.cdspSalesLanguage
                    ?window.cdspSalesLanguage.translate('noFilteredPosts',{status:rangeStatusLabel(filter)})
                    :'No '+rangeStatusLabel(filter)+' posts in this range.';}
                filterEmpty.classList.toggle('hidden',!(cards.length>0&&visible===0));
            }
            if(rangeEmpty){rangeEmpty.classList.toggle('hidden',cards.length>0);}
        }

        if($){
            $(document).off('cdsp:language-changed.cdspPostFilter')
                .on('cdsp:language-changed.cdspPostFilter',applyRangePostFilter);
        }

        /**
         * EN: Update the set loading behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“set loading”行为。
         *
         * @param {*} active Active value used by this function. / 本函数使用的“active”参数值。
         * @param {*} reason Reason value used by this function. / 本函数使用的“reason”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function setLoading(active,reason){
            if(
                state.loadingTimer
            ){
                window.clearTimeout(
                    state.loadingTimer
                );
                state.loadingTimer=null;
            }

            if(dailyStage){
                dailyStage.classList.toggle(
                    'sales-dashboard-changing',
                    Boolean(active)
                );
            }

            const shell=
                chartPanel
                    ?chartPanel.querySelector(
                        '.sales-chart-shell'
                    )
                    :null;

            if(shell){
                shell.classList.toggle(
                    'sales-dashboard-changing',
                    Boolean(active)
                );
            }

            if(channelFilter){
                channelFilter.classList.toggle(
                    'sales-dashboard-channel-loading',
                    Boolean(
                        active
                        &&reason==='channel'
                    )
                );
            }

            if(active){
                state.loadingTimer=
                    window.setTimeout(
                        function(){
                            setLoading(
                                false,
                                reason
                            );
                        },
                        700
                    );
            }
        }

        /**
         * EN: Update the set error behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“set error”行为。
         *
         * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function setError(message){
            if(!rangeStatus){
                return;
            }

            rangeStatus.textContent=
                String(message||'');

            rangeStatus.classList.toggle(
                'error',
                Boolean(message)
            );
        }

        /**
         * EN: Update the update url behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“update url”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function updateUrl(){
            const url=new URL(
                window.location.href
            );

            url.searchParams.set(
                'from',
                state.from
            );

            url.searchParams.set(
                'to',
                state.to
            );

            url.searchParams.set(
                'period',
                state.period
            );

            if(state.channel==='all'){
                url.searchParams.delete(
                    'channel'
                );
            }else{
                url.searchParams.set(
                    'channel',
                    state.channel
                );
            }

            window.history.replaceState(
                {},
                '',
                url.toString()
            );
        }

        /**
         * EN: Update the apply server data behavior used by the sales dashboard.
         * 中文：更新sales dashboard 使用的“apply server data”行为。
         *
         * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
         * @param {*} reason Reason value used by this function. / 本函数使用的“reason”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function applyServerData(
            data,
            reason
        ){
            state.from=String(
                data.from||state.from
            );

            state.to=String(
                data.to||state.to
            );

            state.period=String(
                data.period||state.period
            );

            state.channel=String(
                data.channel||state.channel
            ).toLowerCase();

            state.target=Math.max(
                1,
                parseInt(
                    data.daily_target,
                    10
                )||state.target
            );
            state.targets=(data.daily_targets&&typeof data.daily_targets==='object')
                ?data.daily_targets
                :{};

            state.rows=Array.isArray(
                data.chart_rows
            )
                ?data.chart_rows
                :[];

            fromInput.value=
                state.from;

            toInput.value=
                state.to;

            root.setAttribute(
                'data-from',
                state.from
            );

            root.setAttribute(
                'data-to',
                state.to
            );

            if(dailyPosts){
                dailyPosts.innerHTML=
                    data.html||'';

                dailyPosts.setAttribute(
                    'data-from',
                    state.from
                );

                dailyPosts.setAttribute(
                    'data-to',
                    state.to
                );

                dailyPosts.setAttribute(
                    'data-offset',
                    String(
                        data.next_offset||0
                    )
                );
            }

            const hasDays=
                (
                    parseInt(
                        data.total_days,
                        10
                    )||0
                )>0;

            if(dailyEmpty){
                dailyEmpty.classList.toggle(
                    'hidden',
                    hasDays
                );
            }

            if(dailyStage){
                dailyStage.classList.toggle(
                    'sales-daily-stage-empty',
                    !hasDays
                );
            }

            if(loadMore){
                const hasMore=
                    Boolean(
                        data.has_more
                    );

                loadMore.hidden=
                    !hasMore;

                loadMore.disabled=
                    !hasMore;
            }

            normalizeRange('');
            setPeriod(
                state.period
            );
            setChannel(
                state.channel
            );
            applyRangePostFilter();
            if(window.cdspSalesLanguage){window.cdspSalesLanguage.apply();}
            renderChart();
            updateBackToday();
            updateUrl();

            setLoading(
                false,
                reason
            );

            if(dailyStage){
                dailyStage.classList
                    .add(
                        'sales-dashboard-enter'
                    );

                window.setTimeout(
                    function(){
                        dailyStage.classList
                            .remove(
                                'sales-dashboard-enter'
                            );
                    },
                    280
                );
            }

            setError('');
        }

        async function fetchRange(reason){
            const range=
                normalizeRange('');

            if(!range){
                return;
            }

            state.requestSeq++;
            const seq=
                state.requestSeq;

            if(state.controller){
                state.controller.abort();
            }

            state.controller=
                new AbortController();

            setLoading(
                true,
                reason
            );

            const base=String(
                window.CD_BASE_PATH||''
            );

            const params=
                new URLSearchParams({
                    from:state.from,
                    to:state.to,
                    offset:'0',
                    limit:String(
                        parseInt(
                            dailyPosts
                                ?dailyPosts
                                    .getAttribute(
                                        'data-limit'
                                    )
                                :3,
                            10
                        )||3
                    ),
                    channel:state.channel,
                    period:state.period
                });

            try{
                const response=
                    await fetch(
                        base
                        +'/sales/daily-posts?'
                        +params.toString(),
                        {
                            method:'GET',
                            cache:'no-store',
                            headers:{
                                'Accept':
                                    'application/json'
                            },
                            signal:
                                state.controller
                                    .signal
                        }
                    );

                if(
                    seq!==state.requestSeq
                ){
                    return;
                }

                if(!response.ok){
                    throw new Error(
                        'Request failed: '
                        +response.status
                    );
                }

                const data=
                    await response.json();

                if(
                    !data
                    ||!data.ok
                ){
                    throw new Error(
                        data&&data.message
                            ?data.message
                            :'Could not load posts.'
                    );
                }

                applyServerData(
                    data,
                    reason
                );
            }catch(error){
                if(
                    error
                    &&error.name==='AbortError'
                ){
                    return;
                }

                if(
                    seq!==state.requestSeq
                ){
                    return;
                }

                setLoading(
                    false,
                    reason
                );

                if(window.CDSPDiagnostics&&typeof window.CDSPDiagnostics.report==='function'){
                    window.CDSPDiagnostics.report({
                        type:'sales_dashboard_fetch_error',
                        message:error&&error.message?error.message:'Could not load posts.',
                        request_url:base+'/sales/daily-posts'
                    });
                }

                setError(
                    error&&error.message
                        ?error.message
                        :'Could not load posts.'
                );
            }
        }

        /**
         * EN: Perform the custom input preview behavior used by the sales dashboard.
         * 中文：执行sales dashboard 使用的“custom input preview”行为。
         *
         * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function customInputPreview(
            changed
        ){
            const range=
                normalizeRange(
                    changed
                );

            if(!range){
                return;
            }

            setPeriod(
                'custom'
            );

            /*
             * This is the critical invariant:
             * X axis changes from the input values immediately.
             * Example 08/01 -> 08/10 means exactly ten X labels now.
             */
            renderChart();
            updateUrl();
        }

        let dateFetchTimer=null;

        /**
         * EN: Schedule or start the schedule date fetch behavior used by the sales dashboard.
         * 中文：调度或启动sales dashboard 使用的“schedule date fetch”行为。
         *
         * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function scheduleDateFetch(
            changed
        ){
            customInputPreview(
                changed
            );

            if(dateFetchTimer){
                window.clearTimeout(
                    dateFetchTimer
                );
            }

            dateFetchTimer=
                window.setTimeout(
                    function(){
                        fetchRange(
                            'range'
                        );
                    },
                    140
                );
        }

        /*
         * Use native input AND change.
         * Native date pickers vary by browser; relying only on jQuery
         * change was the reason the visible To date could move while
         * the chart remained on the previous month.
         */
        fromInput.addEventListener(
            'input',
            function(){
                customInputPreview(
                    'from'
                );
            }
        );

        toInput.addEventListener(
            'input',
            function(){
                customInputPreview(
                    'to'
                );
            }
        );

        fromInput.addEventListener(
            'change',
            function(){
                scheduleDateFetch(
                    'from'
                );
            }
        );

        toInput.addEventListener(
            'change',
            function(){
                scheduleDateFetch(
                    'to'
                );
            }
        );

        const rangeForm=
            document.getElementById(
                'salesRangeForm'
            );

        // Delegate once on the stable Posts container, including first load.
        if(dailyPosts){
            dailyPosts.addEventListener('click',function(event){
                const button=event.target.closest('[data-sales-post-filter]');
                if(!button||!dailyPosts.contains(button)){return;}
                event.preventDefault();
                event.stopPropagation();
                state.postFilter=String(button.getAttribute('data-sales-post-filter')||'all');
                applyRangePostFilter();
            },true);
        }

        if(rangeForm){
            rangeForm.addEventListener(
                'submit',
                function(event){
                    event.preventDefault();

                    customInputPreview(
                        ''
                    );

                    fetchRange(
                        'range'
                    );
                }
            );
        }

        if(periodSwitch){
            periodSwitch.addEventListener(
                'click',
                function(event){
                    const button=
                        event.target.closest(
                            '[data-sales-period]'
                        );

                    if(!button){
                        return;
                    }

                    event.preventDefault();

                    const period=
                        String(
                            button.getAttribute(
                                'data-sales-period'
                            )||'custom'
                        );

                    if(period==='custom'){
                        setPeriod(
                            'custom'
                        );
                        renderChart();
                        fetchRange(
                            'range'
                        );
                        return;
                    }

                    const range=
                        presetRange(
                            period,
                            toInput.value
                            ||state.today
                        );

                    if(!range){
                        return;
                    }

                    fromInput.value=
                        range.from;

                    toInput.value=
                        range.to;

                    state.from=
                        range.from;

                    state.to=
                        range.to;

                    normalizeRange('');
                    setPeriod(period);
                    renderChart();
                    updateBackToday();
                    updateUrl();

                    fetchRange(
                        'range'
                    );
                }
            );
        }

        if(backToday){
            backToday.addEventListener(
                'click',
                function(event){
                    event.preventDefault();

                    const period=
                        ['single','day','week','month']
                            .includes(
                                state.period
                            )
                            ?state.period
                            :'single';

                    const range=
                        presetRange(
                            period,
                            state.today
                        );

                    if(!range){
                        return;
                    }

                    fromInput.value=
                        range.from;

                    toInput.value=
                        range.to;

                    state.from=
                        range.from;

                    state.to=
                        range.to;

                    normalizeRange('');
                    setPeriod(period);
                    renderChart();
                    updateBackToday();
                    updateUrl();

                    fetchRange(
                        'range'
                    );
                }
            );
        }

        if(channelFilter){
            channelFilter.addEventListener(
                'click',
                function(event){
                    const button=
                        event.target.closest(
                            '[data-sales-platform-filter]'
                        );

                    if(!button){
                        return;
                    }

                    event.preventDefault();

                    const channel=
                        String(
                            button.getAttribute(
                                'data-sales-platform-filter'
                            )||'all'
                        ).toLowerCase();

                    if(
                        channel===state.channel
                    ){
                        return;
                    }

                    /*
                     * Preserve the current Posts footprint while the new
                     * channel is loading. Empty server result keeps it.
                     */
                    if(dailyStage){
                        const h=
                            Math.ceil(
                                dailyStage
                                    .getBoundingClientRect()
                                    .height
                            );

                        if(h>0){
                            dailyStage.style
                                .setProperty(
                                    '--sales-preserved-height',
                                    h+'px'
                                );
                        }
                    }

                    setChannel(
                        channel
                    );

                    fetchRange(
                        'channel'
                    );
                }
            );
        }

        /*
         * Per-day All / Good / Issues / Unreviewed filtering.
         * It is independent from the channel state because server HTML
         * has already been filtered to the active channel.
         */
        document.addEventListener(
            'click',
            function(event){
                const button=
                    event.target.closest(
                        '[data-sales-day-filter]'
                    );

                if(!button){
                    return;
                }

                const section=
                    button.closest(
                        '.sales-day-section'
                    );

                if(!section){
                    return;
                }

                event.preventDefault();

                const filter=
                    String(
                        button.getAttribute(
                            'data-sales-day-filter'
                        )||'all'
                    );

                const cards=
                    Array.from(
                        section.querySelectorAll(
                            '.sales-self-post-card'
                        )
                    );

                let visible=0;

                cards.forEach(
                    function(card){
                        const status=
                            String(
                                card.getAttribute(
                                    'data-sales-post-status'
                                )||'unreviewed'
                            );

                        const show=
                            filter==='all'
                            ||status===filter;

                        card.classList.toggle(
                            'sales-card-filtered-out',
                            !show
                        );

                        if(show){
                            visible++;
                        }
                    }
                );

                section
                    .querySelectorAll(
                        '[data-sales-day-filter]'
                    )
                    .forEach(
                        function(item){
                            const active=
                                item===button;

                            item.classList.toggle(
                                'active',
                                active
                            );

                            item.setAttribute(
                                'aria-pressed',
                                active
                                    ?'true'
                                    :'false'
                            );
                        }
                    );

                let empty=
                    section.querySelector(
                        '.sales-module-filter-empty'
                    );

                if(
                    visible===0
                    &&cards.length>0
                ){
                    if(!empty){
                        empty=
                            document.createElement(
                                'div'
                            );

                        empty.className=
                            'sales-module-filter-empty sales-empty-message sales-filter-empty-message';

                        empty.innerHTML=
                            '<span class="sales-empty-icon" aria-hidden="true">'
                            +'<svg viewBox="0 0 24 24">'
                            +'<path d="M4 4h16v16H4V4Zm2 2v12h12V6H6Zm2 2h8v2H8V8Zm0 4h5v2H8v-2Z"/>'
                            +'</svg>'
                            +'</span>'
                            +'<strong>Empty</strong>'
                            +'<span>No posts match this filter.</span>';

                        const grid=
                            section.querySelector(
                                '.sales-post-card-grid'
                            );

                        if(grid){
                            grid.appendChild(
                                empty
                            );
                        }
                    }

                    empty.hidden=false;
                }else if(empty){
                    empty.hidden=true;
                }
            }
        );

        if(loadMore){
            loadMore.addEventListener(
                'click',
                async function(event){
                    event.preventDefault();

                    if(
                        loadMore.disabled
                        ||!dailyPosts
                    ){
                        return;
                    }

                    const base=String(
                        window.CD_BASE_PATH||''
                    );

                    const offset=
                        parseInt(
                            dailyPosts.getAttribute(
                                'data-offset'
                            )||'0',
                            10
                        )||0;

                    const limit=
                        parseInt(
                            dailyPosts.getAttribute(
                                'data-limit'
                            )||'3',
                            10
                        )||3;

                    loadMore.disabled=true;

                    const params=
                        new URLSearchParams({
                            from:state.from,
                            to:state.to,
                            offset:String(
                                offset
                            ),
                            limit:String(
                                limit
                            ),
                            channel:
                                state.channel,
                            period:
                                state.period
                        });

                    try{
                        const response=
                            await fetch(
                                base
                                +'/sales/daily-posts?'
                                +params.toString(),
                                {
                                    cache:'no-store',
                                    headers:{
                                        'Accept':
                                            'application/json'
                                    }
                                }
                            );

                        const data=
                            await response.json();

                        if(
                            !response.ok
                            ||!data
                            ||!data.ok
                        ){
                            throw new Error(
                                data&&data.message
                                    ?data.message
                                    :'Could not load posts.'
                            );
                        }

                        dailyPosts.insertAdjacentHTML(
                            'beforeend',
                            data.html||''
                        );

                        dailyPosts.setAttribute(
                            'data-offset',
                            String(
                                data.next_offset||offset
                            )
                        );

                        loadMore.hidden=
                            !data.has_more;

                        loadMore.disabled=
                            !data.has_more;
                    }catch(error){
                        if(window.CDSPDiagnostics&&typeof window.CDSPDiagnostics.report==='function'){
                            window.CDSPDiagnostics.report({
                                type:'sales_dashboard_fetch_error',
                                message:error&&error.message?error.message:'Could not load posts.',
                                request_url:base+'/sales/daily-posts'
                            });
                        }
                        loadMore.disabled=false;
                        setError(
                            error&&error.message
                                ?error.message
                                :'Could not load posts.'
                        );
                    }
                }
            );
        }

        if(chartBars&&tooltip){
            let tooltipDay=null;
            let tooltipSegment='';
            let hoverDay=null;
            let hoverTimer=null;
            let hoverPoint=null;
            let touchDay=null;

            /**
             * EN: Close or clear the clear hover timer behavior used by the sales dashboard.
             * 中文：关闭或清理sales dashboard 使用的“clear hover timer”行为。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function clearHoverTimer(){
                if(hoverTimer){
                    window.clearTimeout(hoverTimer);
                    hoverTimer=null;
                }
            }

            /**
             * EN: Close or clear the hide pointer tooltip behavior used by the sales dashboard.
             * 中文：关闭或清理sales dashboard 使用的“hide pointer tooltip”行为。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function hidePointerTooltip(){
                clearHoverTimer();
                if(typeof followFrame!=='undefined'&&followFrame){
                    window.cancelAnimationFrame(followFrame);
                    followFrame=0;
                }
                if(typeof followEvent!=='undefined'){
                    followEvent=null;
                }
                hoverDay=null;
                hoverPoint=null;
                tooltipDay=null;
                tooltipSegment='';
                tooltip.classList.add('hidden');
            }

            /**
             * EN: Render the render pointer tooltip behavior used by the sales dashboard.
             * 中文：渲染sales dashboard 使用的“render pointer tooltip”行为。
             *
             * @param {*} day Day value used by this function. / 本函数使用的“day”参数值。
             * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function renderPointerTooltip(day,target){
                const total=
                    parseInt(
                        day.getAttribute(
                            'data-chart-total'
                        )||'0',
                        10
                    )||0;

                const good=
                    parseInt(
                        day.getAttribute(
                            'data-chart-good'
                        )||'0',
                        10
                    )||0;

                const bad=
                    parseInt(
                        day.getAttribute(
                            'data-chart-bad'
                        )||'0',
                        10
                    )||0;

                const unreviewed=
                    parseInt(
                        day.getAttribute(
                            'data-chart-unreviewed'
                        )||'0',
                        10
                    )||0;

                const segment=
                    target&&target.closest
                        ?target.closest(
                            '.sales-chart-segment'
                        )
                        :null;

                let segmentLine='';
                let segmentKey='';

                if(segment&&day.contains(segment)){
                    const type=String(
                        segment.getAttribute(
                            'data-chart-segment'
                        )||''
                    );
                    const count=
                        parseInt(
                            segment.getAttribute(
                                'data-chart-segment-count'
                            )||'0',
                            10
                        )||0;
                    const label=
                        type==='good'
                            ?'Good'
                            :(
                                type==='bad'
                                    ?'Bad'
                                    :'Unreviewed'
                            );

                    segmentKey=type+':'+count;
                    segmentLine=
                        '<span class="sales-chart-tooltip-focus">'
                        +label
                        +': <b>'
                        +count
                        +'</b></span>';
                }

                tooltipDay=day;
                tooltipSegment=segmentKey;
                tooltip.innerHTML=
                    '<strong>'
                    +escapeHtml(
                        day.getAttribute(
                            'data-chart-date'
                        )||''
                    )
                    +'</strong>'
                    +segmentLine
                    +'<span>Total: <b>'
                    +total
                    +'</b></span>'
                    +'<span>Good: <b>'
                    +good
                    +'</b></span>'
                    +'<span>Bad: <b>'
                    +bad
                    +'</b></span>'
                    +'<span>Unreviewed: <b>'
                    +unreviewed
                    +'</b></span>'
                    +'<span>Missing: <b>'
                    +Math.max(
                        0,
                        (parseInt(day.getAttribute('data-chart-target'),10)||state.target)-total
                    )
                    +'</b></span>'
                    +'<span>Daily target: <b>'
                    +(parseInt(day.getAttribute('data-chart-target'),10)||state.target)
                    +'</b></span>';

                tooltip.classList.remove('hidden');
            }

            /**
             * EN: Position the position pointer tooltip behavior used by the sales dashboard.
             * 中文：定位sales dashboard 使用的“position pointer tooltip”行为。
             *
             * @param {*} day Day value used by this function. / 本函数使用的“day”参数值。
             * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
             * @param {*} followPointer Follow pointer value used by this function. / 本函数使用的“follow pointer”参数值。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function positionPointerTooltip(day,event,followPointer){
                if(!day){
                    return;
                }

                const gap=12;
                const edge=8;
                const width=tooltip.offsetWidth||176;
                const height=tooltip.offsetHeight||120;
                const viewportWidth=
                    document.documentElement.clientWidth
                    ||window.innerWidth;
                const viewportHeight=
                    document.documentElement.clientHeight
                    ||window.innerHeight;
                const rect=day.getBoundingClientRect();
                let left;
                let top;

                if(
                    followPointer
                    &&event
                    &&typeof event.clientX==='number'
                    &&typeof event.clientY==='number'
                ){
                    left=event.clientX+gap;
                    top=event.clientY+gap;

                    if(left+width+edge>viewportWidth){
                        left=event.clientX-width-gap;
                    }
                    if(top+height+edge>viewportHeight){
                        top=event.clientY-height-gap;
                    }
                }else{
                    left=rect.left+(rect.width/2)-(width/2);
                    top=rect.top-height-gap;
                    if(top<edge){
                        top=rect.bottom+gap;
                    }
                }

                left=Math.max(
                    edge,
                    Math.min(
                        Math.max(edge,viewportWidth-width-edge),
                        left
                    )
                );
                top=Math.max(
                    edge,
                    Math.min(
                        Math.max(edge,viewportHeight-height-edge),
                        top
                    )
                );

                tooltip.style.left=Math.round(left)+'px';
                tooltip.style.top=Math.round(top)+'px';
            }

            /**
             * EN: Perform the chart day from target behavior used by the sales dashboard.
             * 中文：执行sales dashboard 使用的“chart day from target”行为。
             *
             * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
             *
             * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
             */
            function chartDayFromTarget(target){
                if(!target||!target.closest){
                    return null;
                }
                const day=target.closest('.sales-chart-day');
                return day&&chartBars.contains(day)
                    ?day
                    :null;
            }

            /**
             * EN: Schedule or start the start mouse hover behavior used by the sales dashboard.
             * 中文：调度或启动sales dashboard 使用的“start mouse hover”行为。
             *
             * @param {*} day Day value used by this function. / 本函数使用的“day”参数值。
             * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function startMouseHover(day,event){
                clearHoverTimer();
                touchDay=null;
                tooltip.classList.add('hidden');
                hoverDay=day;
                hoverPoint={
                    clientX:Number(event.clientX)||0,
                    clientY:Number(event.clientY)||0
                };

                hoverTimer=window.setTimeout(
                    function(){
                        hoverTimer=null;
                        if(
                            hoverDay!==day
                            ||!document.documentElement.contains(day)
                        ){
                            return;
                        }
                        renderPointerTooltip(day,day);
                        positionPointerTooltip(
                            day,
                            hoverPoint,
                            true
                        );
                    },
                    3000
                );
            }

            /*
             * Desktop uses classic mouse events intentionally. They are more
             * reliable here than mixing pointerover with the chart's nested
             * SVG-like decoration and survive every AJAX chart redraw because
             * #salesChartBars itself is never replaced.
             */
            chartBars.addEventListener(
                'mouseover',
                function(event){
                    const day=chartDayFromTarget(event.target);
                    if(!day){
                        return;
                    }
                    if(
                        event.relatedTarget
                        &&day.contains(event.relatedTarget)
                    ){
                        return;
                    }
                    startMouseHover(day,event);
                }
            );

            /*
             * Keep the visible desktop tooltip physically attached to the
             * pointer.  The chart itself is AJAX-rendered and some browser
             * combinations do not deliver every mousemove to #salesChartBars
             * while the cursor crosses stacked chart decoration.  A document
             * level mousemove is therefore the single source of pointer
             * coordinates once the 3-second hover has opened the tooltip.
             */
            let followFrame=0;
            let followEvent=null;

            /**
             * EN: Schedule or start the schedule pointer follow behavior used by the sales dashboard.
             * 中文：调度或启动sales dashboard 使用的“schedule pointer follow”行为。
             *
             * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
             *
             * @returns {void} No value is returned. / 无返回值。
             */
            function schedulePointerFollow(event){
                if(
                    !hoverDay
                    ||tooltipDay!==hoverDay
                    ||tooltip.classList.contains('hidden')
                ){
                    return;
                }

                followEvent={
                    clientX:Number(event.clientX)||0,
                    clientY:Number(event.clientY)||0
                };

                if(followFrame){
                    return;
                }

                followFrame=window.requestAnimationFrame(
                    function(){
                        followFrame=0;

                        if(
                            !followEvent
                            ||!hoverDay
                            ||tooltipDay!==hoverDay
                            ||tooltip.classList.contains('hidden')
                        ){
                            return;
                        }

                        const rect=hoverDay.getBoundingClientRect();
                        const x=followEvent.clientX;
                        const y=followEvent.clientY;

                        /* If the pointer has left the active day, let the
                         * normal mouseout path close it instead of leaving a
                         * detached card floating over the page. */
                        if(
                            x<rect.left
                            ||x>rect.right
                            ||y<rect.top
                            ||y>rect.bottom
                        ){
                            return;
                        }

                        positionPointerTooltip(
                            hoverDay,
                            followEvent,
                            true
                        );
                    }
                );
            }

            chartBars.addEventListener(
                'mousemove',
                function(event){
                    const day=chartDayFromTarget(event.target);
                    if(!day){
                        return;
                    }

                    if(hoverDay===day){
                        hoverPoint={
                            clientX:Number(event.clientX)||0,
                            clientY:Number(event.clientY)||0
                        };
                    }

                    schedulePointerFollow(event);
                }
            );

            document.addEventListener(
                'mousemove',
                function(event){
                    if(!hoverDay){
                        return;
                    }

                    if(hoverDay===tooltipDay){
                        hoverPoint={
                            clientX:Number(event.clientX)||0,
                            clientY:Number(event.clientY)||0
                        };
                        schedulePointerFollow(event);
                    }
                },
                {passive:true}
            );

            chartBars.addEventListener(
                'mouseout',
                function(event){
                    const day=chartDayFromTarget(event.target);
                    if(!day){
                        return;
                    }
                    if(
                        event.relatedTarget
                        &&day.contains(event.relatedTarget)
                    ){
                        return;
                    }
                    if(touchDay!==day){
                        hidePointerTooltip();
                    }
                }
            );

            chartBars.addEventListener(
                'focusin',
                function(event){
                    const day=chartDayFromTarget(event.target);
                    if(!day){
                        return;
                    }
                    clearHoverTimer();
                    renderPointerTooltip(day,day);
                    positionPointerTooltip(day,null,false);
                }
            );

            chartBars.addEventListener(
                'focusout',
                function(event){
                    const day=chartDayFromTarget(event.target);
                    if(day&&touchDay!==day){
                        hidePointerTooltip();
                    }
                }
            );

            chartBars.addEventListener(
                'pointerup',
                function(event){
                    const pointerType=String(event.pointerType||'');
                    if(pointerType!=='touch'&&pointerType!=='pen'){
                        return;
                    }

                    const day=chartDayFromTarget(event.target);
                    if(!day){
                        return;
                    }

                    event.preventDefault();
                    if(touchDay===day){
                        touchDay=null;
                        hidePointerTooltip();
                        return;
                    }

                    clearHoverTimer();
                    touchDay=day;
                    hoverDay=null;
                    renderPointerTooltip(day,day);
                    positionPointerTooltip(day,event,false);
                }
            );

            document.addEventListener(
                'pointerdown',
                function(event){
                    if(!touchDay){
                        return;
                    }
                    const pointerType=String(event.pointerType||'');
                    if(pointerType!=='touch'&&pointerType!=='pen'){
                        return;
                    }
                    if(chartDayFromTarget(event.target)){
                        return;
                    }
                    touchDay=null;
                    hidePointerTooltip();
                }
            );

            window.addEventListener(
                'resize',
                hidePointerTooltip,
                {passive:true}
            );
        }

        /*
         * Initial data is server-generated and is authoritative until the
         * first AJAX response.
         */
        const initialNode=
            document.getElementById(
                'salesChartInitialData'
            );

        if(initialNode){
            try{
                const initial=
                    JSON.parse(
                        initialNode.textContent
                        ||'{}'
                    );

                state.target=Math.max(
                    1,
                    parseInt(
                        initial.daily_target,
                        10
                    )||10
                );
                state.targets=(initial.daily_targets&&typeof initial.daily_targets==='object')
                    ?initial.daily_targets
                    :{};

                state.rows=Array.isArray(
                    initial.rows
                )
                    ?initial.rows
                    :[];
            }catch(error){
                state.target=10;
                state.targets={};
                state.rows=[];
            }
        }

        normalizeRange('');
        setPeriod(state.period);
        setChannel(state.channel);
        updateBackToday();
        applyRangePostFilter();
        renderChart();

        /*
         * Re-render after layout settles. This specifically protects the
         * first page paint from old server/CSS width calculations.
         */
        window.requestAnimationFrame(
            function(){
                renderChart({animate:false});
            }
        );

        let resizeTimer=null;

        window.addEventListener(
            'resize',
            function(){
                window.clearTimeout(
                    resizeTimer
                );

                resizeTimer=
                    window.setTimeout(
                        function(){renderChart({animate:false});},
                        80
                    );
            }
        );
    });
})();
