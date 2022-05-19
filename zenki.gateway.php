<?php
/*
 * Plugin Name: Zenkipay
 * Plugin URI: 
 * Description: Your shoppers can pay with cryptos… any wallet, any coin!. Transaction 100% secured.
 * Author: Zenki
 * Author URI: https://zenki.fi/
 * Text Domain: zenkipay
 * Domain Path: /assets/languages/
 * Version: 1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
  
define( 'ZNK_WC_PLUGIN_FILE', __FILE__ );
define( 'ZNK_WC_DIR_PATH', plugin_dir_path( ZNK_WC_PLUGIN_FILE ) );

//Languages traslation
load_plugin_textdomain( 'zenkipay', false, dirname( plugin_basename( __FILE__ ) ) . '/assets/languages/' );

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action( 'plugins_loaded', 'zenkipay_init_gateway_class', 0);

function zenkipay_init_gateway_class() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    require_once( ZNK_WC_DIR_PATH . 'includes/class.znk_wc_payment_gateway.php' );

    add_filter( 'woocommerce_payment_gateways', 'add_gateway_class' );

    function znk_plugin_action_links( $links ) {
        $zenki_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=zenkipay' ) );
        array_unshift( $links, "<a title='Zenkipay Settings Page' href='$zenki_settings_url'>" .__('Settings', 'zenkipay') . "</a>" );
    
        return $links;
      }

    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'znk_plugin_action_links' );

   /**
   * Add the Gateway to WooCommerce
   *
   * @return Array Gateway list with our gateway added
   */
    function add_gateway_class( $gateways ) {
        $gateways[] = 'WC_Zenki_Gateway';
        return $gateways;
    }
}