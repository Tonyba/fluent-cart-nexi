<?php

namespace Inc\Helpers;

class FC_Nexi_Order_Helper
{


    public static function get_billing_phone($order)
    {

        $meta = $order->orderMeta;
        $phone = '';

        foreach ($meta as $meta_item) {
            $decodedValue = $meta->getDecodedValue();
            $phone = $decodedValue['billing_phone'];
        }

        return $phone;
    }

    public static function get_shipping_phone($order)
    {

        $meta = $order->orderMeta;
        $phone = '';

        foreach ($meta as $meta_item) {
            $decodedValue = $meta->getDecodedValue();
            $phone = $decodedValue['shipping_phone'];
        }

        return $phone;
    }

}