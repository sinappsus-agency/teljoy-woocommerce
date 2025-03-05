jQuery(document).ready(function($) {
    var modal = $('#store-location-popup');
    var span = $('#close-store-location-popup');

    modal.show();

    span.on('click', function() {
        modal.hide();
    });

    $(window).on('click', function(event) {
        if (event.target == modal[0]) {
            modal.hide();
        }
    });

    $('#store-location-dropdown').on('change', function() {
        var storeLocationId = $(this).val();
        if (storeLocationId) {
            $.post(ajaxurl, {
                action: 'save_store_location',
                store_location_id: storeLocationId
            }, function(response) {
                if (response.success) {
                    modal.hide();
                }
            });
        }
    });
});