<?php

/*
 * Plugin Name: Zenkipay
 * Plugin URI: https://github.com/zenkifi/zenkipay-woocommerce
 * Description: Your shoppers can pay with cryptos… any wallet, any coin!. Transaction 100% secured.
 * Author: Zenki
 * Author URI: https://zenki.fi/
 * Text Domain: zenkipay
 * Version: 1.4.0
 */

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

uninstall_zenkipay_plugin();

/**
 * Deletes plugin settings
 *
 * @return void
 */
function uninstall_zenkipay_plugin()
{
    if (function_exists('is_multisite') && is_multisite()) {
        if (false == is_super_admin()) {
            return;
        }

        delete_site_option('woocommerce_zenkipay_settings');
    } else {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        delete_option('woocommerce_zenkipay_settings');
    }
}
