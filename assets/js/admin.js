jQuery(function ($) {

    // Test API key
    $('#soser-test-api').on('click', function () {
        var $btn = $(this), $res = $('#soser-api-result');
        var key = $('#soser-api-key').val();
        $btn.prop('disabled', true).text('…');
        $res.css('color', '#888').text('Test in corso…');
        $.post(soserV4.ajaxurl, {
            action: 'soser_v4_test_api',
            nonce: soserV4.nonce,
            api_key: key
        }, function (r) {
            if (r.success) $res.css('color', '#00a32a').text(r.data);
            else $res.css('color', '#d63638').text('❌ ' + r.data);
        }).always(function () {
            $btn.prop('disabled', false).text('Testa');
        
    // Business context scanner
    $('#soser-scan-business').on('click', function () {
        var $btn = $(this), $res = $('#soser-biz-result');
        $btn.prop('disabled', true).text('Scansione in corso…');
        $res.css('color', '#888').text('');
        $('#soser-biz-services').hide();

        $.post(soserV4.ajaxurl, {
            action: 'soser_v4_scan_business',
            nonce: soserV4.nonce
        }, function (r) {
            if (r.success) {
                $res.css('color', '#00a32a').text('✅ ' + r.data.message);
                var $list = $('#soser-biz-list').empty();
                r.data.services.forEach(function (s) {
                    $list.append('<span style="background:#e8f4ff;border:1px solid #b8d4f0;border-radius:12px;padding:3px 10px;font-size:11px;color:#1a5c9a;font-weight:600">' + s + '</span>');
                
    // GSC refresh
    $('#soser-gsc-refresh').on('click', function () {
        var $btn = $(this), $res = $('#soser-gsc-refresh-result');
        $btn.prop('disabled', true).text('Caricamento…');
        $res.css('color','#888').text('');
        $.post(soserV4.ajaxurl, {action:'soser_v4_gsc_refresh', nonce:soserV4.nonce}, function(r){
            if (r.success) {
                $res.css('color','#00a32a').text('✅ '+r.data.message);
                if (r.data.top && r.data.top.length) {
                    var html = '';
                    r.data.top.forEach(function(o){ html += '<div>'+o.type+' <strong>'+o.keyword+'</strong> ('+o.impressions+' imp.)</div>'; });
                    $('#soser-gsc-top-list').html(html);
                    $('#soser-gsc-preview').show();
                }
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                $res.css('color','#d63638').text('❌ '+r.data);
            }
        }).always(function(){ $btn.prop('disabled',false).text('🔄 Aggiorna keyword da GSC'); });
    });

    // GSC disconnect
    $('#soser-gsc-disconnect').on('click', function(){
        if (!confirm('Disconnettere Google Search Console?')) return;
        $.post(soserV4.ajaxurl, {action:'soser_v4_gsc_disconnect', nonce:soserV4.nonce}, function(r){
            if (r.success) location.reload();
        });
    });

});
                $('#soser-biz-summary').text(r.data.summary);
                $('#soser-biz-services').show();
            } else {
                $res.css('color', '#d63638').text('❌ ' + r.data);
            }
        }).always(function () {
            $btn.prop('disabled', false).text('🔍 Scansiona servizi dal sito');
        });
    });

});
    });

    // Scan site
    $('#soser-scan-site').on('click', function () {
        var $btn = $(this), $res = $('#soser-scan-result');
        $btn.prop('disabled', true).text('Scansione…');
        $res.css('color', '#888').text('Analisi in corso…');
        $.post(soserV4.ajaxurl, {
            action: 'soser_v4_scan_site',
            nonce: soserV4.nonce
        }, function (r) {
            if (r.success) $res.css('color', '#00a32a').text('✅ ' + r.data.message);
            else $res.css('color', '#d63638').text('❌ ' + r.data);
        }).always(function () {
            $btn.prop('disabled', false).text('🗂️ Riscansiona ora');
        });
    });

});

    // ── V5 Features ───────────────────────────────────────────────

    // AI Planner refresh
    $('#v5-run-plan').on('click', function(){
        var $b=$(this),$r=$('#v5-plan-result');
        $b.prop('disabled',true).text('Calcolo...');
        $.post(soserV4.ajaxurl,{action:'soser_v5_run_plan',nonce:soserV4.nonce},function(r){
            if(r.success){$r.css('color','#00a32a').text('✅ '+r.data.count+' azioni | Prossima: '+r.data.next);setTimeout(()=>location.reload(),1500);}
            else $r.css('color','#d63638').text('❌ '+r.data);
        }).always(()=>$b.prop('disabled',false).text('🔄 Ricalcola piano'));
    });

    // Refresh post
    $(document).on('click','.v5-refresh-btn',function(){
        var $b=$(this),pid=$b.data('pid');
        if(!confirm('Aggiornare questo articolo con AI?'))return;
        $b.prop('disabled',true).text('...');
        $.post(soserV4.ajaxurl,{action:'soser_v5_refresh_post',nonce:soserV4.nonce,post_id:pid},function(r){
            r.success?alert('✅ '+r.data):alert('❌ '+r.data);
            location.reload();
        }).always(()=>$b.prop('disabled',false));
    });

    // CTR optimize single
    $(document).on('click','.v5-ctr-btn',function(){
        var $b=$(this),pid=$b.data('pid'),$res=$('#ctr-result-'+pid);
        $b.prop('disabled',true).text('...');
        $.post(soserV4.ajaxurl,{action:'soser_v5_optimize_ctr',nonce:soserV4.nonce,post_id:pid},function(r){
            if(r.success){
                $res.html('✅ <em>'+r.data.meta.substring(0,80)+'...</em>');
                if(r.data.title_variants&&r.data.title_variants.length){
                    var tv='<br>Titoli: ';r.data.title_variants.forEach(v=>{tv+='<strong>'+v.title+'</strong> ('+v.boost+') | ';});
                    $res.append(tv);
                }
            } else $res.text('❌ '+r.data);
        }).always(()=>$b.prop('disabled',false).text('⚡ Ottimizza'));
    });

    // Sync memory
    $('#v5-sync-memory').on('click',function(){
        var $b=$(this),$r=$('#v5-sync-result');
        $b.prop('disabled',true).text('...');
        $.post(soserV4.ajaxurl,{action:'soser_v5_sync_memory',nonce:soserV4.nonce},function(r){
            r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
        }).always(()=>$b.prop('disabled',false).text('🔄 Sincronizza memoria'));
    });

    // Bulk CTR
    $('#v5-bulk-ctr').on('click',function(){
        var $b=$(this),$r=$('#v5-ctr-result');
        $b.prop('disabled',true).text('...');
        // Trigger first 5 CTR buttons
        var btns=$('.v5-ctr-btn').slice(0,5);
        btns.each(function(){$(this).trigger('click');});
        $r.css('color','#00a32a').text('✅ Ottimizzazione avviata per 5 articoli');
        $b.prop('disabled',false).text('⚡ Ottimizza i 5 peggiori');
    });

