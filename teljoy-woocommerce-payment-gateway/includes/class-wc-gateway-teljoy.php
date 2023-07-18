<?php
mb_internal_encoding('UTF-8');
class WC_Gateway_Teljoy extends WC_Payment_Gateway
{


	/**
	 * @var boolean Whether or not logging is enabled
	 */
	public static $log_enabled = false;

	/**
	 * @var WC_Logger Logger instance
	 */
	public static $log = true;

	/**
	 * Main WC_Gateway_Teljoy Instance
	 *
	 * Used for WP-Cron jobs when
	 *
	 * @since 1.0
	 * @return WC_Gateway_Teljoy Main instance
	 */


	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->id = 'teljoy';
		$this->version = WC_GATEWAY_TELJOY_VERSION;
		$this->method_title = __('Teljoy', 'woo_teljoy');
		$this->method_description = __('Use Teljoy to processor payments for WooCommerce.', 'woo_teljoy');
		$this->icon = WC_GATEWAY_TELJOY_URL . "/media/teljoy_logo.png";
		$this->debug_email        = $this->get_option('admin_email');
		$this->available_countries  = array('ZA');
		$this->available_currencies = (array)apply_filters('woocommerce_gateway_teljoy_available_currencies', array('ZAR'));
		// Supported functionality
		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();
		$this->init_environment_config();



		//$this->log('get storage option: '.$this->get_option('woocommerce_hold_stock_minutes'),false);


		// Setup default merchant data.
		$this->merchant_api_key      	= $this->get_option('merchant_api_key');
		$this->url              		= $this->environments['api_url'] . 'payment/create';
		$this->validate_url     		= $this->environments['api_url'] . 'product';
		$this->status_url				= $this->environments['api_url'] . 'status/';
		$this->payment_url				= 'https://api.pay.staging.teljoy.co.za/payments/api/payment/';//$this->environments['api_url'] . 'payment/';
		$this->merchant_url				= $this->environments['api_url'] . 'merchant/';
		$this->api_url					= $this->environments['api_url'];
		$this->title            		= $this->get_option('title') ? $this->get_option('title') : __('Try It, Love It, Own It. You will be redirected to Teljoy to	securely complete your payment.', 'woo_teljoy');
		$this->debug_email    			= $this->get_option('debug_email', get_option('admin_email'));
		$this->response_url	    		= add_query_arg('wc-api', 'WC_Gateway_Teljoy', home_url('/'));
		$this->send_debug_email 		= 'yes' === $this->get_option('send_debug_email');
		$this->description      		= $this->get_option('description') ? $this->get_option('description') : __('Try It, Love It, Own It. You will be redirected to Teljoy to	securely complete your payment.', 'woo_teljoy');
		$this->enabled          		= 'yes' === $this->get_option('enabled') ? 'yes' : 'no';
		$this->enable_logging   		= 'yes' === $this->get_option('enable_logging');
		$this->verify_redirect_params 	= 'yes' === $this->get_option('verify_redirect_params');
		$this->enable_product_widget 	= 'yes' === $this->get_option('enable_product_widget');
		$this->teljoy_hold_stock_minutes 	= $this->get_option('teljoy_hold_stock_minutes');
		$this->enable_cart_warnings = $this->get_option('enable_cart_warnings');
		$this->teljoy_hold_stock_recommended_minutes = 10080; //replace this with the value from the api calls when received


		if (is_admin() && current_user_can('manage_options')) {
			$this->verify_client_status();
		} else {
			update_option("woocommerce_hold_stock_minutes", $this->teljoy_hold_stock_minutes);
		}

		add_action('woocommerce_receipt_teljoy', array($this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_teljoy', array($this, 'check_API_response'));
		add_action('admin_notices', array($this, 'teljoy_admin_notices'));

		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_action('wp_ajax_download_log', array($this, 'download_log'));
		// add_action( 'update_option_merchant_api_key', array($this, 'verify_client_status') );
		// Disable Teljoy if cart contains unapproved products
		add_filter('woocommerce_available_payment_gateways', array(
			$this,
			'check_cart_line_item_validity'
		), 99, 1);
	}

	public function enqueue_admin_scripts() {
		$script_path = plugins_url('/js/download_log_script.js', __FILE__);
		$script_url = file_exists( plugin_dir_path(__FILE__) . '/js/download_log_script.js' ) ? $script_path : '';
		wp_register_script('download_log_script', $script_url, array('jquery'), '1.0.0', true);
		
		if ( wp_script_is( 'download_log_script', 'registered' ) ) {
			wp_enqueue_script('download_log_script');
			wp_localize_script('download_log_script', 'my_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
		} 
	}
	
	

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __('Enable/Disable', 'woocommerce-gateway-teljoy'),
				'label'       => __('Enable teljoy', 'woocommerce-gateway-teljoy'),
				'type'        => 'checkbox',
				'description' => __('This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-teljoy'),
				'default'     => 'no',		// User should enter the required information before enabling the gateway.
				'desc_tip'    => true,
			),
			'title' => array(
				'title'       => __('Title', 'woocommerce-gateway-teljoy'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-teljoy'),
				'default'     => __('teljoy', 'woocommerce-gateway-teljoy'),
				'desc_tip'    => true,
			),
			'teljoy_hold_stock_minutes' => array(
				'title'             => __('Hold stock (minutes) override', 'woocommerce'),
				'desc'              => __('Hold stock (for unpaid orders) for x minutes. When this limit is reached, the pending order will be cancelled. Leave blank to disable.', 'woocommerce'),
				'id'                => 'woocommerce_hold_stock_minutes',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 60,
				),
				'css'               => 'width: 80px;',
				'default'           => '2000',
				'autoload'          => false,
				'class'             => 'manage_stock_field',
			),
			'description' => array(
				'title'       => __('Description', 'woocommerce-gateway-teljoy'),
				'type'        => 'text',
				'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-teljoy'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode' => array(
				'title'       => __('Teljoy Sandbox', 'woocommerce-gateway-teljoy'),
				'type'        => 'checkbox',
				'description' => __('Place the payment gateway in development mode.', 'woocommerce-gateway-teljoy'),
				'default'     => 'yes',
			),
			'merchant_api_key' => array(
				'title'       => __('Api Key', 'woocommerce-gateway-teljoy'),
				'type'        => 'text',
				'description' => __('This is the merchant ID, received from teljoy.', 'woocommerce-gateway-teljoy'),
				'default'     => '',
			),
			'enable_product_widget' => array(
				'title' => __('Product Page Widget', 'woo_teljoy'),
				'type' => 'checkbox',
				'label' => __('Enable Product Page Widget', 'woo_teljoy'),
				'default' => 'no',
			),
			'teljoy_on_cart' => [
				'title' => 'Display on Cart',
				'label' => 'Enable',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
				'desc_tip' => true,
			],
			'teljoy_cart_as_combined' => [
				'title' => 'Set cart rate based on total instead of lowest',
				'label' => 'Enable',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
				'desc_tip' => true,
			],
			'is_using_page_builder' => array(
				'title' => __('Product Page Widget using any page builder', 'woo_teljoy'),
				'type' => 'checkbox',
				'label' => __('Enable Product Page Widget using page builder', 'woo_teljoy'),
				'default' => 'no',
				'description' => __('If you use a page builder plugin, the above payment info can be placed using a shortcode instead of relying on hooks. Use [teljoy_widget] within a product page.', 'woo_teljoy')

			),
			'enable_cart_warnings' => array(
				'title'   => __('Enable Cart warning system', 'woocommerce-gateway-teljoy'),
				'type'    => 'checkbox',
				'label'   => __('Enable Cart warning system.', 'woocommerce-gateway-teljoy'),
				'default' => 'no',
			),
			'send_debug_email' => array(
				'title'   => __('Send Debug Emails', 'woocommerce-gateway-teljoy'),
				'type'    => 'checkbox',
				'label'   => __('Send debug e-mails for transactions through the teljoy gateway (sends on successful transaction as well).', 'woocommerce-gateway-teljoy'),
				'default' => 'no',
			),
			'debug_email' => array(
				'title'       => __('Who Receives Debug E-mails?', 'woocommerce-gateway-teljoy'),
				'type'        => 'text',
				'description' => __('The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-teljoy'),
				'default'     => get_option('admin_email'),
			),
			'enable_logging' => array(
				'title'   => __('Enable Logging', 'woocommerce-gateway-teljoy'),
				'type'    => 'checkbox',
				'label'   => __('Enable transaction logging for gateway.', 'woocommerce-gateway-teljoy'),
				'default' => 'no',
			),
			'log_file' => array(
				'title'       => __('Log File', 'woocommerce-gateway-teljoy'),
				'type'        => 'select',
				'description' => __('Select a log file to download.', 'woocommerce-gateway-teljoy'),
				'options'     => $this->get_log_files(),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'teljoy-log-file', // New: Added a class
				'id'          => 'teljoy_log_file',
			),
		);
	}

	
	/**
	 * Get all log files related to this plugin.
	 *
	 * @return array
	 */
	public function get_log_files() {
		$log_files = array();
		require_once WC()->plugin_path() . '/includes/log-handlers/class-wc-log-handler-file.php';
	
		// Get the path to a specific log file.
		$log_file_path = WC_Log_Handler_File::get_log_file_path('teljoy');
		// Extract the directory from the file path.
		$log_dir = dirname($log_file_path) . '/';
	
		// Get all log files in the directory.
		$files = glob($log_dir . '*teljoy*.log');
	
		// Loop through the files and add them to the log files array.
		foreach ($files as $file) {
			$log_files[basename($file)] = basename($file);
		}
	
		return $log_files;
	}

	public function download_log() {
		$file = sanitize_file_name($_GET['file']);
		$log_files = $this->get_log_files();
	
		if (array_key_exists($file, $log_files)) {
			$uploads_dir = wp_upload_dir();
			$logs_dir_path = $uploads_dir['basedir'] . '/wc-logs/';
			$file_path = $logs_dir_path . $log_files[$file];
	
			if (file_exists($file_path)) {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file_path));
				flush(); 
				readfile($file_path);
				exit;
			}
		}
		wp_die();
	}
	

	/**
	 * Determine if the gateway still requires setup.
	 *
	 * @return bool
	 */
	public function needs_setup()
	{
		return !$this->get_option('merchant_api_key');
	}


	/**
	 * check_requirements()
	 *
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function check_requirements()
	{

		$woocommerce_settings = get_option('woocommerce_teljoy_settings');
		//$time_set 			= null !== $this->get_option('teljoy_hold_stock_minutes') ? $this->get_option('teljoy_hold_stock_minutes') : $woocommerce_settings["woocommerce_hold_stock_minutes"];
		//$recommended_time = isset($this->teljoy_hold_stock_recommended_minutes) ? $this->teljoy_hold_stock_recommended_minutes : $woocommerce_settings["woocommerce_hold_stock_minutes"];
		$errors = [
			// Check if the store currency is supported by Teljoy
			!in_array(get_woocommerce_currency(), $this->available_currencies) ? 'Your store uses a currency that Teljoy doesnt support yet' : null,
			// Check if user entered the merchant ID
			'yes' !== $this->get_option('testmode') && empty($this->get_option('merchant_api_key'))  ? 'You forgot to fill your merchant ID' : null,
			// Check the stock hold time
			//(int)$time_set < (int)$recommended_time ? sprintf(__('Woocommerce Inventory Stock Hold is currently at ' . $woocommerce_settings["woocommerce_hold_stock_minutes"] . ' minutes for pending transactions and it should be ' . $this->teljoy_hold_stock_recommended_minutes . ', Click <strong><a href="%s">here</a></strong> to update this to a more reasonable approval range.', 'woo-teljoy'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=teljoy'))) : null,
			// Check if we are still in test mode
			'yes' == $this->get_option('testmode') ? sprintf(__('Teljoy test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'woo-teljoy'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=teljoy'))) : null

		];

		return array_filter($errors);
	}

	private function verify_client_status()
	{
		//https://pay.teljoy.johnson.org.za/api/merchant
		$verify_merchant = wp_remote_get(
			$this->merchant_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'api-key' => $this->get_option('merchant_api_key'),
					'api-version' => $this->version,
					'user-agent' => $_SERVER['HTTP_USER_AGENT'],
					'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
				),
				'timeout' => 30
			)
		);
		$status = json_decode(wp_remote_retrieve_body($verify_merchant));
		$this->log('verify merchant status: ' . print_r($status->completion_period_expiry, true), false);

		if (is_wp_error($status)) {
			update_option("woocommerce_hold_stock_minutes", $this->teljoy_hold_stock_minutes);
			return $this->teljoy_hold_stock_minutes;
		}

		if ($status->completion_period_expiry !== null) {
			update_option("woocommerce_hold_stock_minutes", $status->completion_period_expiry);
			$this->update_option("teljoy_hold_stock_minutes", $status->completion_period_expiry);
			return $status->completion_period_expiry;
		} else {
			//potentially disable gateway if we get and error here
			//$this->enabled = 'no';
			update_option("woocommerce_hold_stock_minutes", $this->teljoy_hold_stock_minutes);
			return $this->teljoy_hold_stock_minutes;
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if ('yes' === $this->enabled) {
			$errors = $this->check_requirements();
			// Prevent using this gateway on frontend if there are any configuration errors.

			return 0 === count($errors);
		}
		return parent::is_available();
	}

	/**
	 *  Show possible admin notices
	 */
	public function teljoy_admin_notices()
	{

		if (!current_user_can('manage_options')) {
			return;
		}
		// Get requirement errors.
		$errors_to_show = $this->check_requirements();

		// If everything is in place, don't display it.
		if (!count($errors_to_show)) {
			return;
		}

		// If the gateway isn't enabled, don't show it.
		if ("no" ===  $this->enabled) {
			return;
		}

		// Use transients to display the admin notice once after saving values.
		if (!get_transient('wc-gateway-teljoy-admin-notice-transient')) {
			set_transient('wc-gateway-teljoy-admin-notice-transient', 1, 1);

			echo '<div class="notice notice-error is-dismissible"><p>'
				. __('To use teljoy as a payment provider, you need to fix the problems below:', 'woocommerce-gateway-teljoy') . '</p>'
				. '<ul style="list-style-type: disc; list-style-position: inside; padding-left: 2em;">'
				. array_reduce($errors_to_show, function ($errors_list, $error_item) {
					$errors_list = $errors_list . PHP_EOL . ('<li>' . $error_item . '</li>');
					return $errors_list;
				}, '')
				. '</ul></p></div>';
		}
	}

	/**
	 * Get order property with compatibility check on order getter introduced
	 * in WC 3.0.
	 *
	 * @since 1.4.1
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $prop  Property name.
	 *
	 * @return mixed Property value
	 */
	public static function get_order_prop($order, $prop)
	{
		switch ($prop) {
			case 'order_total':
				$getter = array($order, 'get_total');
				break;
			default:
				$getter = array($order, 'get_' . $prop);
				break;
		}

		return is_callable($getter) ? call_user_func($getter) : $order->{$prop};
	}

	/**
	 * Init Environment Options
	 *
	 * @since 1.2.3
	 */
	public function init_environment_config()
	{
		if (empty($this->environments)) {
			//config separated for ease of editing
			require('config.php');
			if ($this->get_option('testmode') == 'yes') {
				$this->environments = $environments["sandbox"];
			} else {
				$this->environments = $environments["production"];
			}
		}
	}

	/**
	 * Log system processes.
	 * @since 1.0.0
	 */
	public function log($message, $send_mail)
	{
		//log the item if valid
		if ('yes' === $this->get_option('testmode') || $this->enable_logging) {
			if (empty($this->logger)) {
				$this->logger = new WC_Logger();
			}
			$this->logger->add('teljoy', $message);
		}
		//send debug mail if valid
		if ($this->send_debug_email === 'yes' && $send_mail) {
			wp_mail($this->debug_email, 'Teljoy Plugin: Debug or Error Notification', $message);
		}
	}

	private function build_product_list($orderitems)
	{
		$items = array();
		$i = 0;
		foreach ($orderitems as $item) {
			$i++;
			// get SKU
			if ($item['variation_id']) {
				if (function_exists("wc_get_product")) {
					$product = wc_get_product($item['variation_id']);
				} else {
					$product = new WC_Product($item['variation_id']);
				}
			} else {
				if (function_exists("wc_get_product")) {
					$product = wc_get_product($item['product_id']);
				} else {
					$product = new WC_Product($item['product_id']);
				}
			}
			$product = array(
				'name' => $item['name'],
				'sku' => $product->get_sku(),
				'quantity' => $item['qty'],
				'price' => number_format(($item['line_subtotal'] / $item['qty']), 2, '.', ''),
				'description' => "string",
				'brand' => "string",
				'merchant_product_id' => $item['product_id'],
				'vendor' => array(
					'vendor_id' => "string",
					'url' => "string",
					'name' => "string"
				),
				'images' => array(
					wp_get_attachment_image_src(get_post_thumbnail_id($item['product_id']), 'single-post-thumbnail')[0]
				),
				'barcodes' => array(
					get_post_meta(self::get_order_prop($product, 'id'), 'teljoy_barcode', true)
				),
				'categories' => array(),
				'properties' => array(
					array(
						'key' => "string",
						'value' => "string"
					)
				)
			);
			
			// $jsonString = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$items[] = $product;
		}
		return $items;
	}

	/**
	 * Process the payment and return the result
	 * - redirects the customer to the pay page
	 *
	 * @param int $order_id
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function process_payment($order_id)
	{

		if (function_exists("wc_get_order")) {
			$order = wc_get_order($order_id);
		} else {
			$order = new WC_Order($order_id);
		}

		//Process here
		$orderitems = $order->get_items();

		if (count($orderitems)) {
			$items = $this->build_product_list($orderitems);
		}

		//calculate total shipping amount
		if (method_exists($order, 'get_shipping_total')) {
			//WC 3.0
			$shipping_total = $order->get_shipping_total();
		} else {
			//WC 2.6.x
			$shipping_total = $order->get_total_shipping();
		}

		$OrderBody = $this->transaction_payload($order, $items, $order_id, $shipping_total);
		$order_args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'api-key' => $this->get_option('merchant_api_key'),
				'api-version' => $this->version,
				'user-agent' => $_SERVER['HTTP_USER_AGENT'],
				'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
			),
			'timeout' => 30,
			'body' => $OrderBody,
		);

		$this->log('POST Order request: ' . print_r($order_args, true), true);

		$order_response = wp_remote_post($this->url, $order_args);

		$order_body = json_decode(wp_remote_retrieve_body($order_response));
		$this->log('POST Order response: ' . print_r($order_body, true), true);
		$this->log('POST Order response url: ' . print_r($this->url, true), true);

		if (is_wp_error($order_body)) {
			$order->add_order_note(__('Some Errors have occurred. Payment couldn\'t proceed.', 'woo_teljoy'));
			wc_add_notice(__('Sorry, there was a problem preparing your payment.', 'woo_teljoy'), 'error');
			return array(
				'result' => 'failure',
				'redirect' => $order->get_checkout_payment_url(true)
			);
		}

		if ($order_body->success !== null && $order_body->success == true) {
			//add the id
			$order->update_meta_data('teljoy_transaction_id', $order_body->id);
			$order->save_meta_data();
			//$this->log('POST base order: ' . print_r($order, true));
			// $this->log('POST Order response ID: ' . print_r($order_body->id, true));

			//lets store the redirect url for later
			$order->update_meta_data('teljoy_redirect_url', $order_body->redirect_url);
			$order->save_meta_data();

			$redirectURL = $order_body->redirect_url;

			return array(
				'result' => 'success',
				'redirect' => $redirectURL
			);
		} else {
			if ($this->send_debug_email === 'yes') {
				$this->log('Sending email notification', false);
				// Send an email
				// $subject = 'Teljoy Create Cart error: ';
				$body =
					"Hi,\n\n" .
					"An invalid Teljoy transaction on your website requires attention\n" .
					"Order ID: " . $order_id . "\n" .
					"------------------------------------------------------------\n";
				if ($order_body->errors !== null) {
					foreach ($order_body->errors as $key => $error) {
						$body .= $key . " : " . $error[0] . "\n";
					}
				}

				$this->log($body, true);
			}
			$order->add_order_note(__('Some Errors have occurred. Payment couldn\'t proceed.', 'woo_teljoy'));
			wc_add_notice(__('Sorry, there was a problem preparing your payment.', 'woo_teljoy'), 'error');
			return array(
				'result' => 'failure',
				'redirect' => $order->get_checkout_payment_url(true)
			);
		}
	}

	public function transaction_payload($order, $items, $order_id, $shipping_total)
	{
		$OrderBodyString = '{
					"customer": {
						"first_name":  "' . $order->billing_first_name . '",
						"last_name":  "' . $order->billing_last_name . '",
						"email":  "' . $order->billing_email . '",
						"mobile": "' . $order->billing_phone . '"
					},
					"shipping_address": {
						"type": "residential",
						"building": "' . $order->shipping_address_1 . ' ",
						"street": "' . $order->shipping_address_2 . '",
						"suburb": "' . $order->shipping_city . '",
						"city": "' . $order->shipping_city . '",
						"province": "' . $order->shipping_state . '",
						"country": "' . $order->shipping_country . '",
						"postal_code": "' . $order->shipping_postcode . '",
						"confirmed": false
					},
					"billing_address": {
						"type": "residential",
						"building": "' . $order->billing_address_1 . ' ",
						"street": "' . $order->billing_address_2 . '",
						"suburb": "' . $order->billing_city . '",
						"city": "' . $order->billing_city . '",
						"province": "' . $order->billing_state . '",
						"country": "' . $order->billing_country . '",
						"postal_code": "' . $order->billing_postcode . '",
						"confirmed": false
					},
					"products": ';
		$seed_value_base = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		foreach ($items as $item) {
			$seed_value_base .= $item[0];
		}
		$OrderBodyString .= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$OrderBodyString = rtrim($OrderBodyString, ",");

		$order->update_meta_data('teljoy_trust_seed', base64_encode($seed_value_base));
		$order->save_meta_data();

		$OrderBodyString .= ',
					"redirects": {
						"order_id": "' . $order_id . '",
						"trust_value": "' . hash('md5', $order_id) . '",
						"trust_seed": "' . get_post_meta(self::get_order_prop($order, 'id'), 'teljoy_trust_seed', true) . '",
						"success_redirect_url": "' . $this->get_return_url($order) . '&order_id=' . $order_id . '&wc-api=WC_Gateway_Teljoy", 
						"failure_redirect_url": "' . $this->get_return_url($order) . '&status=cancelled&wc-api=WC_Gateway_Teljoy",
						"final_amount": ' . number_format($order->get_total(), 2, '.', '') . ',
						"tax_amount": ' . $order->get_total_tax() . ',
						"shipping_amount":' . $shipping_total . ', 
						"discount": "0"
					}
					
					}';
		return $OrderBodyString;
	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to Teljoy.
	 *
	 * @since 1.0.0
	 */
	public function receipt_page($order_id)
	{
		$this->log('Receipt page: ', true);
		if (function_exists("wc_get_order")) {
			$order = wc_get_order($order_id);
		} else {
			$order = new WC_Order($order_id);
		}
		echo '<p>' . __('Thank you for your order, please click the button below to pay with Teljoy.', 'woocommerce-gateway-teljoy') . '</p>';
		print_r($order);
		exit;
	}


	/**
	 * Check Teljoy API response or request
	 * we will either receive a redirect from the payment processor or they will trigger this at a later time
	 *
	 * @since 1.0.0
	 */
	public function check_api_response()
	{
		$this->log('Checkout page triggered: ', true);

		//01: first we must ensure the status and trust value is present and our request is from teljoy
		if ($this->verify_redirect_params === 'yes') {
			if (!$_GET['status'] || !$_GET['trust_signature'] || !$_GET['order_id']) {
				$this->log('Security Warning: missing query params' . print_r($_GET, true), true);
				$this->log('Security Warning: attempt to trigger payment processor' . print_r($_SERVER, true), true);
				wp_redirect('/');
				exit;
			}
		}


		//02: fetch the transaction
		$order = wc_get_order($_GET['order_id']);
		$this->log('Verifying transaction status part 01 ' . print_r(self::get_order_prop($order, 'status')), true);

		//03: verify transaction against teljoy api
		$state = $this->validate_transaction_status($order);
		if (!$state) {
			$this->log('Verifying transaction failed ' . print_r($state), true);
			wp_redirect('/');
			exit;
		}

		//04: verify transaction status against the received status
		// if((self::get_order_prop( $order, 'status' ) == 'confirmed') || (self::get_order_prop( $order, 'status' ) == $_GET['status'])){
		// 	//transaction already completed or at the same level as status update so exit
		// 	wp_redirect('/');
		// 	exit;
		// }


		//05: verify the signature :: return the ! after testing
		if (!$this->validate_signature($state->trust_signature, $order)) {
			//the signature has failed
			$this->log('Security Warning: an incorrect signature was attempted on order ' . $_GET['order_id'] . "\n" .
				print_r($_SERVER, true), true);
			wp_redirect('/');
			exit;
		}

		//06: process the transaction based on the new status
		if ($state->status === 'confirmed' || $state->status === 'complete') {
			$this->handle_api_payment_complete($state, $order);
		} elseif ($state->status  === 'failed') {
			$this->handle_api_payment_failed($state, $order);
		} elseif ($state->status  === 'pending') {
			$this->handle_api_payment_pending($state, $order);
		} elseif ($state->status  === 'cancelled') {
			$this->handle_api_payment_cancelled($state, $order);
		}

		//05: complete the process
		$payment_page = $this->get_return_url($order) . '&status='.$state->status ;
		wp_redirect($payment_page);
		exit;
		header('HTTP/1.0 200 OK');
		flush();
	}


	/**
	 * This function handles payment complete request by Teljoy.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy API callback.
	 * @param WC_Order $order
	 */
	private function handle_api_payment_complete($state, $order)
	{
		$this->log('state: ' . print_r($state, true), false);
		$this->log('order ID: ' . print_r($state->order_id, true), false);
		$this->log('signature: ' . print_r($state->trust_signature, true), false);
		$this->log('Payment Processed as Complete' . print_r($order, true), false);
		$order->add_order_note(sprintf(__('Payment approved. Teljoy Order signature: ' . $state->trust_signature . ' ', 'woo_teljoy')));
		$order->update_meta_data('teljoy_transaction_signed', $state->trust_signature);
		WC()->cart->empty_cart();
		//wc_empty_cart();
		$order->payment_complete($state->order_id);
		$vendor_name    = get_bloginfo('name', 'display');
		$vendor_url     = home_url('/');
		$body =
			"Hi,\n\n"
			. "A Teljoy transaction has been completed on your website\n"
			. "------------------------------------------------------------\n"
			. 'Site: ' . esc_html($vendor_name) . ' (' . esc_url($vendor_url) . ")\n"
			. 'Teljoy Trust Signature: ' . esc_html($state->trust_signature) . "\n";
		$this->log($body, true);
	}

	/**
	 * This function handles payment complete request by Teljoy.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy API callback.
	 * @param WC_Order $order
	 */
	private function handle_api_payment_failed($state, $order)
	{
		$this->log('Payment Processed as failed', false);
		$order->add_order_note(sprintf(__('Teljoy payment declined. Order Signature from Teljoy: ' . $state->trust_signature . ' ', 'woo_teljoy')));
		$order->update_status('failed');
	}

	/**
	 * This function handles payment pending response by Teljoy.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy API callback.
	 * @param WC_Order $order
	 */
	private function handle_api_payment_pending($state, $order)
	{
		$this->log('Payment Processed as pending', false);
		$this->log('state: ' . print_r($state, true), false);
		$order->update_status('pending');
		WC()->cart->empty_cart();
	}

	/**
	 * This function handles payment cancelled response by Teljoy.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy API callback.
	 * @param WC_Order $order
	 */
	private function handle_api_payment_cancelled($state, $order)
	{
		$this->log('Payment Processed as cancelled', false);
		$order->add_order_note(sprintf(__('Teljoy payment is pending approval. Teljoy Order ID: ' . $state->trust_signature . ' ', 'woo_teljoy')));
		$order->update_status('cancelled');
	}


	/**
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 * @return string
	 */
	public function validate_signature($signature, $order)
	{
		$result = $this->generate_signature($order) === $signature;
		$this->log('Signature = ' . $this->generate_signature($order), false);
		return $result;
	}


	/**
	 * verify transaction status
	 *
	 * Validate the received order_id against teljoy api
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 * @return string
	 */
	public function validate_transaction_status($order)
	{

		$verify_transaction = wp_remote_get(
			$this->payment_url . '' . get_post_meta(self::get_order_prop($order, 'id'), 'teljoy_transaction_id', true).'/status',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'api-key' => $this->get_option('merchant_api_key'),
					'api-version' => $this->version,
					'user-agent' => $_SERVER['HTTP_USER_AGENT'],
					'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
				),
				'timeout' => 30
			)
		);
		$status = json_decode(wp_remote_retrieve_body($verify_transaction));
		$this->log('transaction status: ' . print_r($status, true), false);
		if (is_wp_error($status)) {
			return false;
		}
		//is this a valid response
		if ($status->statusCode && $status->statusCode == 404) {
			return false;
		}
		//does the query string and the product lookup id match
		if ($status->order_id != self::get_order_prop($order, 'id')) {
			return false;
		}

		// verify the url signature and the endpoint signature match

		// return response
		return $status;
	}


	public function generate_signature($order)
	{
		//01 create trust_value
		$trust_value = hash('md5', self::get_order_prop($order, 'id'));
		//02 create trust_seed
		$trust_seed = get_post_meta(self::get_order_prop($order, 'id'), 'teljoy_trust_seed', true);
		//for testing return true
		return hash('sha256', $trust_value . '' . $trust_seed);
	}


	/**
	 * Check whether the cart line items are teljoy approved
	 *
	 * @param  array $gateways Enabled gateways
	 * @return  array Enabled gateways, possibly with Teljoy removed
	 * @since 1.0.0
	 */
	public function check_cart_line_item_validity($gateways)
	{
		if (is_admin() || !is_checkout()) {
			return false;
		}
		global $woocommerce;

		if (isset($woocommerce->cart->cart_contents) && count($woocommerce->cart->cart_contents) >= 1) {

			$showTeljoy = true;
			$response = $this->api_bulk_product_lookup($woocommerce->cart->cart_contents);
			//echo 'validating teljoy products';
			foreach ($response as $item) {
				//TODO:: add or check for a flag against the product so we can skip this step if its already been checked
				if ($item->accepted == false) {
					$showTeljoy = false;
				}
			}



			if (!$showTeljoy) {
				unset($gateways['teljoy']);
			}
		}

		return $gateways;
	}


	public function generate_product_payload($obj)
	{

		$data = $obj->get_data();
		$item = '{
			"name": "' . esc_html($data['name']) . '",
			"description": "' . esc_html($data['description']) . '",
			"short_description": "' . esc_html($data['short_description']) . '",
			"brand": "string",
			"merchant_product_id":"' . $data['id'] . '",
			"quantity": "1",
			"vendor": {
			  "vendor_id": "string",
			  "url": "string",
			  "name": "string"
			},
			"images": [
			  "' . wp_get_attachment_image_src($data['image_id'])[0] . '" 
			],
			"url": "string",
			"price": "' . $data['price'] . '",
			"sku": "' . $data['sku'] . '",
			"barcodes": [
			  "' . get_post_meta($data['id'], 'teljoy_barcode', true)  . '"
			],
			"categories": [';
		foreach ($data['category_ids'] as $cat) {
			$item .= '
				  {
					"id": "' . $cat . '",
					"name": "' . get_the_category_by_ID($cat) . '",
					"url": "string"
				  },';
		}
		$item = rtrim($item, ",");
		$item .= '],
			"properties": [
			  {
				"key": "string",
				"value": "string"
			  }
			]
		  }
		';
		return $item;
	}

	public function api_product_lookup($payload, $product_id)
	{
		$payload = $this->generate_product_payload($payload);
		$this->log('POST product lookup: ' . print_r($payload, true), false);

		$response = wp_remote_post($this->validate_url, array(
			'body' => $payload,
			'headers' => array(
				'Content-Type' => 'application/json',
				'api-key' => $this->get_option('merchant_api_key'),
				'api-version' => $this->version,
				'user-agent' => $_SERVER['HTTP_USER_AGENT'],
				'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
			),
			'timeout' => 30
		));

		$result = json_decode(wp_remote_retrieve_body($response));
		$this->log('POST lookup response: ' . print_r($result, true), false);

		if (is_wp_error($result)) {
			return false;
		}
		if (isset($result->accepted) && $result->accepted == true) {
			return $result;
		} elseif (isset($result->accepted) && $result->accepted == false) {
			return false;
		} else {
			$this->log('Sending error email notification for Teljoy Product Lookup error:', false);
			$body =
				"Hi,\n\n" .
				"An invalid Teljoy lookup on your website requires attention\n" .
				"Product ID: " . $product_id . "\n" .
				"------------------------------------------------------------\n";
			//$body .= $result->message !== null?$result->message:'';
			$this->log($body, true);

			return false;
		}
	}

	public function api_bulk_product_lookup($list)
	{

		$payload = '[';
		foreach ($list as $item) {
			$payload .= $this->generate_product_payload($item['data']) . ',';
		}
		$payload = rtrim($payload, ",");
		$payload .= ']';

		//$this->log('POST product lookup: ' . print_r($payload, true), false);

		$response = wp_remote_post($this->validate_url, array(
			'body' => $payload,
			'headers' => array(
				'Content-Type' => 'application/json',
				'api-key' => $this->get_option('merchant_api_key'),
				'api-version' => $this->version,
				'user-agent' => $_SERVER['HTTP_USER_AGENT'],
				'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
			),
			'timeout' => 30
		));

		$result = json_decode(wp_remote_retrieve_body($response));
		$this->log('POST lookup response: ' . print_r($result, true), false);

		if (is_wp_error($result)) {
			return false;
		} else {
			return $result;
		}
	}

	public function order_status_change_update($order)
	{
		//verify product list is the same
		//Process here
		$orderitems = $order->get_items();

		if (count($orderitems)) {
			$items = $this->build_product_list($orderitems);
		}

		$seed_value_base = '';
		foreach ($items as $item) {
			$seed_value_base .= $item[0];
		}
		$alt_trust_value = base64_encode($seed_value_base);

		$payload = '{
			"status": "' . self::get_order_prop($order, 'status') . '",
			"trust_value": "' . hash('md5', self::get_order_prop($order, 'id')) . '",
			"trust_seed": "' . get_post_meta(self::get_order_prop($order, 'id'), 'teljoy_trust_seed', true) . '",
			"alt_trust_value": "' . $alt_trust_value . '"
		}';

		$response = wp_remote_post($this->payment_url . '' . get_post_meta(self::get_order_prop($order, 'id'), 'teljoy_transaction_id', true).'/status', array(
			'body' => $payload,
			'headers' => array(
				'Content-Type' => 'application/json',
				'api-key' => $this->get_option('merchant_api_key'),
				'api-version' => $this->version,
				'user-agent' => $_SERVER['HTTP_USER_AGENT'],
				'environment' => '{"woocommerce_version": "' . WC_VERSION . '","php_version":"' . phpversion() . '"}'
			),
			'timeout' => 30
		));

		$this->log('POST transaction status change: ' . print_r($payload, true), false);

		$result = json_decode(wp_remote_retrieve_body($response));
		if (is_wp_error($result)) {
			$this->log('Sending error email notification for Teljoy Product Lookup error:', false);
			$body =
				"Hi,\n\n" .
				"A failure occured when attempting to notify teljoy of an order status change\n" .
				"Order ID: " . self::get_order_prop($order, 'id') . "\n" .
				"------------------------------------------------------------\n";
			$this->log($body, true);

			return false;
		}

		//TODO: we are pending a response object from Stephan
		// $this->log('POST transaction status change: ' . print_r($result, true), false);
		// if (isset($result->accepted) && $result->accepted == true) {
		// 	return $result;
		// } else {
		// 	$this->log('Sending error email notification for Teljoy Product Lookup error:', false);
		// 	$body =
		// 		"Hi,\n\n" .
		// 		"A failure occured when attempting to notify teljoy of an order status change\n" .
		// 		"Order ID: " . self::get_order_prop($order, 'id') . "\n" .
		// 		"------------------------------------------------------------\n";
		// 	$body .= $result->message;
		// 	$this->log($body, true);

		// 	return false;
		// }
	}
}

/**
 * Add the Teljoy gateway to WooCommerce
 *
 * @param  array $methods Array of Payment Gateways
 * @return  array Array of Payment Gateways
 * @since 1.0.0
 *
 */

/**
 * PRODUCT PAGE SETTINGS TAB
 **/
function wk_custom_product_tab($default_tabs)
{
	$default_tabs['teljoy_settings'] = array(
		'label'   =>  __('Teljoy Settings', 'domain'),
		'target'  =>  'wk_teljoy_tab_data',
		'priority' => 60,
		'class'   => array()
	);
	return $default_tabs;
}

add_filter('woocommerce_product_data_tabs', 'wk_custom_product_tab', 10, 1);

/**
 * 	: PRODUCT PAGE SETTINGS TAB CONTENT
 **/
add_action('woocommerce_product_data_panels', 'wk_teljoy_tab_data');
function wk_teljoy_tab_data()
{
	global $product_object;
?>
	<div id="wk_teljoy_tab_data" class="panel woocommerce_options_panel">
		<div class="options_group">
			<p class="form-field dimensions_field">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => 'teljoy_barcode',
						'value'       => get_post_meta(get_the_ID(), 'teljoy_barcode', true),
						'label'       => __('Teljoy Barcode', 'woocommerce'),
						'placeholder' => 'product barcode',
						'desc_tip'    => true,
						'description' => __('barcode used for teljoy', 'woocommerce'),
						'type'        => 'text',
						'data_type'   => 'decimal',
					)
				);
				?>
			</p>
		</div>
	</div>
<?php
}

//TODO: notify teljoy api when status changes
function woo_order_status_change_teljoy()
{
	$gateway = new WC_Gateway_Teljoy();

	if (!current_user_can('manage_options'))
		return false;
	if (!is_admin())
		return false;
	if ($_REQUEST['post_type'] != 'shop_order')
		return false;
	if ($_REQUEST['post_ID'] != '') {
		$orderId = $_REQUEST['post_ID'];
		$order = new WC_Order($orderId);
		if ($order->payment_method == 'teljoy') {
			$gateway->order_status_change_update($order);
		}
	}
}

add_action('woocommerce_order_status_changed', 'woo_order_status_change_teljoy', 10, 3);


/**
 * 	: Handing the saving of barcodes
 **/
add_action('woocommerce_process_product_meta', 'teljoy_save_fields', 10, 2);
function teljoy_save_fields($id, $post)
{

	if (!empty($_POST['teljoy_barcode'])) {
		update_post_meta($id, 'teljoy_barcode', $_POST['teljoy_barcode']);
	}
	// } else {
	// 	delete_post_meta( $id, 'teljoy_barcode' );
	// }

}


/**
 * ADD TELJOY GATEWAY TO WOOCOMMERCE
 **/
function add_teljoy_gateway($methods)
{
	$methods[] = 'WC_Gateway_Teljoy';
	return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_teljoy_gateway');

$options = get_option('woocommerce_teljoy_settings', 'gets the option');


if (isset($options['teljoy_on_cart'])) {
	$teljoy_on_cart = $options['teljoy_on_cart'];
}

if (isset($teljoy_on_cart) && $teljoy_on_cart == 'yes') {
	/* Display on Cart Page */
	function display_teljoy_cart()
	{

		global $woocommerce;
		$gateway = new WC_Gateway_Teljoy();
		$minPrice_cut = 0;
		$message = 'Or, for only ';
		$options = get_option('woocommerce_teljoy_settings', 'gets the option');
		$img = plugins_url('../media/teljoy_logo.png', __FILE__);
		if (isset($woocommerce->cart->cart_contents) && count($woocommerce->cart->cart_contents) >= 1) {

			$showTeljoy = true;
			$response = $gateway->api_bulk_product_lookup($woocommerce->cart->cart_contents);

			foreach ($response as $item) {
				if ($item->accepted == false) {
					$showTeljoy = false;
				} else {

					if ($options['teljoy_cart_as_combined'] && $options['teljoy_cart_as_combined'] == 'yes') {
						$minPrice_cut = ($minPrice_cut + $item->price);
						$message = 'Or, for only ';
					} else {
						if ($item->price < $minPrice_cut || $minPrice_cut == 0) {
							$minPrice_cut = $item->price;
						}
					}
				}
			}

			if ($showTeljoy) {
				echo wp_kses_post('<div id="float-on-cart" style="color:black !important;margin-top:4px;">' . $message . '<strong class="teljoy-highlight" style="color: #ff003e !important;font-size: inherit;font-weight: inherit;">' . wc_price($minPrice_cut) . ' per month</strong> , try it, love it, own it. <br/>Apply with <img src="' . $img . '" alt="Teljoy" class="float-logo" style="width: 50px;vertical-align: baseline;"/> <a target="_blank" href="https://www.teljoy.co.za/">Learn more</a></div>');
			}
		}
	}

	add_action('woocommerce_after_cart_totals', 'display_teljoy_cart', 9, 0);
}



/**
 * Enable the product summary page widget.
 **/

// FUNCTION - Frontend show on single product page widget
function teljoy_widget_content()
{
	$teljoy_settings = get_option('woocommerce_teljoy_settings');
	if (isset($teljoy_settings['enable_product_widget']) && $teljoy_settings['enable_product_widget'] == 'yes') {
		echo woo_teljoy_frontend_widget();
	}
}

/**
 * Enable the product summary page widget as shortcode.
 **/
function teljoy_widget_shortcode_content()
{
	$teljoy_settings = get_option('woocommerce_teljoy_settings');
	if (isset($teljoy_settings['is_using_page_builder']) && $teljoy_settings['is_using_page_builder'] == 'yes') {
		echo woo_teljoy_frontend_widget();
	}
}
add_shortcode('teljoy_widget', 'teljoy_widget_shortcode_content');

function woo_teljoy_frontend_widget_legacy()
{
	$gateway = new WC_Gateway_Teljoy();
	global $product;

	// TODO:: add or check for a flag against the product so we can skip this step if its already been checked
	$teljoy_price = $gateway->api_product_lookup($product, $product->get_id());
	if ($teljoy_price) {
		// 	return '<div class="teljoy"><div id="teljoytext"><img id="teljoyCalculatorWidgetLogo" width="100px" height="auto" src="' . WC_GATEWAY_TELJOY_URL . "/media/teljoy_logo.svg" . '"/></div><p class="teljoy-copy">This product is not currently</b></p></div>';
		// } else {
		return '<div class="teljoy"><div id="teljoytext"><img id="teljoyCalculatorWidgetLogo" width="100px" height="auto" src="' . WC_GATEWAY_TELJOY_URL . "/media/teljoy_logo.svg" . '"/></div><p class="teljoy-copy">Or, for only <b>R' . $teljoy_price->price . ',00 per month</b>, try it, love it, own it. Apply with Teljoy.
		<br><a target="_blank" href="https://www.teljoy.co.za/">Learn more</a></p></div>';
	}
}
function woo_teljoy_frontend_widget()
{
	$gateway = new WC_Gateway_Teljoy();
	global $product;

	$teljoy_price = $gateway->api_product_lookup($product, $product->get_id());
	if ($teljoy_price) {
		return '
        <div class="teljoy">
            <div id="teljoytext">
                <img id="teljoyCalculatorWidgetLogo" width="100px" height="auto" src="' . WC_GATEWAY_TELJOY_URL . '/media/teljoy_logo.svg"/>
            </div>
            <p class="teljoy-copy">Or, for only <b>R' . $teljoy_price->price . ',00 per month</b>, try it, love it, own it. Apply with Teljoy.
            <br><a href="#" id="openModal">Learn more</a></p>
        </div>
        <div id="teljoyModal" class="modal">
            <div class="modal-content">
                <span id="closeModal">&times;</span>
                <iframe src="https://krispykremesa.sinappsus.co.za" frameborder="0" style="width:100%;height:100%;"></iframe>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var modal = document.getElementById("teljoyModal");
                var btn = document.getElementById("openModal");
                var span = document.getElementById("closeModal");

                btn.onclick = function() {
                    modal.style.display = "block";
                }

                span.onclick = function() {
                    modal.style.display = "none";
                }

                window.onclick = function(event) {
                    if (event.target == modal) {
                        modal.style.display = "none";
                    }
                }
            });
        </script>
        <style>
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.8);
            }

			@media screen and (min-width: 769px) {
            .modal-content {
				top: 3% !important;
				position: relative;
				width: 850px !important;
				height: 90vh !important;
				margin: 0 auto;
				background-color: #fff;
				border-radius: 4px;
				padding: 16px 0px;
			}
		}

            @media screen and (max-width: 768px) {
                .modal-content {
                    width: 100vw;
                    height: 100vh;
                }
            }

            #closeModal {
				position: absolute;
				top: 0px;
				right: 1px;
				cursor: pointer;
				font-size: 18px;
				background-color: #f0f0f0;
				padding: 0px 4px;
				color: black;
				font-weight: bold;
			}
        </style>';
	}
}


add_action('woocommerce_single_product_summary', 'teljoy_widget_content', 25);


/**
 * Loop over the cart and highlight any items not supported by teljoy.
 * also add a function to remove any items not supported by teljoy
 **/
function highlight_teljoy_items_in_cart()
{
	// Check if we are on the cart page
	$options = get_option('woocommerce_teljoy_settings', 'gets the option');
	if (is_cart() && $options['enable_cart_warnings'] == 'yes') {
		$gateway = new WC_Gateway_Teljoy();
		$teljoy_items = array();

		// issue the cart to the api so we can get back a list of what is accepted and what is not
		$items = $gateway->api_bulk_product_lookup(WC()->cart->get_cart());
		$position = 0;
		//this is a fallback for demonstration till the api is working again
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			// Check if the product is not a valid "teljoy" product
			if (!$items[$position]->accepted) {
				$cart_item['data']->update_meta_data('data-teljoy-item', 'rejected');
				$teljoy_items[] = $cart_item_key;
			}
			$position++;
		}
		// If there are teljoy items, display a notification at the top of the cart
		if (!empty($teljoy_items)) {
			$notice_text = __('To allow Teljoy as a payment option, remove the items below highlighted in <span class="rejected_teljoy_item">grey</span><br> or click the "Checkout with Teljoy" button.', 'your-text-domain');
			$notice_text .= '<br><a href="' . esc_url(wc_get_cart_url()) . '?remove_bad_items=true" class="button remove-teljoy-items-button">' . __('Checkout with Teljoy', 'your-text-domain') . '</a>';
			wc_print_notice($notice_text, 'notice');
		}
	}
}

add_action('woocommerce_before_cart', 'highlight_teljoy_items_in_cart');

function remove_bad_items_from_cart()
{
	// Check if the "remove_teljoy_items" parameter is present in the URL

	if (isset($_GET['remove_bad_items']) && $_GET['remove_bad_items'] === 'true') {
		$gateway = new WC_Gateway_Teljoy();

		//elementor doesnt pass the updated property so we reject the api
		$items = $gateway->api_bulk_product_lookup(WC()->cart->get_cart());
		$position = 0;

		//next we get the ids which are in the same order
		// $list = array();
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			if (!$items[$position]->accepted) {
				WC()->cart->remove_cart_item($cart_item_key);
			}

			$position++;
		}
		// Redirect the user back to the cart page
		wp_redirect(wc_get_cart_url());
		exit;
	}
}
add_action('template_redirect', 'remove_bad_items_from_cart');

//style the row

add_filter('woocommerce_cart_item_class', 'add_teljoy_item_class', 10, 3);
function add_teljoy_item_class($class, $cart_item, $cart_item_key)
{
	//$class = array();
	if ($cart_item['data']->get_meta('data-teljoy-item') === 'rejected') {
		$class = 'rejected_teljoy_item';
	}
	return $class;
}



function add_teljoy_style()
{
	wp_enqueue_style('teljoy-style', WC_GATEWAY_TELJOY_URL . '/css/teljoy-style.css');
}
add_action('wp_enqueue_scripts', 'add_teljoy_style');

/**
 * Verify order status and offer paths to continue with process if required
 * in WC 3.0.
 *
 * @since 1.4.1
 *
 * @param WC_Order $order Order object.
 * @param string   $prop  Property name.
 *
 * @return mixed Property value
 */
function account_area_order_status_checks($actions, $order)
{
	// Get the order status from your payment processor
	//$my_order_status = account_area_order_status_checks( $order->get_id() );
	$gateway = new WC_Gateway_Teljoy();

	$teljoy_url = get_post_meta($gateway::get_order_prop($order, 'id'), 'teljoy_redirect_url', true);

	// If the order has been processed by your payment processor, add a new button with a link to the status page

	if ($teljoy_url && $gateway->validate_transaction_status($order)->status == 'pending') {
		$actions['teljoy_status'] = array(
			'url'  => $teljoy_url,
			'name' => __('Teljoy Status', 'teljoy_status')
		);
	}

	return $actions;
}

add_filter('woocommerce_my_account_my_orders_actions', 'account_area_order_status_checks', 10, 2);
