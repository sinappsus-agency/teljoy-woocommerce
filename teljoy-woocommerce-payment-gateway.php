<?php
/*
 * Plugin Name: Teljoy Woocommerce Payment Gateway
 * Description: Use Teljoy as a payment processor for WooCommerce.
 * Plugin URI: #
 * Author URI: https://teljoy.co.za/
 * Version: 1.0.1
 * Author: Teljoy
 * Requires at least: 4.4
 * Tested up to: 6.0
 * WC tested up to: 6.7
 * WC requires at least: 2.6
*/

/**
 * Check if WooCommerce is activated
 */

defined('ABSPATH') || exit;

define('WC_GATEWAY_TELJOY_VERSION', '1.0.0');
define('WC_GATEWAY_TELJOY_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_GATEWAY_TELJOY_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

$plugin_path = trailingslashit(WP_PLUGIN_DIR) . 'woocommerce/woocommerce.php';
require_once __DIR__ . '/plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if (in_array($plugin_path, wp_get_active_and_valid_plugins()) || in_array($plugin_path, wp_get_active_network_plugins())) {

	$myUpdateChecker = PucFactory::buildUpdateChecker(
		'https://github.com/sinappsus-agency/teljoy-woocommerce/blob/main/version.json',  // The URL of the metadata file.
		__FILE__, // Full path to the main plugin file.
		'teljoy-woocommerce-payment-gateway'  // Plugin slug. Usually it's the same as the name of the directory.
	);

	$myUpdateChecker->addHttpRequestArgFilter(function($args) {
		// Replace with your actual username and password
		$username = 'artgraven';
		$password = 'ugrtnvdpacbfb2eygptigj2pjons2vph53clap4xqh6hudkk75rq';
		$args['headers'] = array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
		);
		return $args;
	});


	function teljoy_gateway()
	{
		if (!class_exists('WC_Payment_Gateway')) return;

		require_once(plugin_basename('includes/class-wc-gateway-teljoy.php'));
		require_once(plugin_basename('includes/class-wc-gateway-teljoy-privacy.php'));
		load_plugin_textdomain('woocommerce-gateway-teljoy', false, trailingslashit(dirname(plugin_basename(__FILE__))));
	}

	add_action('plugins_loaded', 'teljoy_gateway', 0);


	//plugin links
	function woocommerce_teljoy_plugin_links($links)
	{
		$settings_url = add_query_arg(
			array(
				'page' => 'wc-settings',
				'tab' => 'checkout',
				'section' => 'wc_gateway_teljoy',
			),
			admin_url('admin.php')
		);

		$plugin_links = array(
			'<a href="' . esc_url($settings_url) . '">' . __('Settings', 'woocommerce-gateway-teljoy') . '</a>',
			'<a href="#">' . __('Support', 'woocommerce-gateway-teljoy') . '</a>',
			'<a href="#">' . __('Docs', 'woocommerce-gateway-teljoy') . '</a>',
		);

		return array_merge($plugin_links, $links);
	}
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_teljoy_plugin_links');
}
