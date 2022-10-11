<?php
/*
 * Plugin Name: Zenkipay
 * Plugin URI: https://github.com/zenkifi/zenkipay-woocommerce
 * Description: Your shoppers can pay with cryptos… any wallet, any coin!. Transaction 100% secured.
 * Author: Zenki
 * Author URI: https://zenki.fi/
 * Text Domain: zenkipay
 * Version: 1.6.11
 */

if (!defined('ABSPATH')) {
    exit();
}

define('ZNK_WC_PLUGIN_FILE', __FILE__);

//Languages traslation
load_plugin_textdomain('zenkipay', false, dirname(plugin_basename(__FILE__)) . '/languages/');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action('plugins_loaded', 'zenkipay_init_gateway_class', 0);
add_filter('woocommerce_thankyou_order_received_text', 'override_thankyou_text', 10, 2);
add_filter('the_title', 'woo_title_order_received', 10, 2);
add_action( 'woocommerce_checkout_process', 'addDiscount');
add_filter('woocommerce_cart_totals_coupon_label', 'discountLabel', 10, 2);

function zenkipay_init_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'includes/class.znk_wc_payment_gateway.php';

    add_filter('woocommerce_payment_gateways', 'add_gateway_class');

    function znk_plugin_action_links($links)
    {
        $zenki_settings_url = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout&section=zenkipay'));
        array_unshift($links, "<a title='Zenkipay Settings Page' href='$zenki_settings_url'>" . __('Settings', 'zenkipay') . '</a>');

        return $links;
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'znk_plugin_action_links');

    /**
     * Add the Gateway to WooCommerce
     *
     * @return Array Gateway list with our gateway added
     */
    function add_gateway_class($gateways)
    {
        $gateways[] = 'WC_Zenki_Gateway';
        return $gateways;
    }
}

add_action(
    'save_post_shop_order',
    function (int $postId, \WP_Post $post, bool $update) {
        $logger = wc_get_logger();

        // Ignore order (post) creation
        if ($update !== true || !is_admin()) {
            return;
        }

        // Here comes your code...
        $order = new WC_Order($postId);
        $logger->info('Zenkipay - order_id => ' . $order->get_id());

        $payment_method = $order->get_payment_method();
        $logger->info('Zenkipay - payment_method => ' . $payment_method);
        // Checks if the order was pay with zenkipay
        if ($payment_method !== 'zenkipay') {
            return;
        }

        // Get the meta data in an unprotected array
        $zenkipay_order_id = $order->get_meta('_zenkipay_order_id');
        $tracking_number = $order->get_meta('zenkipay_tracking_number');
        $logger->info('Zenkipay - zenkipay_order_id => ' . $zenkipay_order_id);
        $logger->info('Zenkipay - tracking_number => ' . $tracking_number);

        // Checks if we hace the required date to send the tracking number to Zenkipay
        if (empty($zenkipay_order_id) || empty($tracking_number)) {
            return;
        }

        $data = [['orderId' => $zenkipay_order_id, 'merchantOrderId' => $order->get_id(), 'trackingId' => $tracking_number]];
        $zenkipay = new WC_Zenki_Gateway();
        $zenkipay->handleTrackingNumber($data);
    },
    10,
    3
);

// Order Received Thank You Text
function override_thankyou_text($thankyoutext, $order)
{
    if ($order->get_payment_method() != 'zenkipay') {
        return $thankyoutext;
    }

    $icon = plugins_url('zenkipay/assets/icons/clock.svg', __DIR__);
    $text = __('Your order was created successfully and is pending payment.', 'zenkipay');
    $added_text = '<img src="' . $icon . '" height="40" width="40" alt="pending" style="margin-right: 5px;" /> <span>' . $text . '</span>';
    return $added_text;
}

// // Order Received Thank You Title
function woo_title_order_received($title, $id)
{
    if (function_exists('is_order_received_page') && is_order_received_page() && get_the_ID() === $id) {
        $title = 'Pending order';
    }
    return $title;
}

function addDiscount() {
    // Obtenemos el valor del cripto love
    $zenkipay = new WC_Zenki_Gateway();
    $merchan_info = $zenkipay->getMerchanInfo();
    $discount_percentage = $merchan_info['discountPercentage'];
    
    $totalAmount = WC()->cart->get_total('number');
    $baseDiscount = ($totalAmount * $discount_percentage) / 100;
    
    $todate = date("dmyGis");
    $coupon_code = 'criptolove'.$todate;
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code); // Coupon code
    $coupon->set_description( 'Cripto Love');
    $coupon->set_amount($baseDiscount); //* Discount amount
    $coupon->set_usage_limit( 1 ); // Times this coupon can be used
    $coupon->set_usage_limit_per_user( 1 ); // Times this coupon can be used per user
    $coupon->save();

    $array_coupons = array($coupon_code);

    foreach ( WC()->cart->get_applied_coupons() as $code ) {
        array_push($array_coupons, $code);
    }

    WC()->cart->set_applied_coupons($array_coupons);
}

function discountLabel($label, $coupon) {

    if (strpos($coupon->code, 'criptolove') !== false) {
        return __('Cripto Love');
    }
    return $label;
}
