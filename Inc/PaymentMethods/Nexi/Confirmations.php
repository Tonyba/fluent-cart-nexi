<?php

namespace Inc\PaymentMethods\Nexi;

use FluentCart\Api\StoreSettings;
use Inc\Helpers\FC_Nexi_Helper;
use FluentCart\App\Helpers\Status;

class Confirmations
{
    public function init()
    {
        register_rest_route(
            'fluent-cart-gateway-nexi-xpay',
            '/s2s/xpay/(?P<id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::s2s',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'fluent-cart-gateway-nexi-xpay',
            '/redirect/xpay/(?P<id>\d+)',
            array(
                'methods' => array('GET', 'POST'),
                'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::redirect',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'fluent-cart-gateway-nexi-xpay',
            '/cancel/xpay/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::cancel',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'fluent-cart-gateway-nexi-xpay',
            '/process_account/xpay' . '/(?P<id>\d+)',
            array(
                'methods' => array('POST'),
                'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::process_account',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            )
        );
        /*
                register_rest_route(
                    'fluent-cart-gateway-nexi-xpay',
                    '/gpay/redirect/(?P<id>\d+)',
                    array(
                        'methods' => 'GET',
                        'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::gpayRedirect',
                        'args' => [
                            'id' => [],
                        ],
                        'permission_callback' => '__return_true',
                    )
                );

                register_rest_route(
                    'fluent-cart-gateway-nexi-xpay',
                    '/xpay/gpay/result/(?P<id>\d+)',
                    array(
                        'methods' => 'GET',
                        'callback' => '\Inc\PaymentMethods\Nexi\Confirmations::xpayGpayResult',
                        'args' => [
                            'id' => [],
                        ],
                        'permission_callback' => '__return_true',
                    )
                );*/
    }

    public static function s2s($data)
    {
        $params = $data->get_params();
        $order_id = $params["id"];

        error_log(__FUNCTION__ . ": S2S notification for order id " . $order_id);

        $status = "500";
        $payload = array(
            "outcome" => "OK",
            "order_id" => $order_id,
        );

        try {
            if (FC_Nexi_Helper::validate_return_mac($_POST)) {

                $order = \FluentCart\App\Models\Order::where('id', $order_id)->first();

                if ($_POST['esito'] == "OK") {

                    if (!in_array($order->status, [Status::ORDER_COMPLETED, Status::ORDER_PROCESSING])) {

                        FC_Nexi_Helper::saveSuccessXPay(
                            $order,
                            $_POST['alias'],
                            FC_Nexi_Helper::nexi_array_key_exists($_POST, 'num_contratto') ? $_POST['num_contratto'] : '',
                            $_POST['codTrans'],
                            FC_Nexi_Helper::nexi_array_key_exists($_POST, 'scadenza_pan') ? $_POST['scadenza_pan'] : ''
                        );


                        $completed = $order->updateStatus(Status::ORDER_COMPLETED);
                    }

                    if (!isset($completed) || $completed) {
                        $status = "200";
                        $payload = array(
                            "outcome" => "OK",
                            "order_id" => $order_id,
                        );
                    }
                } else if ($_POST['esito'] == "PEN") {

                    if ($order->payment_status != Status::PAYMENT_PENDING) {
                        $order->updatePaymentStatus(Status::PAYMENT_PENDING);
                    }

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );

                } else {
                    if (!in_array($order->status, [Status::ORDER_FAILED, Status::ORDER_CANCELED])) {
                        $order->update_status('failed');
                    }

                    $order->updateMeta('_xpay_' . 'last_error', $_POST["messaggio"]);


                    $order->addLog(
                        'Payment error',
                        $_POST["messaggio"],
                        'error'
                    );

                    //  $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $_POST["messaggio"]);

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );
                }
            }

            $order->updateMeta('_xpay_' . 'post_notification_timestamp', time());

        } catch (\Exception $exc) {
            error_log(__FUNCTION__ . ": " . $exc->getMessage());
        }

        return new \WP_REST_Response($payload, $status, []);
    }

    public static function redirect($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];

        $order = \FluentCart\App\Models\Order::where('id', $order_id)->first();

        $post_notification_timestamp = $order->getMeta('_xpay_' . 'post_notification_timestamp', 'default');

        //s2s not recived, so we need to update the order based the data recived in params
        if ($post_notification_timestamp == "") {
            error_log(__FUNCTION__ . ": s2s notification for order id " . $order_id . " not recived, changing oreder status from request params");

            if ($params['esito'] == "OK") {
                if (!in_array($order->get_status(), [Status::ORDER_COMPLETED, Status::ORDER_PROCESSING])) {

                    FC_Nexi_Helper::saveSuccessXPay(
                        $order,
                        $params['alias'],
                        FC_Nexi_Helper::nexi_array_key_exists($params, 'num_contratto') ? $params['num_contratto'] : '',
                        $params['codTrans'],
                        $params['scadenza_pan']
                    );

                    $order->payment_complete($params["codTrans"]);
                }
            } else if ($params['esito'] == "PEN") {
                if ($order->payment_status != Status::PAYMENT_PENDING) {
                    // if order in this status, it is considerated as completed/payed
                    $order->updatePaymentStatus(Status::PAYMENT_PENDING);
                }
            } else {
                if (!in_array($order->get_status(), [Status::ORDER_FAILED, Status::ORDER_CANCELED])) {
                    $order->update_status(Status::ORDER_FAILED);
                }

                $order->updateMeta('_xpay_' . 'last_error', $params["messaggio"]);

                // $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $params["messaggio"]);
            }
        }

        error_log(__FUNCTION__ . ": user redirect for order id " . $order_id . ' - ' . (array_key_exists('esito', $params) ? $params['esito'] : ''));

        if ($order->payment_status == Status::PAYMENT_PENDING || $order->status == Status::ORDER_CANCELED) {

            $lastErrorXpay = $order->getMeta('_xpay_' . 'last_error', 'default');

            if ($lastErrorXpay != "") {
                fluent_cart_add_log(__('Payment error, please try again', PLUGIN), " (" . htmlentities($lastErrorXpay) . ")", 'error', ['log_type' => 'payment']);
            }

            $paymentErrorXpay = $order->getMeta('_xpay_' . 'payment_error', 'default');

            if ($paymentErrorXpay != "") {
                fluent_cart_add_log(__('Payment error', PLUGIN), htmlentities($paymentErrorXpay), 'error', ['log_type' => 'payment']);
            }

            return new \WP_REST_Response(
                "redirecting failed...",
                "303",
                ["Location" => get_site_url() . '/cart']
            );
        }

        return new \WP_REST_Response(
            "redirecting success...",
            "303",
            ["Location" => $order->getReceiptUrl()]
        );
    }

    public static function cancel($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];
        $order = \FluentCart\App\Models\Order::where('id', $order_id)->first();

        if (($params['esito'] ?? '') === "ERRORE" && $params['warning']) {

            if (stripos($params['warning'], 'deliveryMethod') !== false) {
                $order->updateMeta('_xpay_' . 'payment_error', __('It was not possible to process the payment, check that the shipping method set is correct.', PLUGIN));
            } else {
                $order->updateMeta('_xpay_' . 'payment_error', __('Payment error: ', PLUGIN) . $params['warning']);
            }
        } else {
            $order->updateMeta('_xpay_' . 'last_error', __('Payment has been cancelled.', PLUGIN));
        }

        return new \WP_REST_Response(
            "failed...",
            "303",
            array("Location" => (new StoreSettings())->getCartPage())
        );
    }

    public static function process_account($data)
    {
        try {
            $params = $data->get_params();

            $order_id = $params["id"];
            $order = \FluentCart\App\Models\Order::where('id', $order_id)->first();

            $amount = FC_Nexi_Helper::mul_bcmul($_POST['amount'], 1, 0);

            if (!is_numeric($amount)) {
                throw new \Exception(__('Invalid amount.', PLUGIN));
            }

            $codTrans = $order->getMeta('codTrans', 'default');

            if (empty($codTrans)) {
                fluent_cart_add_log(
                    sprintf(__('Unable to capture order %s', PLUGIN), $order_id),
                    "Order does not have XPay capture reference.",
                    'error',
                    ['log_type' => 'payment']
                );
                throw new \Exception(sprintf(__('Unable to capture order %s. Order does not have XPay capture reference.', PLUGIN), $order_id));
            }

            return FC_Nexi_Helper::account($codTrans, $amount, $order->currency);

        } catch (\Exception $exc) {
            return new \WP_Error("broke", $exc->getMessage());
        }
    }
}

