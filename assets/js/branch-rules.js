jQuery(function($){
    var form = $('#gm2-branch-rules-form');
    if(!form.length) return;
    var msg = $('#gm2-branch-rules-msg');
    var attrs = gm2BranchRules.attributes || {};

    function populate(select){
        $.each(attrs,function(slug,data){
            select.append($('<option>').val(slug).text(data.label));
        });
    }

    function renderTerms(container,attrList,selected){
        container.empty();
        attrList.forEach(function(attr){
            var info=attrs[attr];
            if(!info) return;
            var sel=$('<select multiple>').attr('data-attr',attr);
            $.each(info.terms,function(slug,name){
                var opt=$('<option>').val(slug).text(name);
                if(selected && selected[attr] && selected[attr].indexOf(slug)!==-1){
                    opt.prop('selected',true);
                }
                sel.append(opt);
            });
            container.append(sel);
        });
    }

    form.find('.gm2-attr-select').each(function(){
        populate($(this));
    });

    function load(){
        $.post(ajaxurl,{action:'gm2_branch_rules_get',nonce:gm2BranchRules.nonce})
        .done(function(resp){
            if(resp.success){
                for(var slug in resp.data){
                    var r=resp.data[slug];
                    var row=form.find('tr[data-slug="'+slug+'"]');
                    row.find('textarea[data-type="include"]').val(r.include);
                    row.find('textarea[data-type="exclude"]').val(r.exclude);
                    var incAttrs=Object.keys(r.include_attrs||{});
                    var excAttrs=Object.keys(r.exclude_attrs||{});
                    row.find('.gm2-include-attr').val(incAttrs);
                    row.find('.gm2-exclude-attr').val(excAttrs);
                    renderTerms(row.find('.gm2-include-terms'),incAttrs,r.include_attrs);
                    renderTerms(row.find('.gm2-exclude-terms'),excAttrs,r.exclude_attrs);
                }
            }
        });
    }

    load();

    form.on('change','.gm2-include-attr',function(){
        var row=$(this).closest('tr');
        var attrsSel=$(this).val()||[];
        renderTerms(row.find('.gm2-include-terms'),attrsSel);
    });

    form.on('change','.gm2-exclude-attr',function(){
        var row=$(this).closest('tr');
        var attrsSel=$(this).val()||[];
        renderTerms(row.find('.gm2-exclude-terms'),attrsSel);
    });

    form.on('submit',function(e){
        e.preventDefault();
        var rules={};
        form.find('tr[data-slug]').each(function(){
            var row=$(this);
            var slug=row.data('slug');
            var incAttrs={};
            var excAttrs={};
            row.find('.gm2-include-terms select').each(function(){
                var attr=$(this).data('attr');
                var terms=$(this).val()||[];
                if(terms.length) incAttrs[attr]=terms;
            });
            row.find('.gm2-exclude-terms select').each(function(){
                var attr=$(this).data('attr');
                var terms=$(this).val()||[];
                if(terms.length) excAttrs[attr]=terms;
            });
            rules[slug]={
                include:row.find('textarea[data-type="include"]').val(),
                exclude:row.find('textarea[data-type="exclude"]').val(),
                include_attrs:incAttrs,
                exclude_attrs:excAttrs
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
