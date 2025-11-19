<?php

namespace Inc\DataProviders;
use Inc\Helpers\FC_Nexi_Helper;
use Carbon\Carbon;

class FC_3DS20_Data_Provider
{

    public static function calculate_params($order)
    {
        $params = array();

        $billing_address = $order->billing_address;
        $shipping_address = $order->shipping_address;

        $billing_full_name = explode(' ', $billing_address->name);
        $shipping_full_name = explode(' ', $shipping_address->name);

        $billing_email = $billing_address->email;
        $billing_phone = $order->customer->phone;
        $billing_first_name = $billing_full_name[0];
        $billing_last_name = $billing_full_name[1];
        $billing_address_1 = $billing_address->address_1;
        $billing_address_2 = $billing_address->address_2;
        $billing_country = $billing_address->country;
        $billing_state = $billing_address->state;
        $billing_city = $billing_address->city;
        $billing_postcode = $billing_address->postcode;


        $shipping_first_name = $shipping_full_name[0];
        $shipping_last_name = $shipping_full_name[1];
        $shipping_city = $shipping_address->city;
        $shipping_country = $shipping_address->country;
        $shipping_state = $shipping_address->state;
        $shipping_address_1 = $shipping_address->address_1;
        $shipping_address_2 = $shipping_address->address_2;
        $shipping_postcode = $shipping_address->postcode;

        try {

            $params['Buyer_email'] = $billing_email;
            $params['Buyer_account'] = $billing_email;

            if (strpos($billing_phone, "+") !== false) {
                $params['Buyer_homePhone'] = $billing_phone;
            } else if ($billing_phone) {
                $params['Buyer_homePhone'] = "+39" . $billing_phone;
            }

            $params['Dest_city'] = $shipping_city;
            $params['Dest_country'] = FC_Nexi_Helper::iso3166_getAlpha3($shipping_country);
            $params['Dest_street'] = $shipping_address_1;
            $params['Dest_street2'] = $shipping_address_2;
            $params['Dest_cap'] = $shipping_postcode;
            $params['Dest_state'] = FC_Nexi_Helper::getStateCode($shipping_postcode);
            $params['Bill_city'] = $billing_city;
            $params['Bill_country'] = FC_Nexi_Helper::iso3166_getAlpha3($billing_country);
            $params['Bill_street'] = $billing_address_1;
            $params['Bill_street2'] = $billing_address_2;
            $params['Bill_cap'] = $billing_postcode;
            $params['Bill_state'] = FC_Nexi_Helper::getStateCode($billing_postcode);

            $user_id = $order->customer->user_id;

            if ($user_id != 0) {
                $user = $order->customer;

                if ($user->created_at) {
                    $params['chAccDate'] = $user->created_at->format("Y-m-d");
                }

                $params['chAccAgeIndicator'] = static::get3ds20AccountDateIndicator($user->created_at ? $user->created_at : false);
                $params['nbPurchaseAccount'] = self::get3ds20OrderInLastSixMonth();
                $params['destinationAddressUsageDate'] = static::get3ds20LastUsagedestinationAddress($order->id, $shipping_city, $shipping_country, $shipping_address_1, $shipping_address_2, $shipping_postcode, $shipping_state);
                $params['destinationAddressUsageIndicator'] = static::get3ds20FirstUsagedestinationAddress($order->id, $shipping_city, $shipping_country, $shipping_address_1, $shipping_address_2, $shipping_postcode, $shipping_state);
                $params['destinationNameIndicator'] = static::get3ds20CheckName($user, $billing_first_name, $billing_last_name);
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }

        $fieldsGroups = array(
            array(
                "Buyer_email" => true,
                "Buyer_homePhone" => false,
                "Buyer_workPhone" => false,
                "Buyer_msisdn" => false,
                "Buyer_account" => false
            ),
            array(
                "Dest_city" => true,
                "Dest_country" => true,
                "Dest_street" => true,
                "Dest_street2" => false,
                "Dest_street3" => false,
                "Dest_cap" => true,
                "Dest_state" => true
            ),
            array(
                "Bill_city" => true,
                "Bill_country" => true,
                "Bill_street" => true,
                "Bill_street2" => false,
                "Bill_street3" => false,
                "Bill_cap" => true,
                "Bill_state" => true
            )
        );

        $returnedParams = array();
        foreach ($params as $k => $v) {
            if ($v != "") {
                $returnedParams[$k] = $v;
            }
        }


        foreach ($fieldsGroups as $fieldsGroup) {
            $inThisGroup = false;
            foreach ($returnedParams as $k => $v) {
                $inThisGroup = $inThisGroup || key_exists($k, $fieldsGroup);
            }
            if ($inThisGroup) {
                $presentAllRequired = true;
                foreach ($fieldsGroup as $param => $isRequired) {
                    if ($isRequired) {
                        if (!key_exists($param, $returnedParams)) {
                            $presentAllRequired = false;
                        }
                    }
                }

                if (!$presentAllRequired) {
                    foreach ($fieldsGroup as $param => $isRequired) {
                        unset($returnedParams[$param]);
                    }
                }
            }
        }

        return $returnedParams;
    }


    private static function get3ds20AccountDateIndicator($date)
    {
        $today = date("Y-m-d");

        if ($date == false) {
            // Account not registred
            return '01';
        }

        if ($date->format("Y-m-d") == $today) {
            // Account Created in this transaction
            return '02';
        }

        $newDate = new \DateTime($today . ' - 30 day');

        if ($date->format("Y-m-d") >= $newDate->format("Y-m-d")) {
            // Account created in last 30 days
            return '03';
        }

        $newDate = new \DateTime($today . ' - 60 day');

        if ($date->format("Y-m-d") >= $newDate->format("Y-m-d")) {
            // Account created from 30 to 60 days ago
            return '04';
        }

        if ($date->format("Y-m-d") < $newDate->format("Y-m-d")) {
            // Account created more then 60 days ago
            return '05';
        }
    }

    private static function get3ds20LastUsagedestinationAddress($order_id, $city, $country, $street_1, $street_2, $postcode, $state)
    {


        $user_id = get_current_user_id();
        $customer_id = FC_Nexi_Helper::get_customer_id_by_user_id($user_id);

        $customer_orders = \FluentCart\App\Models\Order::where('customer_id', $customer_id)
            ->whereNotIn('id', [$order_id])
            ->orderBy('created_at', 'desc')->get();

        if (count($customer_orders) == 0) {
            //Account Created in this transaction
            return null;
        }

        return $customer_orders[0]->created_at;
    }


    public static function get3ds20CheckName($user, $first_name, $last_name)
    {
        if ($first_name == $user->first_name && $last_name == $user->last_name) {
            return '01';
        }
        return '02';
    }

    private static function get3ds20FirstUsagedestinationAddress($order_id, $city, $country, $street_1, $street_2, $postcode, $state)
    {

        $user_id = get_current_user_id();
        $customer_id = FC_Nexi_Helper::get_customer_id_by_user_id($user_id);

        $customer_orders = \FluentCart\App\Models\Order::where('customer_id', $customer_id)
            ->whereNotIn('id', [$order_id])
            ->orderBy('created_at', 'asc')
            ->get();

        if (count($customer_orders) == 0) {
            //Account Created in this transaction
            return "01";
        }

        $date = $customer_orders[0]->created_at;

        if ($date >= (new \DateTime('now - 30 day'))->format("Y-m-d")) {
            // Account created in last 30 days
            return '02';
        } else if ($date >= (new \DateTime('now - 60 day'))->format("Y-m-d")) {
            // Account created from 30 to 60 days ago
            return '03';
        } else {
            // Account created more then 60 days ago
            return '04';
        }

        return "";
    }

    public static function get3ds20OrderInLastSixMonth()
    {
        $orders = null;

        $user_id = get_current_user_id();
        $customer_id = FC_Nexi_Helper::get_customer_id_by_user_id($user_id);

        $sixMonthsAgo = Carbon::now()->subMonths(6);


        $orders = \FluentCart\App\Models\Order::where('customer_id', $customer_id)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->orderBy('created_at', 'desc')
            ->get();


        return count($orders);
    }

}