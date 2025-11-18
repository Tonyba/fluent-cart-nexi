<?php

namespace Inc\Base;

if (!defined('ABSPATH')) {
    die;
}

class Activate
{


    public static function activate()
    {
        flush_rewrite_rules();
        self::set_nexi_unique();
    }

    private static function set_nexi_unique()
    {
        $nexi_unique = get_option("nexi_unique");

        if ($nexi_unique == "") {
            update_option('nexi_unique', uniqid());
        }
    }
}
