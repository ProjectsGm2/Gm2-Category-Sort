jQuery(function($){
    var form = $('#gm2-branch-rules-form');
    if(!form.length) return;
    var msg = $('#gm2-branch-rules-msg');

    function load(){
        $.post(ajaxurl,{action:'gm2_branch_rules_get',nonce:gm2BranchRules.nonce})
        .done(function(resp){
            if(resp.success){
                for(var slug in resp.data){
                    var r=resp.data[slug];
                    form.find('tr[data-slug="'+slug+'"]').find('textarea[data-type="include"]').val(r.include);
                    form.find('tr[data-slug="'+slug+'"]').find('textarea[data-type="exclude"]').val(r.exclude);
                }
            }
        });
    }

    load();

    form.on('submit',function(e){
        e.preventDefault();
        var rules={};
        form.find('tr[data-slug]').each(function(){
            var slug=$(this).data('slug');
            rules[slug]={
                include:$(this).find('textarea[data-type="include"]').val(),
                exclude:$(this).find('textarea[data-type="exclude"]').val()
            };
        });
        $.post(ajaxurl,{action:'gm2_branch_rules_save',nonce:gm2BranchRules.nonce,rules:rules})
        .done(function(resp){
            if(resp.success){
                msg.text(gm2BranchRules.saved);
            }else{
                msg.text(gm2BranchRules.error);
            }
        }).fail(function(){msg.text(gm2BranchRules.error);});
    });
});
