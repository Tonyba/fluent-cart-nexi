<?php

namespace Inc\DataProviders;

use Inc\Helpers\FC_Nexi_Helper;
use Inc\Helpers\FC_Nexi_Order_Helper;

use Carbon\Carbon;

class FC_Pagodil_Data_Provider
{


    public static function calculate_params($order)
    {

        $params = array();

        $billing_address = $order->billing_address;
        $shipping_address = $order->shipping_address;

        $billing_email = $billing_address->email;
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

            $params['itemsAmount'] = FC_Nexi_Helper::mul_bcmul($order->total_amount, 1, 0);

            $allItems = $order->order_items;

            $itemsNumber = 0;

            foreach ($allItems as $item) {
                $itemsNumber++;

                $params['Item_code_' . $itemsNumber] = $item->post_id;
                $params['Item_quantity_' . $itemsNumber] = $item->quantity;
                $params['Item_amount_' . $itemsNumber] = FC_Nexi_Helper::mul_bcmul($item->line_total, 1, 0);
                $params['Item_description_' . $itemsNumber] = $item->title;

                $categoryIds = wp_get_post_terms($item->post_id, 'product-categories', ['fields' => 'ids']);
                $params['Item_category_' . $itemsNumber] = implode(", ", self::getCategories($categoryIds));
            }

            $params['itemsNumber'] = $itemsNumber;

            $params['shipIndicator'] = self::getShipIndicator($order);

            //  $params['numberOfInstalment'] = \Nexi\OrderHelper::getOrderMeta($order->get_id(), "installments", true);
            $params['numberOfInstalment'] = $order->getMeta('installments', 'default');

            $xpaySettings = FC_Nexi_Helper::getXPaySettings();

            if ($xpaySettings['pd_product_code'] !== null && $xpaySettings['pd_product_code'] !== "") {
                $params['pagodilOfferID'] = $xpaySettings['pd_product_code'];
            }

            $phone = FC_Nexi_Order_Helper::get_billing_phone($order);

            if (isset($phone)) {
                $phone = trim($phone);

                if (strpos($phone, "+") === false) {
                    $phone = '+39' . $phone;
                }

                if ($phone[3] == '3') {
                    $params['Buyer_msisdn'] = $phone;
                }
            }

            if ($shipping_address) {
                $params['Dest_city'] = $shipping_city;
                $params['Dest_country'] = FC_Nexi_Helper::iso3166_getAlpha3($shipping_country);
                $params['Dest_street'] = $shipping_address_1;
                $params['Dest_street2'] = $shipping_address_2;
                $params['Dest_cap'] = $shipping_postcode;
                $params['Dest_state'] = FC_Nexi_Helper::getStateCode($shipping_postcode);
            }

            $params['Bill_city'] = $billing_city;
            $params['Bill_country'] = FC_Nexi_Helper::iso3166_getAlpha3($billing_country);
            $params['Bill_street'] = $billing_address_1;
            $params['Bill_street2'] = $billing_address_2;
            $params['Bill_cap'] = $billing_postcode;
            $params['Bill_state'] = FC_Nexi_Helper::getStateCode($billing_postcode);

            if ($xpaySettings['pd_field_name_cf']) {
                $fiscalCode = $order->getMeta('pd_field_name_cf', 'default');

                if ($fiscalCode != "") {
                    $params['OPTION_CF'] = $fiscalCode;
                }
            }

            $user_id = $order->customer->user_id;

            if ($user_id != 0) {
                $user = $order->customer;

                if ($user->get_date_created()) {
                    $params['chAccDate'] = $user->created_at->format("Y-m-d");
                }

                $params['nbPurchaseAccount'] = FC_3DS20_Data_Provider::get3ds20OrderInLastSixMonth();
                $params['destinationNameIndicator'] = FC_3DS20_Data_Provider::get3ds20CheckName($user, $shipping_first_name, $shipping_last_name);
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }

        return $params;
    }

    private static function getCategories($categoryIds)
    {
        $categories = array();

        foreach ($categoryIds as $id) {
            $term = get_term_by('id', $id, 'product-categories');

            $categories[] = $term->name;
        }

        return $categories;
    }

    private static function getShipIndicator($order)
    {
        if (!$order->shipping_address) {
            return "05";
        }

        if (
            $order->shipping_address->city == $order->billing_address->city
            && FC_Nexi_Helper::iso3166_getAlpha3($order->shipping_address->country) == FC_Nexi_Helper::iso3166_getAlpha3($order->billing_address->country)
            && $order->shipping_address->address_1 == $order->billing_address->address_1
            && $order->shipping_address->postcode == $order->billing_address->postcode
        ) {

            if ($order->shipping_address->address_2 !== null) {
                if ($order->shipping_address->address_2 == $order->billing_address->address_2) {
                    return "01";
                } else {
                    return "03";
                }
            }

            return "01";
        } else {
            return "03";
        }
    }

}