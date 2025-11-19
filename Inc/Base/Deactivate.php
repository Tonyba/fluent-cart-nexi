<?php

namespace Inc\Base;

class Deactivate
{

    public static function deactivate()
    {
        self::remove_cronjobs();
        flush_rewrite_rules();
    }

    private static function remove_cronjobs()
    {
        $timestamp = wp_next_scheduled('wp_nexi_polling');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_nexi_polling');
        }

        $timestamp = wp_next_scheduled('wp_nexi_update_npg_payment_methods');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_nexi_update_npg_payment_methods');
        }
    }

}