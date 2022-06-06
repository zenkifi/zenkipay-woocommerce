<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Main Zenki Gateway Class
 */
class WC_Zenki_Gateway extends WC_Payment_Gateway
{
    protected $GATEWAY_NAME = 'Zenkipay';

    public function __construct()
    {
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
        $this->testmode = $this->get_option('testmode') === 'yes';

        $this->test_plugin_key = sanitize_text_field($this->get_option('test_plugin_key'));
        $this->live_plugin_key = sanitize_text_field($this->get_option('live_plugin_key'));
        $this->zenkipay_key = $this->testmode ? $this->test_plugin_key : $this->live_plugin_key;

        $this->base_url = $this->testmode ? 'https://dev-gateway.zenki.fi' : 'https://uat-gateway.zenki.fi';
        $this->base_url_js = $this->testmode ? 'https://dev-resources.zenki.fi' : 'https://uat-resources.zenki.fi';

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        wp_enqueue_style('zenkipay_style', plugins_url('assets/css/styles.css', ZNK_WC_PLUGIN_FILE), [], '1.3.0');
        wp_enqueue_script('zenkipay_js_input', plugins_url('assets/js/zenkipay-input-controller.js', ZNK_WC_PLUGIN_FILE), [], '1.3.0', true);
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
        $zenkipay_order_id = $_POST['zenkipay_order_id'];

        $order = new WC_Order($order_id);
        $order->payment_complete();
        $order->add_order_note(sprintf("%s payment completed with Zenkipay Order Id of '%s'", $this->GATEWAY_NAME, $zenkipay_order_id));
        $order->save();

        update_post_meta($order->get_id(), '_zenkipay_order_id', $zenkipay_order_id);

        $this->updateZenkipayOrder($zenkipay_order_id, $order_id);

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
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

        if (!$this->validateZenkipayKey()) {
            return;
        }

        return parent::process_admin_options();
    }

    protected function validateZenkipayKey()
    {
        $result = $this->getAccessToken();
        if (!array_key_exists('access_token', $result)) {
            $settings = new WC_Admin_Settings();
            $settings->add_error(' Something went wrong while saving this configuration, your "Zenkipay key" is incorrect.');
            return false;
        }

        return true;
    }

    public function getAccessToken()
    {
        $ch = curl_init();
        $url = $this->base_url . '/public/v1/merchants/plugin/token';
        $payload = $this->zenkipay_key;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:text/plain']);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);

        if ($result === false) {
            $this->logger->error('Curl error ' . curl_error($ch));
            return [];
        }

        curl_close($ch);

        return json_decode($result, true);
    }

    protected function updateZenkipayOrder($zenkipay_order_id, $order_id)
    {
        try {
            $token_result = $this->getAccessToken();
            if (!array_key_exists('access_token', $token_result)) {
                $this->logger->error('Zenkipay - updateZenkipayOrder: Error al obtener access_token');
                return false;
            }

            $url = $this->base_url . '/v1/orders/' . $zenkipay_order_id;
            $payload = json_encode(['zenkipayKey' => $this->zenkipay_key, 'merchantOrderId' => $order_id]);
            $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['access_token']];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            $result = curl_exec($ch);

            $this->logger->info('#updateZenkipayOrder => ' . $result);

            if ($result === false) {
                $this->logger->error('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch));
                return false;
            }

            curl_close($ch);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - updateZenkipayOrder: ' . $e->getMessage());
            $this->logger->error('Zenkipay - updateZenkipayOrder: ' . $e->getTraceAsString());

            return false;
        }
    }

    /**
     * payment_scripts function.
     *
     * Outputs scripts used for openpay payment
     *
     * @access public
     */
    public function payment_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        global $woocommerce;

        wp_enqueue_script('zenkipay_js_resource', $this->base_url_js . '/zenkipay/script/zenkipay.js', [], '1.3.0', true);
        wp_enqueue_script('zenkipay_js_woo', plugins_url('assets/js/znk-modal.js', ZNK_WC_PLUGIN_FILE), ['jquery', 'zenkipay_js_resource'], '1.3.0', true);

        $items = [];
        foreach ($woocommerce->cart->get_cart() as $cart_item) {
            // Get an instance of corresponding the WC_Product object
            $product = wc_get_product($cart_item['product_id']);
            $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());
            $items[] = (object) [
                'itemId' => $cart_item['product_id'],
                'productName' => $product->get_title(),
                'productDescription' => $product->get_description(),
                'quantity' => (int) $cart_item['quantity'],
                'thumbnailUrl' => $thumbnailUrl ? esc_url($thumbnailUrl) : '',
                'price' => round($product->get_price(), 2),
            ];
        }

        $payment_args = [
            'zenkipay_key' => $this->zenkipay_key,
            'country' => $woocommerce->cart->get_customer()->get_billing_country(),
            'total_amount' => $woocommerce->cart->total,
            'currency' => get_woocommerce_currency(),
            'items' => $items,
        ];

        wp_localize_script('zenkipay_js_woo', 'zenkipay_payment_args', $payment_args);
    }
}
?>
