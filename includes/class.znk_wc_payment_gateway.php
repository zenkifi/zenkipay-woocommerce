<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Main Zenki Gateway Class
 */
class WC_Zenki_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->base_url = esc_url('https://dev-resources.zenki.fi/');

        $this->id = 'zenkipay'; // payment gateway plugin ID
        $this->icon = apply_filters('woocommerce_zenkipay_icon', plugins_url('./../assets/icons/logo.png', __FILE__));
        $this->has_fields = false;
        $this->order_button_text = __('Continue with Zenkipay', 'zenkipay');

        $this->method_title = __('Zenkipay', 'zenkipay');
        $this->method_description =
            __('Your shoppers can pay with cryptos… any wallet, any coin!. Transaction 100%', 'zenkipay') .
            ' <a href="' .
            esc_url('https://zenki.fi/') .
            '" target="_blanck">' .
            __('secured', 'zenkipay') .
            '</a>';

        // Gateways can support subscriptions, refunds, saved payment methods,
        // but in this case we begin with simple payments
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();

        $this->title = __('Zenkipay', 'zenkipay');
        $this->description =
            __('Pay with cryptos… any wallet, any coin!. Transaction 100%', 'zenkipay') . ' <a href="' . esc_url('https://zenki.fi/') . '" target="_blanck">' . __('secured', 'zenkipay') . '</a>';
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');

        $this->test_plugin_key = sanitize_text_field($this->get_option('test_plugin_key'));
        $this->live_plugin_key = sanitize_text_field($this->get_option('live_plugin_key'));
        $this->zenkipay_key = $this->testmode ? $this->test_plugin_key : $this->live_plugin_key;

        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_api_wc_zenkipay_gateway', [$this, 'zenkipay_verify_payment']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        wp_enqueue_style('zenkipay_style', plugins_url('assets/css/styles.css', ZNK_WC_PLUGIN_FILE), [], '1.1.2');
        wp_enqueue_script('zenkipay_js_input', plugins_url('assets/js/zenkipay-input-controller.js', ZNK_WC_PLUGIN_FILE), [], '1.1.2', true);
        $this->load_scripts();
    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {
        $header_template =
            '
            <div class="znk-onboarding-header">
                <div class="znk-title">
                    <img class="znk-img" alt="Zenkipay" src="' .
            plugins_url('./../assets/icons/logo-tagline.svg', __FILE__) .
            '"/>
                </div>
                <div class="znk-coin-container">
                    <img class="znk-coin znk-bit" alt="Zenkipay" src="' .
            plugins_url('./../assets/icons/bitcoin.svg', __FILE__) .
            '"/>
                    <img class="znk-coin znk-usd" alt="Zenkipay" src="' .
            plugins_url('./../assets/icons/usd-coin.svg', __FILE__) .
            '"/>
                </div>
            </div>';

        $this->form_fields = [
            'zenkipay-header' => [
                'title' => wp_kses_post($header_template),
                'description' => '',
                'type' => 'title',
            ],
            'enabled' => [
                'title' => __('Enable/Disable', 'zenkipay'),
                'label' => __('Enable Zenkipay', 'zenkipay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ],
            'testmode' => [
                'title' => __('Test mode', 'zenkipay'),
                'label' => __('Enable test mode', 'zenkipay'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode using sandbox network.', 'zenkipay'),
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'test_plugin_key' => [
                'title' => __('Sandbox Zenkipay key', 'zenkipay'),
                'type' => 'text',
                'description' =>
                    __('Prior to accepting live crypto payments, you can test crypto payments in a safe Zenkipay sandbox environment. Create your Zenkipay account', 'zenkipay') .
                    ' <a href="' .
                    esc_url('https://zenki.fi/') .
                    '" target="_blanck">' .
                    __('here', 'zenkipay') .
                    '</a>',
                'default' => '',
            ],
            'live_plugin_key' => [
                'title' => __('Production Zenkipay key', 'zenkipay'),
                'type' => 'text',
                'description' => __('Need a key? Create your Zenkipay account', 'zenkipay') . ' <a href="' . esc_url('https://zenki.fi/') . '" target="_blanck">' . __('here', 'zenkipay') . '</a>',
                'default' => '',
            ],
        ];
    }

    public function payment_fields()
    {
        if ($this->description) {
            // We add instructions for test mode.
            if ($this->testmode) {
                $this->description .= __(' TEST MODE.');
                $this->description = trim($this->description);
            }
            // Display the description with <p> tags.
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    /**
     * Verify payment made on the checkout page
     *
     * @return void
     */
    public function zenkipay_verify_payment()
    {
        if (isset($_POST['order_id']) && isset($_POST['complete'])) {
            $complete = sanitize_text_field($_POST['complete']);
            $order_id = sanitize_key($_POST['order_id']);
            $order = wc_get_order($_POST['order_id']);

            if ($complete == '1') {
                $order->payment_complete();
                wc_reduce_stock_levels($order_id);
                $order->add_order_note('Payment processed and approved successfully.');
            } else {
                $order->update_status('failed', 'Payment not successful.');
            }

            WC()->cart->empty_cart();

            $redirect_url = esc_url($this->get_return_url($order));
            echo json_encode(['redirect_url' => $redirect_url]);
        }

        die();
    }

    /**
     * Handles admin notices
     *
     * @return void
     */
    public function admin_notices()
    {
        if ('no' == $this->enabled) {
            return;
        }

        /**
         * Check if plugin key is provided
         */
        if ((!$this->live_plugin_key && !$this->testmode) || (!$this->test_plugin_key && $this->testmode)) {
            echo wp_kses_post('<div class="error"><p>');
            echo sprintf(
                __(
                    'Zenkipay is almost ready. Provide your Zenki "Pay Button" Zenkipay key <a href="%s">here</a>. Or get your Zenkipay keys <a href="https://zenki.fi/" target="_blank">here</a>.',
                    'zenkipay',
                ),
                esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=zenkipay')),
            );
            echo wp_kses_post('</p></div>');
            return;
        }
    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function load_scripts()
    {
        if (!is_checkout_pay_page()) {
            return;
        }
        wp_enqueue_script('zenkipay_js_resource', $this->base_url . 'zenkipay/script/zenkipay.js', [], '1.1.2', true);
        wp_enqueue_script('zenkipay_js_woo', plugins_url('assets/js/zenkipay-babel.js', ZNK_WC_PLUGIN_FILE), ['jquery', 'zenkipay_js_resource'], '1.1.2', true);

        $zenkipay_key = $this->zenkipay_key;
        if (get_query_var('order-pay')) {
            $order_key = sanitize_key(urldecode($_REQUEST['key']));
            $order_id = sanitize_key(absint(get_query_var('order-pay')));
            $order = wc_get_order($order_id);
            $txnref = sanitize_key('WOOC_' . $order_id . '_' . time());
            $currency = get_option('woocommerce_currency');

            foreach ($order->get_items() as $item_id => $item) {
                // Get an instance of corresponding the WC_Product object
                $product = $item->get_product();
                $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());
                $items[] = (object) [
                    'itemId' => sanitize_key($item_id),
                    'quantity' => intval($item->get_quantity()),
                    'thumbnailUrl' => $thumbnailUrl ? esc_url($thumbnailUrl) : '',
                    'price' => intval($product->get_price()),
                ];
            }

            if (sanitize_key($order->get_order_key()) == $order_key) {
                $cancel_url = esc_url($order->get_cancel_order_url());
                $total_amount = intval($order->get_total());
                $country = $order->get_billing_country();

                $payment_args = compact('order_id', 'cancel_url', 'total_amount', 'zenkipay_key', 'currency', 'items', 'country');
                $payment_args['cb_url'] = esc_url(WC()->api_request_url('WC_Zenkipay_Gateway'));
            }

            update_post_meta($order_id, '_znk_payment_txn_ref', $txnref);
        }

        wp_localize_script('zenkipay_js_woo', 'zenkipay_payment_args', $payment_args);
    }

    public function process_admin_options()
    {
        $post_data = $this->get_post_data();
        $mode = 'live';
        $testmode_index = 'woocommerce_' . $this->id . '_testmode';

        if (isset($post_data[$testmode_index]) && $post_data[$testmode_index] == '1') {
            $mode = 'test';
        }

        $this->zenkipay_key = $post_data['woocommerce_' . $this->id . '_' . $mode . '_plugin_key'];

        $env = $mode == 'live' ? 'Production' : 'Sandbox';

        if ($this->zenkipay_key == '') {
            $settings = new WC_Admin_Settings();
            $settings->add_error('You need to enter "' . $env . ' Zenkipay key" if you want to use this plugin in this mode.');
            return;
        }

        if (!$this->validateSettings()) {
            return;
        }

        return parent::process_admin_options();
    }

    public function validateSettings()
    {
        $response = $this->getMerchantInfo();

        if (!isset($response['access_token'])) {
            $settings = new WC_Admin_Settings();
            $settings->add_error(' Something went wrong while saving this configuration, your "Zenkipay key" is incorrect.');
            return false;
        }

        return true;
    }

    public function getMerchantInfo()
    {
        $url = 'https://dev-gateway.zenki.fi/public/v1/merchants/plugin/token';

        $ch = curl_init();
        $payload = $this->zenkipay_key;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:text/plain']);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);
        $response = null;
        if ($result === false) {
            $this->logger->error('Curl error ' . curl_error($ch));
            return $response;
        }

        $response = json_decode($result, true);

        curl_close($ch);
        return $response;
    }
}
?>
