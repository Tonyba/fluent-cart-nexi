<?php

namespace Inc\PaymentMethods\Nexi;

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
                'callback' => '\Inc\PaymentMethods\Confirmations::s2s',
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
                'callback' => '\Inc\PaymentMethods\Confirmations::redirect',
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
                'callback' => '\Inc\PaymentMethods\Confirmations::cancel',
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
                'callback' => '\Inc\PaymentMethods\Confirmations::process_account',
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
                        'callback' => '\Inc\PaymentMethods\Confirmations::gpayRedirect',
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
                        'callback' => '\Inc\PaymentMethods\Confirmations::xpayGpayResult',
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

        // Log::actionInfo(__FUNCTION__ . ": S2S notification for order id " . $order_id);

        $status = "500";
        $payload = array(
            "outcome" => "KO",
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
            //  Log::actionInfo(__FUNCTION__ . ": " . $exc->getTraceAsString());
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
            // Log::actionInfo(__FUNCTION__ . ": s2s notification for order id " . $order_id . " not recived, changing oreder status from request params");

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

        //  Log::actionInfo(__FUNCTION__ . ": user redirect for order id " . $order_id . ' - ' . (array_key_exists('esito', $params) ? $params['esito'] : ''));

        if ($order->payment_status == Status::PAYMENT_PENDING || $order->status == Status::ORDER_CANCELED) {

            $lastErrorXpay = $order->getMeta('_xpay_' . 'last_error', 'default');

            if ($lastErrorXpay != "") {
                /* if (isset(WC()->session)) {
                     wc_add_notice(__('Payment error, please try again', 'woocommerce-gateway-nexi-xpay') . " (" . htmlentities($lastErrorXpay) . ")", 'error');
                 }*/
            }

            $paymentErrorXpay = $order->getMeta('_xpay_' . 'payment_error', 'default');

            if ($paymentErrorXpay != "") {
                /* if (isset(WC()->session)) {
                     wc_add_notice(htmlentities($paymentErrorXpay), 'error');
                 }*/
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
            array("Location" => $order->getViewUrl())
        );
    }

    public static function process_account($data)
    {
        try {
            $params = $data->get_params();

            $order_id = $params["id"];
            $order = \FluentCart\App\Models\Order::where('id', $order_id)->first();

            $amount = FC_Nexi_Helper::mul_bcmul($_POST['amount'], 100, 0);

            if (!is_numeric($amount)) {
                throw new \Exception(__('Invalid amount.', PLUGIN));
            }

            $codTrans = $order->getMeta('codTrans', 'default');

            if (empty($codTrans)) {
                throw new \Exception(sprintf(__('Unable to capture order %s. Order does not have XPay capture reference.', PLUGIN), $order_id));
            }

            return FC_Nexi_Helper::account($codTrans, $amount, $order->currency);

        } catch (\Exception $exc) {
            return new \WP_Error("broke", $exc->getMessage());
        }
    }
}

