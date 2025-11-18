<?php

namespace Inc\DataProviders;

use Inc\Helpers\FC_Nexi_Helper;
use Inc\Helpers\FC_Nexi_Order_Helper;

use Carbon\Carbon;

use Exception;

class FC_Klarna_Data_Provider
{

    public static function calculate_params($order)
    {

        $params = array();

        $billing_address = $order->billing_address;
        $shipping_address = $order->shipping_address;

        $billing_first_name = $billing_address->first_name;
        $billing_last_name = $billing_address->last_name;
        $billing_address_1 = $billing_address->address_1;
        $billing_address_2 = $billing_address->address_2;
        $billing_country = $billing_address->country;
        $billing_state = $billing_address->state;
        $billing_city = $billing_address->city;
        $billing_postcode = $billing_address->postcode;

        $shipping_first_name = $shipping_address->first_name;
        $shipping_last_name = $shipping_address->last_name;
        $shipping_city = $shipping_address->city;
        $shipping_country = $shipping_address->country;
        $shipping_state = $shipping_address->state;
        $shipping_address_1 = $shipping_address->address_1;
        $shipping_address_2 = $shipping_address->address_2;
        $shipping_postcode = $shipping_address->postcode;

        try {
            $params['nome'] = $billing_first_name;
            $params['cognome'] = $billing_last_name;

            $allItems = $order->order_items;

            $itemsNumber = 0;

            $itemsAmountCalculated = 0;

            foreach ($allItems as $item) {
                $itemsNumber++;

                $params['Item_quantity_' . $itemsNumber] = $item->quantity;
                $params['Item_amount_' . $itemsNumber] = FC_Nexi_Helper::mul_bcmul($item->unit_price, 100, 0);
                $params['Item_name_' . $itemsNumber] = self::escapeKlarnaSpecialCharacters($item->title);

                $itemsAmountCalculated += $item->unit_price * $item->quantity;
            }

            $extraFee = $order->total_amount - $itemsAmountCalculated;

            if ($extraFee > 0) {
                $itemsNumber++;

                $params['Item_quantity_' . $itemsNumber] = 1;
                $params['Item_amount_' . $itemsNumber] = FC_Nexi_Helper::mul_bcmul($extraFee, 100, 0);
                $params['Item_name_' . $itemsNumber] = self::escapeKlarnaSpecialCharacters("Extra Fee");
            }

            $params['itemsNumber'] = $itemsNumber;

            if ($shipping_address) {
                $params['Dest_city'] = $shipping_city;
                $params['Dest_country'] = FC_Nexi_Helper::iso3166_getAlpha3($shipping_country);
                $params['Dest_street'] = $shipping_address_1;
                $params['Dest_street2'] = $shipping_address_2;
                $params['Dest_cap'] = $shipping_postcode;
                $params['Dest_state'] = FC_Nexi_Helper::getStateCode($shipping_postcode);
                $params['Dest_name'] = $shipping_first_name;
                $params['Dest_surname'] = $shipping_last_name;
            } else {
                $params['Dest_city'] = $billing_city;
                $params['Dest_country'] = FC_Nexi_Helper::iso3166_getAlpha3($billing_country);
                $params['Dest_street'] = $billing_address_1;
                $params['Dest_street2'] = $billing_address_2;
                $params['Dest_cap'] = $billing_postcode;
                $params['Dest_state'] = FC_Nexi_Helper::getStateCode($billing_postcode);
                $params['Dest_name'] = $billing_first_name;
                $params['Dest_surname'] = $billing_last_name;
            }

            $params['Bill_city'] = $billing_city;
            $params['Bill_country'] = FC_Nexi_Helper::iso3166_getAlpha3($billing_country);
            $params['Bill_street'] = $billing_address_1;
            $params['Bill_street2'] = $billing_address_2;
            $params['Bill_cap'] = $billing_postcode;
            $params['Bill_state'] = FC_Nexi_Helper::getStateCode($billing_postcode);
            $params['Bill_name'] = $billing_first_name;
            $params['Bill_surname'] = $billing_last_name;
        } catch (Exception $exc) {
            //  Log::actionWarning($exc->getMessage());
        }

        return $params;


    }


    private static function escapeKlarnaSpecialCharacters($string)
    {
        $pattern = "/[^a-zA-Z0-9!@#$%^&() _+\\-=\\[\\]{};':\"\\|,.?]/";

        return preg_replace($pattern, '', $string);
    }

}