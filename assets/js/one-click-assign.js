jQuery(function($){
    var btn = $('#gm2-one-click-btn');
    var assignBtn = $('#gm2-oca-assign');
    var fieldsSel = $('#gm2-oca-fields');
    var msg = $('#gm2-one-click-message');
    var results = $('#gm2-branch-results');
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

    assignBtn.on('click', function(e){
        e.preventDefault();
        msg.text(gm2OneClickAssign.assigning);
        var fields = fieldsSel.val() || [];
        $.post(ajaxurl, {
            action: 'gm2_one_click_assign_categories',
            nonce: gm2OneClickAssign.nonce,
            fields: fields
        }).done(function(resp){
            if(resp.success){
                msg.text(gm2OneClickAssign.assignDone);
            }else{
                msg.text(resp.data || gm2OneClickAssign.error);
            }
        }).fail(function(){
            msg.text(gm2OneClickAssign.error);
        });
    });
});
