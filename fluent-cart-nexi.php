<?php
/*
Plugin Name: FluentCart Nexi
Description: A plugin i made for nexi payment gateway integration with Fluent Cart
Version: 0.1.0
Author: Anthony
Text Domain: fluent-cart-nexi
Requires PHP: 8.2
Requires Plugins: fluent-cart
*/

if (!defined('ABSPATH')) {
    die;
}

require_once 'constants.php';
require_once 'autoload.php';

use Inc\Base\Activate;
use Inc\Base\Deactivate;

function activate_fluentcart_nexi_plugin()
{
    Activate::activate();
}

function deactivate_fluentcart_nexi_plugin()
{
    Deactivate::deactivate();
}


register_activation_hook(__FILE__, 'activate_fluentcart_nexi_plugin');
register_deactivation_hook(__FILE__, 'deactivate_fluentcart_nexi_plugin');

if (class_exists('Inc\\Init')) {
    Inc\Init::register_services();
}