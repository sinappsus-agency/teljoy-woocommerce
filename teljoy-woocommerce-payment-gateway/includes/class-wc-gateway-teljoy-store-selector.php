<?php

$options = get_option('woocommerce_teljoy_settings', 'gets the option');

if (isset($options['teljoy_store_locator'])) {
	$teljoy_store_locator = $options['teljoy_store_locator'];
}

if (isset($teljoy_store_locator) && $teljoy_store_locator == 'yes') {


        // Register Custom Post Type for Store Locations
        function create_store_locations_post_type() {
            $labels = array(
                'name'                  => _x('Store Locations', 'Post Type General Name', 'text_domain'),
                'singular_name'         => _x('Store Location', 'Post Type Singular Name', 'text_domain'),
                'menu_name'             => __('Store Locations', 'text_domain'),
                'name_admin_bar'        => __('Store Location', 'text_domain'),
                'archives'              => __('Store Location Archives', 'text_domain'),
                'attributes'            => __('Store Location Attributes', 'text_domain'),
                'parent_item_colon'     => __('Parent Store Location:', 'text_domain'),
                'all_items'             => __('All Store Locations', 'text_domain'),
                'add_new_item'          => __('Add New Store Location', 'text_domain'),
                'add_new'               => __('Add New', 'text_domain'),
                'new_item'              => __('New Store Location', 'text_domain'),
                'edit_item'             => __('Edit Store Location', 'text_domain'),
                'update_item'           => __('Update Store Location', 'text_domain'),
                'view_item'             => __('View Store Location', 'text_domain'),
                'view_items'            => __('View Store Locations', 'text_domain'),
                'search_items'          => __('Search Store Location', 'text_domain'),
                'not_found'             => __('Not found', 'text_domain'),
                'not_found_in_trash'    => __('Not found in Trash', 'text_domain'),
                'featured_image'        => __('Featured Image', 'text_domain'),
                'set_featured_image'    => __('Set featured image', 'text_domain'),
                'remove_featured_image' => __('Remove featured image', 'text_domain'),
                'use_featured_image'    => __('Use as featured image', 'text_domain'),
                'insert_into_item'      => __('Insert into store location', 'text_domain'),
                'uploaded_to_this_item' => __('Uploaded to this store location', 'text_domain'),
                'items_list'            => __('Store locations list', 'text_domain'),
                'items_list_navigation' => __('Store locations list navigation', 'text_domain'),
                'filter_items_list'     => __('Filter store locations list', 'text_domain'),
            );
            $args = array(
                'label'                 => __('Store Location', 'text_domain'),
                'description'           => __('Post Type for Store Locations', 'text_domain'),
                'labels'                => $labels,
                'supports'              => array('title', 'editor', 'thumbnail'),
                'hierarchical'          => false,
                'public'                => true,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'menu_position'         => 5,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => true,
                'can_export'            => true,
                'has_archive'           => true,
                'exclude_from_search'   => false,
                'publicly_queryable'    => true,
                'capability_type'       => 'post',
            );
            register_post_type('store_location', $args);

            // Debugging statement
            error_log('Store Locations Post Type Registered');
        }
        add_action('init', 'create_store_locations_post_type', 0);

        // Shortcode to display store location dropdown
        function store_location_dropdown_shortcode($atts = []) {
            // Generate a unique ID for this instance
            $unique_id = 'store-location-dropdown-' . uniqid();
            
            $store_locations = get_posts(array(
                'post_type' => 'store_location',
                'posts_per_page' => -1,
            ));
        
            $active_store_location_id = get_option('active_store_location', '');
        
            ob_start();
            ?>
            <select id="<?php echo $unique_id; ?>" class="store-location-dropdown">
                <option value=""><?php _e('Select Store Location', 'text_domain'); ?></option>
                <?php foreach ($store_locations as $store_location) : ?>
                    <option value="<?php echo esc_attr($store_location->ID); ?>" <?php selected($store_location->ID, $active_store_location_id); ?>>
                        <?php echo esc_html($store_location->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <script>
                jQuery(document).ready(function($) {
                    $('#<?php echo $unique_id; ?>').on('change', function() {
                        var storeLocationId = $(this).val();
                        if (storeLocationId) {
                            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                action: 'save_store_location',
                                store_location_id: storeLocationId
                            }, function(response) {
                                console.log('Store location updated');
                                // Refresh page to update any other dropdowns
                                if (response.success) {
                                    location.reload();
                                }
                            });
                        }
                    });
                });
            </script>
            <?php
            return ob_get_clean();
        }
        add_shortcode('store_location_dropdown', 'store_location_dropdown_shortcode');

        // AJAX handler to save store location
        function save_store_location() {
            if (isset($_POST['store_location_id'])) {
                update_option('active_store_location', sanitize_text_field($_POST['store_location_id']));
                wp_send_json_success();
            }
            wp_send_json_error();
        }
        add_action('wp_ajax_save_store_location', 'save_store_location');
        add_action('wp_ajax_nopriv_save_store_location', 'save_store_location');

        // Enqueue scripts and styles for the popup
        function enqueue_store_location_scripts() {
            wp_enqueue_script('jquery');
            wp_enqueue_script('store-location-popup', plugins_url('/js/store-location-popup.js', __FILE__), array('jquery'), '1.0.0', true);
            wp_enqueue_style('store-location-popup', plugins_url('/css/store-location-popup.css', __FILE__));
        }
        add_action('wp_enqueue_scripts', 'enqueue_store_location_scripts');

      
// Inject the popup HTML and JavaScript into the checkout page
// Inject the popup HTML and JavaScript into the checkout page
function inject_store_location_popup() {
    $active_store_location_id = get_option('active_store_location', '');
    
    // Debug the value
    error_log('Active store location ID: ' . $active_store_location_id);
    
    // Don't even render the modal if we have an active store
    if (!empty($active_store_location_id)) {
        return; // Exit the function completely if we have an active store
    }
    
    ?>
    <div id="store-location-popup" class="modal">
        <div class="modal-content">
        <a href="<?php echo home_url(); ?>" id="return-to-shopping" style="position:relative; right:0px"><?php _e('Return to Shopping', 'text_domain'); ?></a>
        <hr>
        <h2><?php _e('Please Select Store in order to continue to checkout', 'text_domain'); ?></h2>
        <span id="close-store-location-popup">&times;</span>
        <?php echo do_shortcode('[store_location_dropdown]'); ?>
        <button id="continue-to-checkout"><?php _e('Continue to Checkout', 'text_domain'); ?></button>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
        var modal = $('#store-location-popup');
        var span = $('#close-store-location-popup');
        var continueButton = $('#continue-to-checkout');
        var storeDropdown = $('#store-location-popup .store-location-dropdown');
        
        // Show modal immediately
        modal.show();
        
        span.on('click', function() {
            modal.hide();
        });
        
        continueButton.on('click', function() {
            // Only hide if a store is selected
            if (storeDropdown.val()) {
                modal.hide();
            } else {
                alert('<?php _e('Please select a store location', 'text_domain'); ?>');
            }
        });
        
        $(window).on('click', function(event) {
            if (event.target == modal[0]) {
                modal.hide();
            }
        });
        });
    </script>
    <?php
}
        add_action('woocommerce_before_checkout_form', 'inject_store_location_popup');

}