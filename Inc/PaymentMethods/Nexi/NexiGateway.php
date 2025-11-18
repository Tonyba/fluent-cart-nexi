<?php

namespace Inc\PaymentMethods\Nexi;

class NexiGateway
{


    public function register()
    {
        add_action('fluent_cart/register_payment_methods', function () {
            if (!function_exists('fluent_cart_api')) {
                return; // FluentCart not active
            }

            // Register your custom gateway
            fluent_cart_api()->registerCustomPaymentMethod(
                'nexi_gateway',
                new NexiPaymentGateway()
            );
        });
    }

}