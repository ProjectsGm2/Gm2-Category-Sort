jQuery(function($){
    var form = $('#gm2-product-category-import-form');
    if (!form.length) return;

    var progress = $('#gm2-import-progress');
    var progressText = $('.gm2-progress-text');
    var message = $('#gm2-import-message');
    var limit = gm2ProductCategoryImport.limit || 50;

    form.on('submit', function(e){
        e.preventDefault();
        message.text('');
        progress.val(0).show();
        progressText.text('0%');

        var fileInput = form.find('input[name="gm2_product_category_file"]')[0];
        if (!fileInput.files.length) {
            message.text(gm2ProductCategoryImport.error);
            return;
        }
        var overwrite = form.find('input[name="gm2_overwrite"]').is(':checked') ? 1 : 0;

        var data = new FormData();
        data.append('action','gm2_product_category_import_step');
        data.append('nonce', gm2ProductCategoryImport.nonce);
        data.append('offset', 0);
        data.append('overwrite', overwrite);
        data.append('file', fileInput.files[0]);
        step(data, overwrite);
    });

    function step(data, overwrite){
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function(resp){
                if(!resp.success){
                    progress.hide();
                    message.text(resp.data || gm2ProductCategoryImport.error);
                    return;
                }
                var d = resp.data;
                if(d.total){
                    var pct = Math.min(100, Math.round(d.offset / d.total * 100));
                    progress.val(pct);
                    progressText.text(pct + '%');
                }
                if(d.done){
                    progress.val(100);
                    progressText.text('100%');
                    message.text(gm2ProductCategoryImport.completed);
                }else{
                    var next = new FormData();
                    next.append('action','gm2_product_category_import_step');
                    next.append('nonce', gm2ProductCategoryImport.nonce);
                    next.append('offset', d.offset);
                    next.append('overwrite', overwrite);
                    next.append('path', d.path);
                    next.append('total', d.total);
                    setTimeout(function(){ step(next, overwrite); }, 200);
                }
            },
            error: function(){
                progress.hide();
                message.text(gm2ProductCategoryImport.error);
            }
        });
    }
});
