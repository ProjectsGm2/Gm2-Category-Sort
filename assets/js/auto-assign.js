jQuery(function($){
    var log = $('#gm2-auto-assign-log');
    var btn = $('#gm2-auto-assign-start');
    if(!btn.length) return;

    function append(lines){
        lines.forEach(function(item){
            var cats = item.cats.join(', ');
            log.append('<div>'+ item.sku +' - '+ item.title +' => '+ cats +'</div>');
        });
    }

    function step(offset, reset){
        $.post(ajaxurl, {
            action: 'gm2_auto_assign_step',
            nonce: gm2AutoAssign.nonce,
            offset: offset,
            reset: reset ? 1 : 0
        }).done(function(resp){
            if(!resp.success){
                log.append('<div class="error">'+ (resp.data || gm2AutoAssign.error) +'</div>');
                return;
            }
            append(resp.data.items);
            if(!resp.data.done){
                step(resp.data.offset, false);
            }else{
                log.append('<div>'+ gm2AutoAssign.completed +'</div>');
            }
        }).fail(function(){
            log.append('<div class="error">'+ gm2AutoAssign.error +'</div>');
        });
    }

    btn.on('click', function(e){
        e.preventDefault();
        log.empty();
        step(0, true);
    });
});
