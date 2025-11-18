<?php

namespace Inc\PaymentMethods\Nexi;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\Framework\Support\Arr;

use Inc\DataProviders\FC_3DS20_Data_Provider;
use Inc\DataProviders\FC_Klarna_Data_Provider;
use Inc\DataProviders\FC_Pagodil_Data_Provider;

use Inc\Helpers\FC_Nexi_Helper;


class NexiPaymentGateway extends AbstractPaymentGateway
{

    // Define supported features
    public array $supportedFeatures = [
        'payment',
        'webhook',
        //   'refund',
        //   'subscriptions'
    ];

    private $nexi_xpay_alias = '';
    private $nexi_xpay_mac = '';
    private $base_url = '';

    private $nexi_accounting = '';
    private $nexi_xpay_3ds20_enabled = false;
    private $nexi_xpay_group = '';

    public function __construct()
    {
        // Initialize settings
        parent::__construct(new NexiGatewaySettings());

        $this->nexi_xpay_alias = $this->settings->get('nexi_alias');
        $this->nexi_xpay_mac = $this->settings->get('nexi_mac');
        $this->nexi_accounting = $this->settings->get('nexi_accounting');
        $this->base_url = $this->settings->getBaseURL();
        $this->nexi_xpay_3ds20_enabled = $this->settings->is3ds20Enabled();
    }

    public function get_profile_info()
    {
        if (strlen($this->nexi_xpay_alias) == 0 || strlen($this->nexi_xpay_mac) == 0) {
            /*    delete_option('xpay_available_methods');
                delete_option('xpay_logo_small');
                delete_option('xpay_logo_large');*/
            return null;
        }

        $timeStamp = (time()) * 1000;

        // MAC calculation
        $macStr = 'apiKey=' . $this->nexi_xpay_alias;
        $macStr .= 'timeStamp=' . $timeStamp;
        $macStr .= $this->nexi_xpay_mac;
        $mac = sha1($macStr);

        // Params
        $payload = array(
            'apiKey' => $this->nexi_xpay_alias,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
            'platform' => 'fluent-cart',
            'platformVers' => FLUENTCART_GATEWAY_NEXI_VERSION,
            'pluginVers' => PLUGIN_VER
        );

        $profile_info = $this->exec_curl_post_json("ecomm/api/profileInfo", $payload);

        // Check on the outcome
        if ($profile_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $profile_info['errore']['messaggio']);
            throw new \Exception(__('Response OK', PLUGIN));
        }

        $macResponseStr = 'esito=' . $profile_info['esito'];
        $macResponseStr .= 'idOperazione=' . $profile_info['idOperazione'];
        $macResponseStr .= 'timeStamp=' . $profile_info['timeStamp'];
        $macResponseStr .= $this->nexi_xpay_mac;

        $MACrisposta = sha1($macResponseStr);

        // Check on response MAC
        if ($profile_info['mac'] != $MACrisposta) {
            //   Log::actionWarning(__FUNCTION__ . ": error: " . $profile_info['mac'] . " != " . $MACrisposta);
            throw new \Exception(__('Mac verification failed', PLUGIN));
        }

        //update_option('xpay_available_methods', json_encode($profile_info['availableMethods']));


        //self::enable_apms();

        return $profile_info;
    }

    /*  public static function enable_apms()
     {
         \Nexi\WC_Nexi_Helper::enable_payment_method(WC_SETTINGS_KEY);

         foreach (\Nexi\WC_Nexi_Helper::get_xpay_available_methods() as $method) {
             if ($method['type'] === 'APM') {
                 \Nexi\WC_Nexi_Helper::enable_payment_method("woocommerce_xpay_" . $method['selectedcard'] . "_settings");

                 if (in_array(strtolower($method['selectedcard']), ['googlepay', 'applepay'])) {
                     \Nexi\WC_Nexi_Helper::enable_payment_method("woocommerce_xpay_" . $method['selectedcard'] . "_button_settings");
                 }
             }
         }
     } */

    public function get_payment_form($order, $selectedcard, $recurringPaymentRequired)
    {
        $importo = FC_Nexi_Helper::mul_bcmul($order->total_amount, 100, 0);
        $chiaveSegreta = $this->nexi_xpay_mac;

        $customer = $order->customer;

        $params = array(
            'alias' => $this->nexi_xpay_alias,
            'importo' => $importo,
            'divisa' => $order->currency,
            'mail' => $customer->email,
            'url' => get_rest_url(null, "fluent-cart-gateway-nexi-xpay/redirect/xpay/" . $order->id), //returning URL
            'url_back' => get_rest_url(null, "fluent-cart-gateway-nexi-xpay/cancel/xpay/" . $order->id), //cancel URL
            'languageId' => FC_Nexi_Helper::get_language_id(), //checkout page lang
            'descrizione' => "Fluent Cart Order: " . $order->receipt_number,
            'urlpost' => get_rest_url(null, "fluent-cart-gateway-nexi-xpay/s2s/xpay/" . $order->id), //S2S notification URL
            'selectedcard' => $selectedcard,
            'TCONTAB' => $this->nexi_accounting,
            'Note1' => 'fluent-cart',
            'Note2' => FLUENTCART_GATEWAY_NEXI_VERSION,
            'Note3' => PLUGIN_VER
        );

        /*  if ($recurringPaymentRequired) {
              if (!$this->nexi_xpay_recurring_enabled) {
                  Log::actionWarning(__FUNCTION__ . ": recurring payment for non recurring payment method");
                  throw new \Exception("Recurring not enabled");
              }

              $params['alias'] = $this->nexi_xpay_recurring_alias;

              $chiaveSegreta = $this->nexi_xpay_recurring_mac;

              // Contract number
              $md5_hash = md5($costumer_id . '@' . $order->get_order_number() . '@' . time() . '@' . get_option('nexi_unique'));
              $params['num_contratto'] = substr("RP" . base_convert($md5_hash, 16, 36), 0, 30);

              $params['tipo_servizio'] = "paga_multi"; // static param for recurring payments
              $params['tipo_richiesta'] = "PP"; //PP = First Payment
              $params['gruppo'] = $this->nexi_xpay_group;

              $params['codTrans'] = $this->get_cod_trans($order->get_order_number(), "PR");

              $macString = 'codTrans=' . $params['codTrans'];
              $macString .= 'divisa=' . $params['divisa'];
              $macString .= 'importo=' . $params['importo'];
          } else {
              // Is using CC and is logged in enable the "one click" payment
              if ($selectedcard == "CC" && is_user_logged_in() && $this->nexi_xpay_oneclick_enabled) {
                  $order_user = $order->get_user();

                  // This will store, on nexi sistems, the card data to speed up the payment process
                  $params['codTrans'] = $this->get_cod_trans($order->get_id(), "");

                  $md5_hash = md5($costumer_id . "@" . $order_user->user_email . '@' . get_option('nexi_unique'));
                  $params['num_contratto'] = substr("OC" . base_convert($md5_hash, 16, 36), 0, 30);

                  $params['tipo_servizio'] = "paga_1click";

                  if ($this->nexi_xpay_group != "") {
                      $params['gruppo'] = $this->nexi_xpay_group;
                  }

                  // Oneclick
                  $macString = 'codTrans=' . $params['codTrans'];
                  $macString .= 'divisa=' . $params['divisa'];
                  $macString .= 'importo=' . $params['importo'];
                  $macString .= 'gruppo=' . $this->nexi_xpay_group;
                  $macString .= 'num_contratto=' . $params['num_contratto'];
              } else {
                  $params['codTrans'] = $this->get_cod_trans($order->get_id(), "");
                  $macString = 'codTrans=' . $params['codTrans'];
                  $macString .= 'divisa=' . $params['divisa'];
                  $macString .= 'importo=' . $params['importo'];
              }
          }*/


        if ($selectedcard == "CC" && is_user_logged_in() && $this->settings->isOneClickEnabled()) {


            // This will store, on nexi sistems, the card data to speed up the payment process
            $params['codTrans'] = $this->get_cod_trans($order->id, "");

            $md5_hash = md5($customer->id . "@" . $customer->email . '@' . get_option('nexi_unique'));
            $params['num_contratto'] = substr("OC" . base_convert($md5_hash, 16, 36), 0, 30);

            $params['tipo_servizio'] = "paga_1click";

            if ($this->nexi_xpay_group != "") {
                $params['gruppo'] = $this->nexi_xpay_group;
            }

            // Oneclick
            $macString = 'codTrans=' . $params['codTrans'];
            $macString .= 'divisa=' . $params['divisa'];
            $macString .= 'importo=' . $params['importo'];
            $macString .= 'gruppo=' . $this->nexi_xpay_group;
            $macString .= 'num_contratto=' . $params['num_contratto'];
        } else {
            $params['codTrans'] = $this->get_cod_trans($order->id, "");
            $macString = 'codTrans=' . $params['codTrans'];
            $macString .= 'divisa=' . $params['divisa'];
            $macString .= 'importo=' . $params['importo'];
        }

        $params['mac'] = sha1($macString . $chiaveSegreta);

        if ($this->nexi_xpay_3ds20_enabled && $selectedcard == "CC") {
            $params = array_merge($params, FC_3DS20_Data_Provider::calculate_params($order));
        }

        if ($selectedcard == "PAGODIL") {
            $params = array_merge($params, FC_Pagodil_Data_Provider::calculate_params($order));
        }

        if ($selectedcard == "KLARNA") {
            $params = array_merge($params, FC_Klarna_Data_Provider::calculate_params($order));
        }

        \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_xpay_" . "codTrans", $params['codTrans']);

        return array(
            "target_url" => $this->base_url . "ecomm/ecomm/DispatcherServlet",
            "fields" => $params,
        );
    }


    private function exec_curl_post_json($path, $payload)
    {
        $connection = curl_init();

        if (!$connection) {
            throw new \Exception(__('Can\'t connect!', PLUGIN));
        }

        curl_setopt_array($connection, array(
            CURLOPT_URL => $this->base_url . $path,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => 1,
            CURLINFO_HEADER_OUT => true
        ));

        $response = curl_exec($connection);
        if ($response == false) {
            throw new \Exception(sprintf(__('CURL exec error: %s', PLUGIN), curl_error($connection)));
        }
        curl_close($connection);

        $payment_data = json_decode($response, true);

        if (!(is_array($payment_data) && json_last_error() === JSON_ERROR_NONE)) {
            throw new \Exception(__('JSON error', PLUGIN));
        }

        return $payment_data;
    }

    /**
     * Return codTrans param for XPay gateway
     *
     * @param string $payment_type
     * @return string
     */
    protected function get_cod_trans($order_id, $payment_type)
    {
        $cod_trans = '';

        switch ($payment_type) {
            case "PR":
                $cod_trans .= "PR-";
                break;
            default:
        }

        $cod_trans .= $order_id;

        return substr($cod_trans . "-" . time(), 0, 30);
    }

    public function boot()
    {
        // initialize any hanldere, webhook/ payment confirmation class if needed
    }

    #required: Return gateway metadata
    public function meta(): array
    {
        return [
            'title' => __('Nexi Gateway', PLUGIN),
            'route' => 'nexi_gateway',
            'slug' => 'nexi_gateway',
            'description' => __('Accept payments with Nexi', PLUGIN),
            'logo' => PLUGIN_URL . 'assets/images/nexi-logo.png',
            'icon' => PLUGIN_URL . 'assets/images/nexi-logo.png',
            'status' => $this->settings->get('is_active') === 'yes',
            'supported_features' => $this->supportedFeatures,
            'brand_color' => 'red',
            'upcoming' => false,
            'tag' => 'developing'
        ];
    }

    #required: Check if gateway supports a feature
    public function has(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    // Required: Process payment
    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        // Your payment processing logic here

    }

    // Required: Handle IPNs/Webhooks
    public function handleIPN()
    {
        // Process the webhook

    }


    #required: Return settings fields configuration
    public function fields(): array
    {
        // For a comprehensive guide on building gateway settings fields,
        // see the detailed [Payment Gateway Settings Fields] documentation link given below
        return [
            'nexi_info' => [
                'type' => 'notice',
                'value' => __('<br>For a correct behavior of the module, check in the configuration section of the Nexi back-office that the transaction cancellation in the event of a failed notification is set.
A POST notification by the Nexi servers is sent to the following address, containing information on the outcome of the payment. <br>
                <br>
                ' . site_url() . '/wp-json/woocommerce-gateway-nexi-xpay/s2s/(xpay|npg)/(order id)
                <br>    <br>
                The notification is essential for the functioning of the plugin, it is therefore necessary that it is not blocked or filtered by the site infrastructure.
', PLUGIN)
            ],
            'nexi_alias' => [
                'type' => 'text',
                'label' => __('Alias', PLUGIN),
                'description' => 'Given to Merchant by Nexi.',
                'help_text' => __('Given to Merchant by Nexi.', PLUGIN),
            ],
            'nexi_mac' => [
                'type' => 'text',
                'label' => __('Key MAC', PLUGIN),
                'description' => 'Given to Merchant by Nexi.',
                'help_text' => __('Given to Merchant by Nexi.', PLUGIN),
            ],
            'integration_type' => [
                'type' => 'select',
                'label' => __('Capture method', PLUGIN),
                'options' => [
                    ['value' => 'redirect', 'label' => __('Hosted Payment Page with redirect', PLUGIN)],
                    ['value' => 'build', 'label' => __('Build with embedded checkout', PLUGIN)],
                ],
                '- Select "Hosted Payment Page with redirect" if you want to use "Hosted Payment Page" integration type where the customer is redirected to XPay external checkout page.
- Select "Build with embedded checkout" if you want to use "XPay Build" integration type where the payment form is on checkout.'
            ],
            'nexi_accounting' => [
                'type' => 'select',
                'label' => __('Capture method', PLUGIN),
                'options' => [
                    ['value' => 'C', 'label' => __('Immediate', PLUGIN)],
                    ['value' => 'D', 'label' => __('Deferred', PLUGIN)],
                ],
                'description' => 'This field identifies the collection method the merchant wishes to apply to the individual transaction. If set to:
-C (immediate), the transaction, if authorized, is also collected without further intervention by the operator and regardless of the default profile set on the terminal.
-D (deferred), meaning the field is left blank, the transaction, if authorized, is handled according to the terminal profile.'
            ],
            'nexi_xpay_3ds20_enabled' => [
                'type' => 'enable',
                'label' => __('Enable 3D Secure 2 service', PLUGIN),
                'value' => 'no', // or 'no',
                'description' => __("The 3D Secure 2 protocol adopted by the main international circuits (Visa, MasterCard, American Express), introduces new authentication methods, able to improve and speed up the cardholder's purchase experience.", PLUGIN),
                'help_text' => __("The 3D Secure 2 protocol adopted by the main international circuits (Visa, MasterCard, American Express), introduces new authentication methods, able to improve and speed up the cardholder's purchase experience.", PLUGIN),
                'tooltip' => __("The 3D Secure 2 protocol adopted by the main international circuits (Visa, MasterCard, American Express), introduces new authentication methods, able to improve and speed up the cardholder's purchase experience.", PLUGIN),
            ]
        ];
    }

    // For a comprehensive guide on building gateway settings fields,
    // see the detailed documentation: [Payment Gateway Settings Fields](./payment_setting_fields.md)

    #required: Get order information for frontend
    public function getOrderInfo(array $data)
    {
        // Prepare frontend data for checkout
        $paymentArgs = [];

        // Return data for frontend
        wp_send_json([
            'status' => 'success',
            'payment_args' => $paymentArgs,
            'message' => __('Order info retrieved', Plugin)
        ], 200);
    }

    #required: Register scripts (automatically called by base gateway)
    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        // External gateway library, custom checkout scripts (if needed), otherwise return empty array
        // $gatewayLibUrl = 'https://js.yourgateway.com/v1/checkout.js';

        return [
            /*     [
                     'handle' => 'your-gateway-external-lib',
                     'src' => $gatewayLibUrl,
                 ],
                 [
                     'handle' => 'fluent-cart-your-gateway-checkout',
                     'src' => plugin_dir_url(__FILE__) . 'assets/js/your-gateway-checkout.js',
                     'deps' => ['your-gateway-external-lib'],
                     'version' => FLUENTCART_PLUGIN_VERSION
                 ]*/
        ];
    }

}