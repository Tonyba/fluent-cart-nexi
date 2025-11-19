<?php

namespace Inc\Base;

use Inc\PaymentMethods\Nexi\NexiPaymentGateway;


if (!defined('ABSPATH')) {
    die;
}

class Activate
{


    public static function activate()
    {
        self::set_nexi_unique();

        flush_rewrite_rules();
    }

    private static function set_nexi_unique()
    {
        $nexi_unique = get_option("nexi_unique");

        if ($nexi_unique == "") {
            update_option('nexi_unique', uniqid());
        }
    }
}
