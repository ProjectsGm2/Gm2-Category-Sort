jQuery(function($){
    var btn = $('#gm2-one-click-btn');
    var assignBtn = $('#gm2-oca-assign');
    var fieldsSel = $('#gm2-oca-fields');
    var msg = $('#gm2-one-click-message');
    var results = $('#gm2-branch-results');
    var progress = $('#gm2-oca-progress');
    var list = $('#gm2-oca-list');
    var overwriteRadios = $('input[name="gm2_oca_overwrite"]');
    var resetBtn = $('#gm2-oca-reset');
    var resetProgress = $('#gm2-oca-reset-progress');
    if(!btn.length) return;

    function loadBranches(){
        results.text(gm2OneClickAssign.loadingBranches);
        $.post(ajaxurl, {
            action: 'gm2_one_click_branches',
            nonce: gm2OneClickAssign.nonce
        }).done(function(resp){
            if(resp.success){
                results.html(resp.data.html);
            }else{
                results.text(resp.data || gm2OneClickAssign.error);
            }
        }).fail(function(){
            results.text(gm2OneClickAssign.error);
        });
    }

    // Display existing branch list on initial page load if CSV files exist
    loadBranches();

    btn.on('click', function(e){
        e.preventDefault();
        msg.text(gm2OneClickAssign.running);
        $.post(ajaxurl, {
            action: 'gm2_one_click_assign',
            nonce: gm2OneClickAssign.nonce
        }).done(function(resp){
            if(resp.success){
                msg.text(gm2OneClickAssign.completed);
                loadBranches();
            }else{
                msg.text(resp.data || gm2OneClickAssign.error);
            }
        }).fail(function(){
            msg.text(gm2OneClickAssign.error);
        });
    });

    function append(items){
        items.forEach(function(item){
            var cats = item.cats.join(', ');
            list.append('<li>'+ item.sku +' - '+ item.title +' => '+ cats +'</li>');
        });
    }

    function step(offset, overwrite, fields){
        $.post(ajaxurl, {
            action: 'gm2_one_click_assign_categories',
            nonce: gm2OneClickAssign.nonce,
            offset: offset,
            overwrite: overwrite ? 1 : 0,
            fields: fields
        }).done(function(resp){
            if(!resp.success){
                progress.hide();
                msg.text(resp.data || gm2OneClickAssign.error);
                return;
            }
            var d = resp.data;
            append(d.items);
            if(d.total){
                var pct = Math.min(100, Math.round(d.offset / d.total * 100));
                progress.val(pct).show();
            }
            if(!d.done){
                step(d.offset, overwrite, fields);
            }else{
                progress.val(100);
                msg.text(gm2OneClickAssign.assignDone);
            }
        }).fail(function(){
            progress.hide();
            msg.text(gm2OneClickAssign.error);
        });
    }

    assignBtn.on('click', function(e){
        e.preventDefault();
        msg.text(gm2OneClickAssign.assigning);
        list.empty();
        progress.val(0).show();
        var fields = fieldsSel.val() || [];
        var overwrite = overwriteRadios.filter(':checked').val() === '1';
        step(0, overwrite, fields);
    });

    function resetStep(offset, reset){
        $.post(ajaxurl, {
            action: 'gm2_reset_product_categories',
            nonce: gm2OneClickAssign.nonce,
            offset: offset,
            reset: reset ? 1 : 0
        }).done(function(resp){
            if(!resp.success){
                resetProgress.hide();
                msg.text(gm2OneClickAssign.error);
                return;
            }
            if(resp.data.total){
                var percent = Math.round((resp.data.offset/resp.data.total)*100);
                resetProgress.val(percent).show();
            }
            if(!resp.data.done){
                resetStep(resp.data.offset, false);
            }else{
                resetProgress.hide();
                msg.text(gm2OneClickAssign.resetDone);
            }
        }).fail(function(){
            resetProgress.hide();
            msg.text(gm2OneClickAssign.error);
        });
    }

    resetBtn.on('click', function(e){
        e.preventDefault();
        msg.text('');
        list.empty();
        resetProgress.val(0).show();
        resetStep(0, true);
    });
});
