<?php

use Inc\Helpers\FC_Nexi_Helper;


class FC_Cronjobs
{
    private $nexi_settings = [];

    public function register()
    {
        add_filter('cron_schedules', [$this, 'my_add_nexi_schedules_for_polling']);
        add_action('wp_nexi_update_npg_payment_methods', [$this, 'wp_nexi_update_npg_payment_methods_executor']);
        add_action('wp_nexi_polling', [$this, 'wp_nexi_polling_executor']);

        $this->add_cronjobs();
    }


    private function add_cronjobs()
    {
        //chcks if the task is not already scheduled
        if (!wp_next_scheduled('wp_nexi_polling') && !FC_Nexi_Helper::is_nexi_build() && FC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule', 'wp_nexi_polling');
        }

        if (!wp_next_scheduled('wp_nexi_update_npg_payment_methods') && !FC_Nexi_Helper::is_nexi_build() && FC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule_2h', 'wp_nexi_update_npg_payment_methods');
        }
    }

    private function wp_nexi_polling_executor()
    {
        /*   $args = array(
               'payment_method' => 'xpay',
               'status' => ['wc-pending'],
               'orderby' => 'date',
               'order' => 'ASC',
           );

           $orders = wc_get_orders($args);

           foreach ($orders as $order) {
               $authorizationRecord = \Nexi\WC_Gateway_NPG_API::getInstance()->get_order_status($order->get_id());

               if ($authorizationRecord === null) {
                   \Nexi\Log::actionWarning(__FUNCTION__ . ': authorization operation not found for order: ' . $order->get_id());
                   continue;
               }

               $orderObj = new \WC_Order($order->get_id());

               switch ($authorizationRecord['operationResult']) {
                   case NPG_OR_AUTHORIZED:
                   case NPG_OR_EXECUTED:
                       $completed = $orderObj->payment_complete(\Nexi\OrderHelper::getOrderMeta($order->get_id(), "_npg_" . "orderId", true));

                       if ($completed) {
                           \Nexi\WC_Save_Order_Meta::saveSuccessNpg(
                               $order->get_id(),
                               $authorizationRecord
                           );
                       } else {
                           \Nexi\Log::actionWarning(__FUNCTION__ . ': unable to change order status: ' . $orderObj->get_status());
                       }
                       break;

                   case NPG_OR_PENDING:
                       \Nexi\Log::actionWarning(__FUNCTION__ . ': operation not in a final status yet');
                       break;

                   case NPG_OR_CANCELED:
                   case NPG_OR_CANCELLED:
                       \Nexi\Log::actionWarning(__FUNCTION__ . ': payment canceled');

                       if ($order->get_status() != 'cancelled') {
                           $order->update_status('cancelled');
                       }
                       break;

                   case NPG_OR_DECLINED:
                   case NPG_OR_DENIED_BY_RISK:
                   case NPG_OR_THREEDS_FAILED:
                   case NPG_OR_3DS_FAILED:
                   case NPG_OR_FAILED:
                       \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - operation: ' . json_encode($authorizationRecord));

                       if ($order->get_status() != 'cancelled') {
                           $orderObj->update_status('failed');
                       }

                       $orderObj->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay'));
                       break;

                   default:
                       \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - not managed operation status: ' . json_encode($authorizationRecord));
                       break;
               }
           }*/
    }

    private function my_add_nexi_schedules_for_polling($schedules)
    {
        // add a 'nexi_polling_schedule' schedule to the existing set
        $schedules['nexi_polling_schedule'] = array(
            'interval' => 300,
            'display' => __('5 minutes')
        );

        $schedules['nexi_polling_schedule_2h'] = array(
            'interval' => 7200,
            'display' => __('2 hours'),
        );

        return $schedules;
    }

    private function wp_nexi_update_npg_payment_methods_executor()
    {
        /*try {
            \Nexi\WC_Gateway_NPG_API::getInstance()->get_profile_info();
        } catch (\Exception $exc) {
            \Nexi\Log::actionWarning(__FUNCTION__ . $exc->getMessage());
        }*/
    }


}