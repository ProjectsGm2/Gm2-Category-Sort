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

    function step(offset, reset, overwrite, fuzzy){
        $.post(ajaxurl, {
            action: 'gm2_auto_assign_step',
            nonce: gm2AutoAssign.nonce,
            offset: offset,
            reset: reset ? 1 : 0,
            overwrite: overwrite ? 1 : 0,
            fuzzy: fuzzy ? 1 : 0
        }).done(function(resp){
            if(!resp.success){
                log.append('<div class="error">'+ (resp.data || gm2AutoAssign.error) +'</div>');
                return;
            }
            append(resp.data.items);
            if(!resp.data.done){
                step(resp.data.offset, false, overwrite, fuzzy);
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
        var overwrite = $('input[name="gm2_overwrite"]:checked').val();
        var fuzzy = $('#gm2_fuzzy').is(':checked');
        step(0, true, overwrite, fuzzy);
    });

    // --- Manual search and assign ---
    var searchBtn = $('#gm2-search-btn');
    var searchFields = $('#gm2-search-fields');
    var searchTerms = $('#gm2-search-terms');
    var productList = $('#gm2-product-list');
    var productSearch = $('#gm2-product-search');
    var assignBtn = $('#gm2-assign-btn');
    var catSelect = $('#gm2-category-select');
    var searchProgress = $('#gm2-search-progress');

    if(searchBtn.length){
        var products = {};

        function renderList(){
            productList.empty();
            Object.values(products).forEach(function(p){
                var li = $('<li>').attr('data-id', p.id).text(p.sku+' - '+p.title);
                $('<a href="#" class="gm2-remove">&times;</a>').appendTo(li);
                productList.append(li);
            });
        }

        function addItems(items){
            items.forEach(function(p){
                if(!products[p.id]) products[p.id] = p;
            });
            renderList();
        }

        productList.on('click','.gm2-remove', function(e){
            e.preventDefault();
            var id = $(this).parent().data('id');
            delete products[id];
            renderList();
        });

        function runSearch(fields, term, offset){
            $.post(ajaxurl, {
                action: 'gm2_auto_assign_search',
                nonce: gm2AutoAssign.nonce,
                fields: fields,
                search: term,
                offset: offset || 0,
                batch: 100
            }).done(function(resp){
                if(resp.success){
                    addItems(resp.data.items);
                    if(resp.data.total){
                        var percent = Math.round((resp.data.processed/resp.data.total)*100);
                        searchProgress.attr('value', percent).show();
                        if(!resp.data.done){
                            runSearch(fields, term, resp.data.processed);
                        }else{
                            searchProgress.hide();
                        }
                    }else{
                        searchProgress.hide();
                    }
                }else{
                    searchProgress.hide();
                }
            }).fail(function(){ searchProgress.hide(); });
        }

        searchBtn.on('click', function(e){
            e.preventDefault();
            var fields = searchFields.val() || [];
            var term = searchTerms.val();
            products = {};
            renderList();
            searchProgress.attr('value',0).show();
            runSearch(fields, term, 0);
        });

        productSearch.on('keypress', function(e){
            if(e.which === 13){
                e.preventDefault();
                var q = $(this).val();
                products = {};
                renderList();
                searchProgress.attr('value',0).show();
                runSearch(['title','description','attributes'], q, 0);
            }
        });

        assignBtn.on('click', function(e){
            e.preventDefault();
            var ids = Object.keys(products);
            var cats = catSelect.val() || [];
            if(!ids.length || !cats.length) return;
            var overwrite = $('input[name="gm2_overwrite"]:checked').val();
            $.post(ajaxurl, {
                action: 'gm2_auto_assign_selected',
                nonce: gm2AutoAssign.nonce,
                products: ids,
                categories: cats,
                overwrite: overwrite
            }).done(function(resp){
                if(resp.success){
                    products = {};
                    renderList();
                }
            });
        });
    }
});
