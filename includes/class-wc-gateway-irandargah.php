<?php
/**
 * IranDargah Payment Gateway
 *
 * Provides a IranDargah Payment Gateway.
 *
 * @class  woocommerce_irandargah
 * @package WooCommerce
 * @category Payment Gateways
 * @author IranDargah
 */
class WC_Gateway_IranDargah extends WC_Payment_Gateway
{

    /**
     * Version
     *
     * @var string
     */
    public $version;

    /**
     * @access protected
     * @var array $data_to_send
     */
    protected $data_to_send = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version = WC_GATEWAY_IRANDARGAH_VERSION;
        $this->id = 'irandargah';
        $this->method_title = __('IranDargah', 'woocommerce-gateway-irandargah');
        $this->method_description = sprintf(__('IranDargah payment gateway for woocommerce.', 'woocommerce-gateway-irandargah'), '<a href="https://irandargah.com">', '</a>');
        $this->icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__))) . '/assets/images/icon.svg';
        $this->debug_email = get_option('admin_email');
        $this->available_countries = array('IR');
        $this->available_currencies = (array) apply_filters('woocommerce_gateway_irandargah_available_currencies', array('IRR', 'IRT'));

        $this->supports = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        // Setup default merchant data.
        $this->merchant_id = $this->get_option('merchantId');
        $this->wsdl_url = 'https://dargaah.com/wsdl';
        $this->url = 'https://dargaah.com/payment';
        $this->validate_url = 'https://dargaah.com/verification';
        $this->title = $this->get_option('title');
        $this->response_url = add_query_arg('wc-api', 'wc_gateway_irandargah', home_url('/'));
        $this->description = $this->get_option('description');
        $this->enable_logging = 'yes' === $this->get_option('enable_logging');

        // Setup the test data, if in test mode.
        if ('yes' === $this->get_option('sandbox')) {
            $this->url = 'https://dargaah.com/sandbox/payment';
            $this->validate_url = 'https://dargaah.com/sandbox/verification';
            $this->add_sandbox_admin_settings_notice();
        }

        add_action('woocommerce_api_wc_gateway_irandargah', array($this, 'check_response'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_irandargah', array($this, 'receipt_page'), 10, 1);
        add_action('admin_notices', array($this, 'admin_notices'));

    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 2.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'title' => array(
                'title' => __('Title', 'woocommerce-gateway-irandargah'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-irandargah'),
                'default' => __('IranDargah', 'woocommerce-gateway-irandargah'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-gateway-irandargah'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-irandargah'),
                'default' => 'پرداخت با تمام کارت های بانکی عضو شتاب از طریق ایران درگاه',
                'desc_tip' => true,
            ),
            'sandbox' => array(
                'title' => __('IranDargah Sandbox', 'woocommerce-gateway-irandargah'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in development mode.', 'woocommerce-gateway-irandargah'),
                'default' => 'no',
            ),
            'merchantId' => array(
                'title' => __('Merchant ID', 'woocommerce-gateway-irandargah'),
                'type' => 'text',
                'description' => __('This is the merchant id, received from IranDargah.', 'woocommerce-gateway-irandargah'),
                'default' => '',
            ),
            'connection_method' => array(
                'title' => __('Gateway connection method', 'woocommerce-gateway-irandargah'),
                'type' => 'select',
                'description' => __('connect to irandargah webservice by selecting one of available methods.', 'woocommerce-gateway-irandargah'),
                'options' => array(
                    null => __('Choose one', 'woocommerce-gateway-irandargah'),
                    'REST_POST' => __('REST-POST', 'woocommerce-gateway-irandargah'),
                    'REST_GET' => __('REST-GET', 'woocommerce-gateway-irandargah'),
                    'SOAP' => __('SOAP', 'woocommerce-gateway-irandargah'),
                ),
                'default' => 'REST_POST',
            ),
            'currency' => array(
                'title' => __('Store active curreny', 'woocommerce-gateway-irandargah'),
                'type' => 'select',
                'description' => __('Select your default active curreny in shop.', 'woocommerce-gateway-irandargah'),
                'options' => array(
                    null => __('Choose one', 'woocommerce-gateway-irandargah'),
                    'IRR' => __('IRR', 'woocommerce-gateway-irandargah'),
                    'IRT' => __('IRT', 'woocommerce-gateway-irandargah'),
                ),
                'default' => in_array(get_woocommerce_currency(), $this->available_currencies) ? get_woocommerce_currency() : null,
            ),
            'enable_logging' => array(
                'title' => __('Enable Logging', 'woocommerce-gateway-irandargah'),
                'type' => 'checkbox',
                'label' => __('Enable transaction logging for gateway.', 'woocommerce-gateway-irandargah'),
                'default' => 'no',
            ),
        );
    }

    /**
     * add_sandbox_admin_settings_notice()
     * Add a notice to the merchant_id fields when in test mode.
     *
     * @since 2.0.0
     */
    public function add_sandbox_admin_settings_notice()
    {
        $this->form_fields['merchantId']['description'] .= '<br /><strong>' . __('Sandbox is currently in use.', 'woocommerce-gateway-irandargah') . '</strong>';
    }

    /**
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_valid_for_use() {
        $is_available          = false;
        $is_available_currency = in_array(get_woocommerce_currency(), $this->available_currencies);

        if ($is_available_currency && $this->merchant_id) {
            $is_available = true;
        }

        return $is_available;
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 2.0.0
     */
    public function admin_options()
    {
        if (in_array(get_woocommerce_currency(), $this->available_currencies)) {
            parent::admin_options();
        } else {
            ?>
			<h3><?php _e('IranDargah', 'woocommerce-gateway-irandargah');?></h3>
			<div class="inline error"><p><strong><?php _e('Gateway Disabled', 'woocommerce-gateway-irandargah');?></strong> <?php echo sprintf(__('Choose Iranian Rial or Iranian Toman as your store currency in %1$sGeneral Settings%2$s to enable the IranDargah Gateway.', 'woocommerce-gateway-irandargah'), '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=general')) . '">', '</a>'); ?></p></div>
			<?php
}
    }

    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    /**
     * Generate the IranDargah button link.
     *
     * @since 2.0.0
     */
    public function generate_irandargah_form($order_id)
    {

        global $woocommerce;

        $order = wc_get_order($order_id);
        // Construct variables for post

        $this->data_to_send = array(
            // Merchant details
            'merchantID' => $this->get_option('sandbox') == 'no' ? $this->merchant_id : 'TEST',
            'callbackURL' => add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_gateway_irandargah')),

            // Billing details
            'mobile' => self::get_order_prop($order, 'billing_phone'),
            'orderId' => self::get_order_prop($order, 'id'),
            'amount' => intval($order->get_total()) * ($this->get_option('currency') == 'IRT' ? 10 : 1),
            'description' => "سفارش شماره: " . self::get_order_prop($order, 'id') . " خریدار: " . self::get_order_prop($order, 'billing_first_name') . " " . self::get_order_prop($order, 'billing_last_name'),
        );

        $response = $this->send_request_to_irandargah_gateway(
            $this->get_option('sandbox') == 'yes' ? 'SANDBOX' : $this->get_option('connection_method'),
            'payment',
            $this->data_to_send
        );

        if (intval($response->status) != 200) {
            $note = $response->message;
            $order->add_order_note($note);
            wp_redirect($woocommerce->cart->get_checkout_url());

            exit;
        } else {
            $note = $response->message;
            $order->add_order_note($note);
            if ($this->get_option('sandbox') == 'yes') {
                header('Location: https://dargaah.com/sandbox/ird/startpay/' . $response->authority);
                exit;
            }

            header('Location: https://dargaah.com/ird/startpay/' . $response->authority);
            exit;
        }
    }

    /**
     * Reciept page.
     *
     * Display text and a button to direct the user to IranDargah.
     *
     * @since 2.0.0
     */
    public function receipt_page($order)
    {
        try {
            $this->generate_irandargah_form($order);
        } catch (\Exception $ex) {
            echo __('Error in connecting to gateway', 'woocommerce-gateway-irandargah');
        }
    }

    /**
     * Check IranDargah response.
     *
     * @since 2.0.0
     */
    public function check_response()
    {
        $this->handle_response(
            $this->get_option('connection_method') == 'REST_GET' && $this->get_option('sandbox') == 'no' ? stripslashes_deep($_GET) : stripslashes_deep($_POST)
        );

        // Notify IranDargah that information has been received
        header('HTTP/1.0 200 OK');
        flush();
    }

    /**
     * Check IranDargah ITN validity.
     *
     * @param array $data
     * @since 2.0.0
     */
    public function handle_response($data)
    {
        global $woocommerce;

        $this->log(PHP_EOL
            . '----------'
            . PHP_EOL . 'IranDargah Callback Data has been received'
            . PHP_EOL . '----------'
        );
        $this->log('Get posted data');
        $this->log('IranDargah Data: ' . print_r($data, true));

        $order_id = absint($_GET['wc_order']);
        $order = wc_get_order($order_id);

        $this->log_order_details($order);

        // Check if order has already been processed
        if ('completed' === self::get_order_prop($order, 'status')) {
            $this->log('Order has already been processed');
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

            exit;
        }

        if ($data['code'] != 100) {
            $this->handle_payment_failed($data, $order);
        } else {
            $this->handle_payment_complete($data, $order);
        }

        $this->log(PHP_EOL
            . '----------'
            . PHP_EOL . 'End processing callback data'
            . PHP_EOL . '----------'
        );

    }

    /**
     * Handle logging the order details.
     *
     * @since 2.0.0
     */
    public function log_order_details($order)
    {
        if (version_compare(WC_VERSION, '3.0.0', '<')) {
            $customer_id = get_post_meta($order->get_id(), '_customer_user', true);
        } else {
            $customer_id = $order->get_user_id();
        }

        $details = "Order Details:"
        . PHP_EOL . 'customer id:' . $customer_id
        . PHP_EOL . 'order id:   ' . $order->get_id()
        . PHP_EOL . 'parent id:  ' . $order->get_parent_id()
        . PHP_EOL . 'status:     ' . $order->get_status()
        . PHP_EOL . 'total:      ' . $order->get_total()
        . PHP_EOL . 'currency:   ' . $order->get_currency()
        . PHP_EOL . 'key:        ' . $order->get_order_key()
            . "";

        $this->log($details);
    }

    /**
     * This function handles payment complete request by IranDargah.
     *
     * @param array $data should be from the Gatewy ITN callback.
     * @param WC_Order $order
     */
    public function handle_payment_complete($data, $order)
    {
        global $woocommerce;
        $this->log('- Complete Payment');
        $order->add_order_note(__('payment has been completed', 'woocommerce-gateway-irandargah'));
        $order->update_meta_data('irandargah_payment_amount', $data['amount']);
        $order_id = self::get_order_prop($order, 'id');

        $this->data_to_send = [
            'merchantID' => $this->get_option('merchantId'),
            'authority' => $data['authority'],
            'amount' => $data['amount'],
            'orderId' => $data['orderId'],
        ];

        if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
            $this->irandargah_display_success_message($order_id);
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

            exit;
        }

        if (get_post_meta($order_id, 'irandargah_transaction_status', true) == 100) {
            $this->irandargah_display_success_message($order_id);
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

            exit;
        }

        $verification_result = $this->send_request_to_irandargah_gateway(
            $this->get_option('sandbox') == 'yes' ? 'SANDBOX' : $this->get_option('connection_method'),
            'verification',
            $this->data_to_send
        );

        if ($verification_result->status == 100) {

            //completed
            $note = sprintf(__('Transaction payment status: %s', 'woocommerce-irandargah-gateway'), $verification_result->status);
            $note .= '<br/>';
            $note .= sprintf(__('Transaction ref id: %s', 'woocommerce-irandargah-gateway'), $verification_result->refId);
            $note .= '<br/>';
            $note .= sprintf(__('Payer card number: %s', 'woocommerce-irandargah-gateway'), $verification_result->cardNumber);
            $order->add_order_note($note);

            update_post_meta($order_id, 'irandargah_transaction_status', $verification_result->status);
            update_post_meta($order_id, 'irandargah_transaction_order_id', $order_id);
            update_post_meta($order_id, 'irandargah_transaction_refid', $verification_result->refId);
            update_post_meta($order_id, 'irandargah_transaction_amount', $verification_result->amount);
            update_post_meta($order_id, 'irandargah_payment_card_no', $verification_result->cardNumber);

            $order->payment_complete($verification_result->refId);
            $order->update_status('completed');
            $woocommerce->cart->empty_cart();
            $this->irandargah_display_success_message($order_id);

            do_action('WC_IranDargah_Return_from_Gateway_ReSuccess', $order_id, $verification_result->refId);

            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

            exit;
        } else {
            $order->add_order_note($this->failed_message);
            $order->update_status('failed');
            wp_redirect($woocommerce->cart->get_checkout_url());

            exit;
        }
    }

    /**
     * @param $data
     * @param $order
     */
    public function handle_payment_failed($data, $order)
    {
        global $woocommerce;
        $order->add_order_note($this->failed_message . '<br />' . $data['message']);
        $order->update_status('pending');
        wp_redirect($woocommerce->cart->get_checkout_url());

        exit;
    }

    /**
     * Send Request to IranDargah Gateway
     *
     * @since 2.0.0
     *
     * @param string $method
     * @param mixed  $data
     * @return mixed
     */
    private function send_request_to_irandargah_gateway($option, $method, $data)
    {
        global $woocommerce;

        $this->data_to_send = strpos($option, 'GET') ? array_merge($this->data_to_send, ['action' => 'GET']) : $this->data_to_send;

        $response = strpos($option, 'REST') || $option == 'SANDBOX' ? $this->send_curl_request(
            $method == 'payment' ? $this->url : $this->validate_url,
            $this->data_to_send
        ) : $this->send_soap_request(
            $method == 'payment' ? 'IRDPayment' : 'IRDVerification',
            $this->data_to_send
        );

        $order = new WC_Order($this->data_to_send['orderId']);
        if (is_null($response)) {
            if ($method == 'payment') {
                $note = __('Error in sending request for connecting to gateway', 'woocommerce-gateway-irandargah');
                $order->add_order_note($note);
                wp_redirect($woocommerce->cart->get_checkout_url());

                exit;
            } else {
                $order->update_status('failed');
                $note = __('Error in sending request for transaction\'s verification', 'woocommerce-gateway-irandargah');
                $order->add_order_note($note);
                wp_redirect($woocommerce->cart->get_checkout_url());

                exit;
            }
        }

        return $response;
    }

    /**
     * Send curl request
     *
     * @param string $url
     * @param mixed $data
     * @return mixed
     */
    private function send_curl_request($url, $data)
    {
        $response = null;
        $iteration = 0;

        do {

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ));

            $response = curl_exec($curl);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error) {
                $this->log('Error in sending request');
                return null;
            }

            $response = json_decode($response);

            $iteration++;

        } while (is_null($response) && $iteration < 3);

        return $response;
    }

    /**
     * Send SOAP request
     *
     * @param string $method
     * @param mixed $data
     * @return mixed
     */
    private function send_soap_request($method, $data)
    {
        $client = new SoapClient($this->wsdl_url, ['cache_wsdl' => WSDL_CACHE_NONE]);

        $response = null;
        $iteration = 0;

        do {
            try {
                $response = $client->__soapCall($method, [$data]);
            } catch (\SoapFault $fault) {
                $this->log($fault->getMessage());
            }

            $iteration++;

        } while (is_null($response) && $iteration < 3);

        return $response;
    }

    /**
     * Shows a success message
     *
     * This message is configured at the admin page of the gateway.
     *
     * @since 2.0.0
     *
     * @see irandargah_checkout_return_handler()
     *
     * @param $order_id
     */
    private function irandargah_display_success_message($order_id)
    {
        $refid = get_post_meta($order_id, 'irandargah_refid', true);
        $notice = wpautop(wptexturize($this->success_message));
        $notice = str_replace("{refid}", $refid, $notice);
        $notice = str_replace("{order_id}", $order_id, $notice);
        wc_add_notice($notice, 'success');
    }

    /**
     * Shows a failure message for the unsuccessful payments.
     *
     * This message is configured at the admin page of the gateway.
     *
     * @since 2.0.0
     *
     * @param $order_id
     */
    private function irandargah_display_failed_message($order_id, $status)
    {
        $refid = get_post_meta($order_id, 'irandargah_refid', true);
        $msg = get_post_meta($order_id, 'irandargah_verification_message', true);
        $notice = wpautop(wptexturize($this->failed_message));
        $notice = str_replace("{refid}", $refid, $notice);
        $notice = str_replace("{order_id}", $order_id, $notice);
        $notice = $notice . "<br>" . $msg;
        wc_add_notice($notice, 'error');
    }

    /**
     * Shows an invalid order message.
     *
     * @since 2.0.0
     */
    private function irandargah_display_invalid_order_message()
    {
        $notice = '';
        $notice .= __('There is no order number referenced.', 'woocommerce-gateway-irandargah');
        $notice .= '<br/>';
        $notice .= __('Please try again or contact the site administrator in case of a problem.', 'woocommerce-gateway-irandargah');
        wc_add_notice($notice, 'error');
    }

    /**
     * Log system processes.
     * @since 2.0.0
     */
    public function log($message)
    {
        if ('yes' === $this->get_option('sandbox') || $this->enable_logging) {
            if (empty($this->logger)) {
                $this->logger = new WC_Logger();
            }
            $this->logger->add('irandargah', $message);
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
     *  Show possible admin notices
     *
     */
    public function admin_notices()
    {
        if (!empty($this->merchant_id)) {
            return;
        }

        echo '<div class="error irandargah-merchant-id-message"><p>'
        . __('IranDargah requires a merchant id to work.', 'woocommerce-gateway-irandargah')
            . '</p></div>';
    }
}
