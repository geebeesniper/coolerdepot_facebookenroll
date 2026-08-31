(function(){
    'use strict';

    function ready(fn){
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

        function escapeHtml(value){
            return String(value??'')
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;');
        }

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

            if(period==='day'){
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

        function titleForPeriod(){
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
                chartTitle.textContent=
                    titleForPeriod();
            }
        }

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

        function renderAxis(cap,target){
            const step=
                tickStep(cap);

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
                        +(Math.abs(
                            value-target
                        )<0.0001
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
                        +(Math.abs(
                            value-target
                        )<0.0001
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

        function renderChart(){
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

            const target=Math.max(
                1,
                parseInt(
                    state.target,
                    10
                )||10
            );

            const cap=Math.max(
                target,
                target*1.2
            );

            renderAxis(
                cap,
                target
            );

            if(targetValue){
                targetValue.textContent=
                    String(target);
            }

            if(targetLine){
                targetLine.style.top=
                    (
                        plotHeight
                        *(1-(target/cap))
                    )+'px';
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
                    +'">'
                    +'<div'
                    +' class="sales-chart-day-plot">'
                    +'<div'
                    +' class="sales-chart-stack">'
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

        function rangeStatusLabel(filter){
            if(filter==='good'){return 'Good';}
            if(filter==='bad'){return 'Issues';}
            if(filter==='unreviewed'){return 'Unreviewed';}
            return 'All';
        }

        function applyRangePostFilter(){
            const section=document.getElementById('salesRangePostSection');
            if(!section){return;}
            const filter=String(state.postFilter||'all');
            const cards=Array.from(section.querySelectorAll('.sales-range-post-grid > .sales-self-post-card'));
            let visible=0;
            cards.forEach(function(card){
                const status=String(card.getAttribute('data-sales-post-status')||'unreviewed');
                const show=filter==='all'||status===filter;
                card.classList.toggle('sales-card-filtered-out',!show);
                card.setAttribute('aria-hidden',show?'false':'true');
                if(show){visible++;}
            });
            section.querySelectorAll('[data-sales-post-filter]').forEach(function(button){
                const active=String(button.getAttribute('data-sales-post-filter')||'all')===filter;
                button.classList.toggle('active',active);
                button.setAttribute('aria-pressed',active?'true':'false');
            });
            section.setAttribute('data-active-post-filter',filter);
            const filterEmpty=section.querySelector('[data-sales-post-filter-empty]');
            const rangeEmpty=section.querySelector('[data-sales-range-empty]');
            if(filterEmpty){
                const copy=filterEmpty.querySelector('[data-sales-post-filter-empty-copy]');
                if(copy){copy.textContent='No '+rangeStatusLabel(filter)+' posts in this range.';}
                filterEmpty.classList.toggle('hidden',!(cards.length>0&&visible===0));
            }
            if(rangeEmpty){rangeEmpty.classList.toggle('hidden',cards.length>0);}
        }

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

                setError(
                    error&&error.message
                        ?error.message
                        :'Could not load posts.'
                );
            }
        }

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
                        ['day','week','month']
                            .includes(
                                state.period
                            )
                            ?state.period
                            :'day';

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
            chartBars.addEventListener(
                'pointerover',
                function(event){
                    const day=
                        event.target.closest(
                            '.sales-chart-day'
                        );

                    if(!day){
                        return;
                    }

                    const rect=
                        day.getBoundingClientRect();

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

                    const segment=event.target.closest('.sales-chart-segment');
                    let segmentLine='';
                    if(segment&&day.contains(segment)){
                        const type=String(segment.getAttribute('data-chart-segment')||'');
                        const count=parseInt(segment.getAttribute('data-chart-segment-count')||'0',10)||0;
                        const label=type==='good'?'Good':(type==='bad'?'Issues':'Unreviewed');
                        segmentLine='<span class="sales-chart-tooltip-focus">'+label+': <b>'+count+'</b></span>';
                    }

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
                        +'<span>Issues: <b>'
                        +bad
                        +'</b></span>'
                        +'<span>Unreviewed: <b>'
                        +unreviewed
                        +'</b></span>'
                        +'<span>Missing: <b>'
                        +Math.max(
                            0,
                            state.target-total
                        )
                        +'</b></span>';

                    tooltip.classList
                        .remove(
                            'hidden'
                        );

                    tooltip.style.left=
                        (
                            rect.left
                            +rect.width/2
                            -tooltip
                                .offsetWidth/2
                        )
                        +'px';

                    tooltip.style.top=
                        (
                            rect.top
                            -tooltip
                                .offsetHeight
                            -8
                            +window.scrollY
                        )
                        +'px';
                }
            );

            chartBars.addEventListener(
                'pointerout',
                function(event){
                    if(
                        event.relatedTarget
                        &&chartBars.contains(
                            event.relatedTarget
                        )
                    ){
                        return;
                    }

                    tooltip.classList.add(
                        'hidden'
                    );
                }
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

                state.rows=Array.isArray(
                    initial.rows
                )
                    ?initial.rows
                    :[];
            }catch(error){
                state.target=10;
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
                renderChart();

                window.requestAnimationFrame(
                    renderChart
                );
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
                        renderChart,
                        80
                    );
            }
        );
    });
})();
