<?php

if (!defined('ABSPATH')) {
    die;
}

define('PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN', plugin_basename(__FILE__));
define('PLUGIN_VER', '0.0.1');

define('FC_SETTINGS_KEY', 'fluent_cart_xpay_settings');
define("GATEWAY_XPAY", "xpay");
define("GATEWAY_NPG", "npg");

define('NPG_OR_AUTHORIZED', 'AUTHORIZED');
define('NPG_OR_EXECUTED', 'EXECUTED');
define('NPG_OR_DECLINED', 'DECLINED');
define('NPG_OR_DENIED_BY_RISK', 'DENIED_BY_RISK');
define('NPG_OR_THREEDS_VALIDATED', 'THREEDS_VALIDATED');
define('NPG_OR_THREEDS_FAILED', 'THREEDS_FAILED');
define('NPG_OR_3DS_FAILED', '3DS_FAILED');
define('NPG_OR_PENDING', 'PENDING');
define('NPG_OR_CANCELED', 'CANCELED');
define('NPG_OR_CANCELLED', 'CANCELLED');
define('NPG_OR_VOIDED', 'VOIDED');
define('NPG_OR_REFUNDED', 'REFUNDED');
define('NPG_OR_FAILED', 'FAILED');
define('NPG_OR_EXPIRED', 'EXPIRED');

define('NPG_PAYMENT_SUCCESSFUL', [
    NPG_OR_AUTHORIZED,
    NPG_OR_EXECUTED,
]);

define('NPG_PAYMENT_FAILURE', [
    NPG_OR_DECLINED,
    NPG_OR_DENIED_BY_RISK,
    NPG_OR_FAILED,
    NPG_OR_THREEDS_FAILED,
    NPG_OR_3DS_FAILED,
]);

define('NPG_CONTRACT_CIT', 'CIT');

define('NPG_OT_AUTHORIZATION', 'AUTHORIZATION');
define('NPG_OT_CAPTURE', 'CAPTURE');
define('NPG_OT_VOID', 'VOID');
define('NPG_OT_REFUND', 'REFUND');
define('NPG_OT_CANCEL', 'CANCEL');

define('NPG_NO_RECURRING', 'NO_RECURRING');
define('NPG_SUBSEQUENT_PAYMENT', 'SUBSEQUENT_PAYMENT');
define('NPG_CONTRACT_CREATION', 'CONTRACT_CREATION');
define('NPG_CARD_SUBSTITUTION', 'CARD_SUBSTITUTION');

define('NPG_RT_MIT_UNSCHEDULED', 'MIT_UNSCHEDULED');


$plugins = get_plugins();
define("FLUENTCART_GATEWAY_NEXI_VERSION", $plugins["fluent-cart/fluent-cart.php"]["Version"]);