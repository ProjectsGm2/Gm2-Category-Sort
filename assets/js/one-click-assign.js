jQuery(function($){
    var btn = $('#gm2-one-click-btn');
    var msg = $('#gm2-one-click-message');
    if(!btn.length) return;

    btn.on('click', function(e){
        e.preventDefault();
        msg.text(gm2OneClickAssign.running);
        $.post(ajaxurl, {
            action: 'gm2_one_click_assign',
            nonce: gm2OneClickAssign.nonce
        }).done(function(resp){
            if(resp.success){
                msg.text(gm2OneClickAssign.completed);
            }else{
                msg.text(resp.data || gm2OneClickAssign.error);
            }
        }).fail(function(){
            msg.text(gm2OneClickAssign.error);
        });
    });
});
