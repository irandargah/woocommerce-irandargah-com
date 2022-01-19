<?php
/**
 * Plugin Name: WooCommerce IranDargah Gateway
 * Plugin URI: https://irandargah.com
 * Description: IPG for woocommerce with IranDargah
 * Author: IranDargah
 * Author URI: https://irandargah.com
 * Version: 2.0.10
 * Requires at least: 4.4
 * Tested up to: 5.8
 * Text Domain: woocommerce-gateway-irandargah
 *
 */
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

defined('ABSPATH') || exit;

define('WC_GATEWAY_IRANDARGAH_VERSION', '2.0.10');
define('WC_GATEWAY_IRANDARGAH_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_GATEWAY_IRANDARGAH_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

/**
 * Initialize the gateway.
 * @since 2.0.0
 */
function woocommerce_irandargah_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_basename('includes/class-wc-gateway-irandargah.php');
    load_plugin_textdomain('woocommerce-gateway-irandargah', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    add_filter('woocommerce_payment_gateways', 'woocommerce_irandargah_add_gateway');
}
add_action('plugins_loaded', 'woocommerce_irandargah_init', 0);

function woocommerce_irandargah_plugin_links($links)
{
    $settings_url = add_query_arg(
        array(
            'page' => 'wc-settings',
            'tab' => 'checkout',
            'section' => 'wc_gateway_irandargah',
        ),
        admin_url('admin.php')
    );

    $plugin_links = array(
        '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'woocommerce-gateway-irandargah') . '</a>',
        '<a href="https://docs.irandargah.com">' . __('Docs', 'woocommerce-gateway-irandargah') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_irandargah_plugin_links');

/**
 * Add the gateway to WooCommerce
 * @since 2.0.0
 */
function woocommerce_irandargah_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_IranDargah';
    return $methods;
}

add_action('woocommerce_blocks_loaded', 'woocommerce_irandargah_woocommerce_blocks_support');

function woocommerce_irandargah_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/includes/class-wc-gateway-irandargah-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_IranDargah_Blocks_Support);
            }
        );
    }
}
