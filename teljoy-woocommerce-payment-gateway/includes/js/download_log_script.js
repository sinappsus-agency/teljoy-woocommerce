
jQuery(document).ready(function($) {
    $('#woocommerce_teljoy_log_file').change(function(){
        var logFile = $(this).val();
        if(logFile){
            var url = my_ajax_object.ajax_url + '?action=download_log&file=' + logFile;
            var iframe = document.createElement('iframe');
            iframe.style.display = "none";
            iframe.src = url;
            document.body.appendChild(iframe);
        }
    });
});
