jQuery(function($){
    var btn = $('#gm2-one-click-btn');
    var msg = $('#gm2-one-click-message');
    var progress = $('#gm2-one-click-progress');
    if(!btn.length) return;

    function step(offset, reset){
        $.post(ajaxurl, {
            action: 'gm2_one_click_assign',
            nonce: gm2OneClickAssign.nonce,
            offset: offset,
            reset: reset ? 1 : 0
        }).done(function(resp){
            if(!resp.success){
                progress.hide();
                msg.text(resp.data || gm2OneClickAssign.error);
                return;
            }
            if(resp.data.total){
                var percent = Math.round((resp.data.offset/resp.data.total)*100);
                progress.attr('value', percent).show();
            }
            if(!resp.data.done){
                step(resp.data.offset, false);
            }else{
                progress.hide();
                msg.text(gm2OneClickAssign.completed);
            }
        }).fail(function(){
            progress.hide();
            msg.text(gm2OneClickAssign.error);
        });
    }

    btn.on('click', function(e){
        e.preventDefault();
        msg.text(gm2OneClickAssign.running);
        progress.attr('value',0).show();
        step(0, true);
    });
});
