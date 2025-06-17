jQuery(function($){
    var btn = $('#gm2-one-click-btn');
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
});
