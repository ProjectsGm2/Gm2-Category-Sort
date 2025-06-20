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
    
  function renderTerms(container,attrList,selected,collapsed){
        container.empty();
        attrList.forEach(function(attr){
            var info=attrs[attr];
            if(!info) return;
            var group=$('<span class="gm2-attr-group">');
            var toggle=$('<span class="gm2-toggle-attr" data-attr="'+attr+'">&#9660;</span>');
            var remove=$('<span class="gm2-remove-attr" data-attr="'+attr+'">&times;</span>');
            var sel=$('<select multiple>').attr('data-attr',attr);
            $.each(info.terms,function(slug,name){
                var opt=$('<option>').val(slug).text(name);
                if(selected && selected[attr] && selected[attr].indexOf(slug)!==-1){
                    opt.prop('selected',true);
                }
                sel.append(opt);
            });
            group.append(toggle);
            group.append(remove);
            group.append(sel);
            if(collapsed){
                group.addClass('collapsed');
            }
            container.append(group);
        });
    }

    function renderTags(container, selected){
        container.empty();
        $.each(selected,function(attr,terms){
            if(!terms.length) return;
            var info=attrs[attr]||{};
            var label=info.label||attr;
            var names=[];
            $.each(terms,function(i,slug){
                names.push(info.terms && info.terms[slug] ? info.terms[slug] : slug);
            });
            var tag=$('<span class="gm2-tag" data-attr="'+attr+'">');
            var remove=$('<span class="gm2-remove-tag" data-attr="'+attr+'">&times;</span>');
            tag.append(remove).append(' '+label+': '+names.join(', '));
            container.append(tag);
        });
    }

    function updateSummary(row){
        var inc=gatherSelected(row.find('.gm2-include-terms'));
        var exc=gatherSelected(row.find('.gm2-exclude-terms'));
        renderTags(row.find('.gm2-include-tags'),inc);
        renderTags(row.find('.gm2-exclude-tags'),exc);
    }

    form.find('.gm2-attr-select').each(function(){
        populate($(this));
    });

    // Allow selecting multiple attributes without holding Ctrl/Command
    form.on('mousedown', '.gm2-attr-select option', function(e){
        e.preventDefault();
        var opt=$(this);
        opt.prop('selected', !opt.prop('selected'));
        opt.parent().trigger('change');
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
                    renderTerms(row.find('.gm2-include-terms'),incAttrs,r.include_attrs,true);
                    renderTerms(row.find('.gm2-exclude-terms'),excAttrs,r.exclude_attrs,true);
                    updateSummary(row);
                }
            }
        });
    }

    load();

    function gatherSelected(container){
        var selected={};
        container.find('.gm2-attr-group select').each(function(){
            var attr=$(this).data('attr');
            selected[attr]=$(this).val()||[];
        });
        return selected;
    }

    form.on('change','.gm2-include-attr',function(){
        var row=$(this).closest('tr');
        var attrsSel=$(this).val()||[];
        var current=gatherSelected(row.find('.gm2-include-terms'));
        renderTerms(row.find('.gm2-include-terms'),attrsSel,current,false);
        updateSummary(row);
    });

    form.on('change','.gm2-exclude-attr',function(){
        var row=$(this).closest('tr');
        var attrsSel=$(this).val()||[];
        var current=gatherSelected(row.find('.gm2-exclude-terms'));
        renderTerms(row.find('.gm2-exclude-terms'),attrsSel,current,false);
        updateSummary(row);
    });

    form.on('change','.gm2-include-terms select,.gm2-exclude-terms select',function(){
        updateSummary($(this).closest('tr'));
    });

    form.on('click','.gm2-attr-group .gm2-toggle-attr',function(){
        var group=$(this).closest('.gm2-attr-group');
        group.toggleClass('collapsed');
    });

    form.on('click','.gm2-attr-group .gm2-remove-attr',function(){
        var group=$(this).closest('.gm2-attr-group');
        group.toggleClass('collapsed');
    });

    form.on('click','.gm2-remove-tag',function(){
        var tag=$(this).closest('.gm2-tag');
        var attr=$(this).data('attr');
        var row=tag.closest('tr');
        var isInc=tag.closest('.gm2-include-tags').length>0;
        var attrSelect=isInc?row.find('.gm2-include-attr'):row.find('.gm2-exclude-attr');
        var termsContainer=isInc?row.find('.gm2-include-terms'):row.find('.gm2-exclude-terms');
        attrSelect.find('option[value="'+attr+'"]').prop('selected',false);
        termsContainer.find('select[data-attr="'+attr+'"]').closest('.gm2-attr-group').remove();
        tag.remove();
        updateSummary(row);
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
