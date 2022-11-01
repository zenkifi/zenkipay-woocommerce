<?php

/**
 * Main Zenki Gateway Class
 *
 * @package Zenkipay/Gateways
 * @author Zenki
 *
 */

if (!defined('ABSPATH')) {
    exit();
}

require_once dirname(__DIR__) . '/lib/svix/init.php';

class WC_Zenki_Gateway extends WC_Payment_Gateway
{
    protected $GATEWAY_NAME = 'Zenkipay';
    protected $test_mode = true;
    protected $rsa_private_key;
    protected $webhook_signing_secret;
    protected $plugin_version = '1.7.3';
    protected $purchase_data_version = 'v1.1.0';
    protected $gateway_url = 'https://prod-gateway.zenki.fi';
    protected $api_url = 'https://api.zenki.fi';
    protected $js_url = 'https://resources.zenki.fi';

    public function __construct()
    {
        $this->id = 'zenkipay'; // payment gateway plugin ID
        $this->has_fields = false;
        $this->order_button_text = __('Continue with Zenkipay', 'zenkipay');
        $this->method_title = __('Zenkipay', 'zenkipay');
        $this->method_description = __('Your shoppers can pay with cryptos… any wallet, any coin!. Transaction 100% secured.', 'zenkipay');

        // Gateways can support subscriptions, refunds, saved payment methods,
        // but in this case we begin with simple payments
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();

        $this->title = __('Zenkipay', 'zenkipay');
        $this->description =
            __('Pay with cryptos… any wallet, any coin!. Transaction 100%', 'zenkipay') .
            ' <a href="' .
            esc_url('https://www.zenki.fi/shopper/') .
            '" target="_blanck">' .
            __('secured', 'zenkipay') .
            '</a>.';

        $this->enabled = $this->settings['enabled'];
        $this->test_mode = strcmp($this->settings['test_mode'], 'yes') == 0;
        $this->test_plugin_key = $this->settings['test_plugin_key'];
        $this->live_plugin_key = $this->settings['live_plugin_key'];
        $this->zenkipay_key = $this->test_mode ? $this->test_plugin_key : $this->live_plugin_key;
        $this->rsa_private_key = $this->settings['rsa_private_key'];
        $this->webhook_signing_secret = $this->settings['webhook_signing_secret'];

        add_action('wp_enqueue_scripts', [$this, 'load_scripts']);
        add_action('woocommerce_api_zenkipay_verify_payment', [$this, 'zenkipayVerifyPayment']);

        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_api_' . strtolower(get_class($this)), [$this, 'webhookHandler']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        wp_enqueue_style('zenkipay_style', plugins_url('assets/css/styles.css', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version);
        wp_enqueue_script('zenkipay_js_input', plugins_url('assets/js/zenkipay-input-controller.js', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version, true);
    }

    public function webhookHandler()
    {
        $order_id = '';
        $payload = file_get_contents('php://input');
        $response = [];
        $header_response = 'HTTP/1.1 200 OK';
        $this->logger->info('Zenkipay - Webhook => ' . $payload);

        $headers = apache_request_headers();
        $svix_headers = [];
        foreach ($headers as $key => $value) {
            $header = strtolower($key);
            $svix_headers[$header] = $value;
        }

        try {
            $secret = $this->webhook_signing_secret;
            $wh = new \Svix\Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);

            if (!($decrypted_data = $this->RSADecyrpt($json->encryptedData))) {
                throw new Exception('Unable to decrypt data.');
            }

            $this->logger->info('$decrypted_data => ' . $decrypted_data);
            $event = json_decode($decrypted_data);
            $payment = $event->eventDetails;

            if ($payment->transactionStatus != 'COMPLETED' || !$payment->merchantOrderId) {
                throw new Exception('Transaction status is not completed or merchantOrderId is empty.');
            }

            $order_id = $payment->merchantOrderId;
            $order = new WC_Order($order_id);
            $order->payment_complete();
            $order->add_order_note(sprintf("%s payment completed with Zenkipay Order Id of '%s'", $this->GATEWAY_NAME, $payment->orderId));

            update_post_meta($order->get_id(), '_zenkipay_order_id', $payment->orderId);
            update_post_meta($order->get_id(), 'zenkipay_tracking_number', '');

            wc_reduce_stock_levels($order_id);

            $response = ['success' => true, 'order_id' => $order_id];
        } catch (Exception $e) {
            $header_response = 'HTTP/1.1 500 Internal Server Error';
            $response = ['error' => true, 'msg' => $e->getMessage()];
            $this->logger->error('Zenkipay - webhookHandler: ' . $e->getMessage());
        }

        header($header_response);
        header('Content-type: application/json');
        echo json_encode($response);
        exit();
    }

    public function process_admin_options()
    {
        parent::process_admin_options();

        $post_data = $this->get_post_data();
        $mode = 'live';
        $test_mode_index = 'woocommerce_' . $this->id . '_test_mode';
        $plain_rsa_private_key = $post_data['woocommerce_' . $this->id . '_rsa_private_key'];

        if (isset($post_data[$test_mode_index]) && $post_data[$test_mode_index] == '1') {
            $mode = 'test';
        }

        $this->zenkipay_key = $post_data['woocommerce_' . $this->id . '_' . $mode . '_plugin_key'];
        $this->test_mode = $mode == 'test';

        $env = $mode == 'live' ? 'Production' : 'Test';

        $settings = new WC_Admin_Settings();

        if ($this->zenkipay_key == '') {
            $this->settings['enabled'] = '0';
            $settings->add_error('You need to enter "' . $env . ' Zenkipay key" if you want to use this plugin in this mode.');
        }

        if (!$this->validateZenkipayKey()) {
            $this->settings['enabled'] = '0';
            $settings->add_error('Something went wrong while saving this configuration, your "Zenkipay key" is incorrect.');
        }

        if (!$this->validateRSAPrivateKey($plain_rsa_private_key)) {
            $this->settings['enabled'] = '0';
            $settings->add_error('Invalid RSA private key has been provided.');
        }

        return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
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

        $webhook_url = site_url('/', 'https') . 'wc-api/';

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
                'default' => 'no',
            ],
            'test_mode' => [
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
                'default' => '',
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
                'default' => '',
                'description' =>
                    __('<b>Need a key?</b> Create your Zenkipay account', 'zenkipay') . ' <a href="' . esc_url('https://zenki.fi/') . '" target="_blanck">' . __('here', 'zenkipay') . '</a>',
                'default' => '',
            ],
            'webhook_signing_secret' => [
                'title' => __('Webhook signing secret', 'zenkipay'),
                'type' => 'password',
                'description' => __('You can get this secret from your Zenkipay Dashboard: <b>Configurations > Webhooks</b>. But first add this URL: ' . $webhook_url, 'zenkipay'),
                'default' => '',
            ],
            'rsa_private_key' => [
                'title' => __('RSA private key', 'zenkipay'),
                'type' => 'textarea',
                'css' => 'width: 600px; height: 600px;',
                'description' => __('Copy and paste in this text box the RSA private key that you generated or provided during the configuration of your Zenkipay account.', 'zenkipay'),
            ],
        ];
    }

    public function payment_fields()
    {
        $this->logo = plugins_url('assets/icons/logo.png', __DIR__);
        if ($this->description) {
            // We add instructions for test mode.
            if ($this->test_mode) {
                $this->description .= __(' (TEST MODE).');
                $this->description = trim($this->description);
            }
        }

        include_once dirname(__DIR__) . '/templates/payment.php';
    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $redirect_url = $this->get_return_url($order);
        // $redirect_url = $order->get_checkout_payment_url(true);

        return [
            'result' => 'success',
            'redirect' => $redirect_url,
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
         * Check if WC is installed and activated
         */
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // WooCommerce is NOT enabled!
            echo wp_kses_post('<div class="error"><p>');
            echo __('Zenkipay needs WooCommerce plugin is installed and activated to work.', 'zenkipay');
            echo wp_kses_post('</p></div>');
            return;
        }
    }

    /**
     * Checks if the plain RSA private key is valid
     *
     * @param string $plain_rsa_private_key Plain RSA private key
     *
     * @return boolean
     */
    protected function validateRSAPrivateKey($plain_rsa_private_key)
    {
        if (empty($plain_rsa_private_key)) {
            return false;
        }

        $private_key = openssl_pkey_get_private($plain_rsa_private_key);
        if (!is_resource($private_key) && !is_object($private_key)) {
            return false;
        }

        $public_key = openssl_pkey_get_details($private_key);
        if (is_array($public_key) && isset($public_key['key'])) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the Zenkipay key is valid
     *
     * @return boolean
     */
    protected function validateZenkipayKey()
    {
        $result = $this->getAccessToken();
        if (!array_key_exists('access_token', $result)) {
            return false;
        }

        return true;
    }

    public function handleTrackingNumber($data)
    {
        try {
            $url = $this->api_url . '/v1/api/tracking';
            $method = 'POST';

            $this->customRequest($url, $method, $data);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - handleTrackingNumber: ' . $e->getMessage());
            return false;
        }
    }

    public function createDispute($data)
    {
        try {
            $this->logger->info('Zenkipay - createDispute => ' . json_encode($data));
            $url = $this->api_url . '/v1/api/disputes';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            $this->logger->info('Zenkipay - createDispute => ' . $result);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - createDispute: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Zenkipay access token
     *
     * @return array
     */
    public function getAccessToken()
    {
        $ch = curl_init();
        $url = $this->gateway_url . '/public/v1/merchants/plugin/token';
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

    protected function customRequest($url, $method, $data)
    {
        $token_result = $this->getAccessToken();

        if (!array_key_exists('access_token', $token_result)) {
            $this->logger->error('Zenkipay  - customRequest: Error al obtener access_token');
            throw new Exception('Invalid access token');
        }

        $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['access_token']];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $result = curl_exec($ch);

        if ($result === false) {
            $this->logger->error('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch));
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function load_scripts()
    {
        if (!is_order_received_page()) {
            return;
        }

        $payment_args = [];
        $order_key = sanitize_key(urldecode($_REQUEST['key']));
        $order_id = sanitize_key(absint(get_query_var('order-received')));
        $order = wc_get_order($order_id);

        if (sanitize_key($order->get_order_key()) != $order_key || $order->get_payment_method() !== 'zenkipay') {
            return;
        }

        $payment_args = $this->getZenkipayPurchaseOptions($order_id);

        wp_enqueue_script('zenkipay_js_resource', $this->js_url . '/zenkipay/script/zenkipay.js', [], $this->plugin_version, true);
        wp_enqueue_script('zenkipay_js_woo', plugins_url('assets/js/znk-modal.js', ZNK_WC_PLUGIN_FILE), ['jquery', 'zenkipay_js_resource'], $this->plugin_version, true);
        wp_enqueue_style('zenkipay_checkout_style', plugins_url('assets/css/checkout-style.css', ZNK_WC_PLUGIN_FILE, [], $this->plugin_version, 'all'));
        wp_localize_script('zenkipay_js_woo', 'zenkipay_payment_args', $payment_args);
    }

    /**
     * Generates the input data for Zenkipay's modal
     *
     * @param string $order_id
     * @return Array
     */
    protected function getZenkipayPurchaseOptions($order_id)
    {
        $order = wc_get_order($order_id);
        $totalItemsAmount = 0;
        $items = [];
        $cb_url = $order->get_view_order_url();
        $failed_url = esc_url(WC()->api_request_url('zenkipay_verify_payment'));
        $service_types = [];

        foreach ($order->get_items() as $item) {
            // Get an instance of corresponding the WC_Product object
            $product = $item->get_product();
            $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());
            $product_type = $product->get_type();
            $name = trim(preg_replace('/[[:^print:]]/', '', strip_tags($item->get_name())));
            $desc = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_short_description())));
            $qty = (int) $item->get_quantity();
            $product_price = wc_get_price_excluding_tax($product); // without taxes

            // If product has variations, image is taken from here
            if ($product_type == 'variable') {
                $variable_product = new WC_Product_Variation($item->get_variation_id());
                $thumbnailUrl = wp_get_attachment_image_url($variable_product->get_image_id());
            }

            $items[] = (object) [
                'itemId' => $item->get_product_id(),
                'productName' => $name,
                'productDescription' => $desc,
                'quantity' => $qty,
                'thumbnailUrl' => $thumbnailUrl ? esc_url($thumbnailUrl) : '',
                'price' => round($product_price, 2), // without taxes
            ];

            $totalItemsAmount += $product_price * $qty;

            $service_type = $product->is_virtual() || $product->is_downloadable() ? 'SERVICE' : 'GOOD';
            array_push($service_types, $service_type);
        }

        $subtotalAmount = $totalItemsAmount + $order->get_shipping_total();
        $discountAmount = $order->get_discount_total();
        $grandTotalAmount = $order->get_total();

        $merchan_info = $this->getMerchanInfo();
        $discount_percentage = $merchan_info['discountPercentage'];
        $originalGrandTotalAmount = ($grandTotalAmount * 100) / (100 - $discount_percentage);
        $criptoLoveDiscount = $originalGrandTotalAmount - $grandTotalAmount;
        $originalDiscountAmount = 0;
        if ($discountAmount > 0) {
            $originalDiscountAmount = $discountAmount - $criptoLoveDiscount;
        }

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'zenkipayKey' => $this->zenkipay_key,
            'serviceType' => $this->getServiceType($service_types),
            'merchantOrderId' => $order_id,
            'shopperEmail' => !empty($order->get_billing_email()) ? $order->get_billing_email() : null,
            'items' => $items,
            'purchaseSummary' => [
                'currency' => get_woocommerce_currency(),
                'totalItemsAmount' => round($totalItemsAmount, 2), // without taxes
                'shipmentAmount' => round($order->get_shipping_total(), 2), // without taxes
                'subtotalAmount' => round($subtotalAmount, 2), // without taxes
                'taxesAmount' => round($order->get_total_tax(), 2),
                'discountAmount' => round($originalDiscountAmount, 2),
                'grandTotalAmount' => round($originalGrandTotalAmount, 2),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
            ],
        ];

        $payload = json_encode($purchase_data);
        $signature = $this->generateSignature($payload);
        $this->logger->info('Zenkipay - payload => ' . $payload);
        return [
            'zenkipay_key' => $this->zenkipay_key,
            'purchase_data' => $payload,
            'zenkipay_signature' => $signature,
            'cb_url' => $cb_url,
            'failed_url' => $failed_url,
            'order_id' => $order_id,
        ];
    }

    /**
     * Generates payload signature using the RSA private key
     *
     * @param string $payload Purchase data
     *
     * @return string
     */
    protected function generateSignature($payload)
    {
        $rsa_private_key = openssl_pkey_get_private($this->rsa_private_key);
        openssl_sign($payload, $signature, $rsa_private_key, 'RSA-SHA256');
        return base64_encode($signature);
    }

    /**
     * Verify payment made on the checkout page
     *
     * @return string
     */
    public function zenkipayVerifyPayment()
    {
        header('HTTP/1.1 200 OK');

        if (isset($_POST['order_id']) && isset($_POST['complete'])) {
            $complete = $_POST['complete'];
            if ($complete != '0') {
                return;
            }

            $order = wc_get_order($_POST['order_id']);
            $order->update_status('failed', 'Payment not successful.');
            //$redirect_url = esc_url($this->get_return_url($order));
            $redirect_url = $order->get_view_order_url();

            echo json_encode(['redirect_url' => $redirect_url]);
        }

        die();
    }

    /**
     * Decrypt message with RSA private key
     *
     * @param  base64_encoded string holds the encrypted message.
     * @param  integer $chunk_size Chunking by bytes to feed to the decryptor algorithm (512).
     *
     * @return String decrypted message.
     */
    public function RSADecyrpt($encrypted_msg)
    {
        $ppk = openssl_pkey_get_private($this->rsa_private_key);
        $encrypted_msg = base64_decode($encrypted_msg);

        // Decrypt the data in the small chunks
        $a_key = openssl_pkey_get_details($ppk);
        $chunk_size = ceil($a_key['bits'] / 8);

        $offset = 0;
        $decrypted = '';

        while ($offset < strlen($encrypted_msg)) {
            $decrypted_chunk = '';
            $chunk = substr($encrypted_msg, $offset, $chunk_size);

            if (openssl_private_decrypt($chunk, $decrypted_chunk, $ppk)) {
                $decrypted .= $decrypted_chunk;
            } else {
                throw new Exception('Problem decrypting the message');
            }
            $offset += $chunk_size;
        }
        return $decrypted;
    }

    /**
     * Get Merchan Info
     *
     * @return array
     */
    public function getMerchanInfo()
    {
        $method = 'GET';
        $url = $this->gateway_url . '/v1/merchants/plugin?pluginKey=' . $this->zenkipay_key;
        $result = $this->customRequest($url, $method, null);

        return json_decode($result, true);
    }

    /**
     * Get service type
     *
     * @param array $service_types
     *
     * @return string
     */
    protected function getServiceType($service_types)
    {
        $needles = ['GOOD', 'SERVICE'];
        if (empty(array_diff($needles, $service_types))) {
            return 'HYBRID';
        } elseif (in_array('GOOD', $service_types)) {
            return 'GOOD';
        } else {
            return 'SERVICE';
        }
    }
}
?>
