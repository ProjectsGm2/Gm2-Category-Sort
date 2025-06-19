jQuery(function($){
    var form = $('#gm2-product-export-form');
    var spinner = $('#gm2-export-spinner');
    if(!form.length) return;

    form.on('submit', function(){
        spinner.show();
    });
});
