<?php
namespace Inc\PaymentMethods\Nexi;

use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\Framework\Support\Arr;

class NexiGatewaySettings extends BaseGatewaySettings
{
    public $methodHandler = 'fluent_cart_payment_settings_nexi_gateway';

    public $settings;

    public function __construct()
    {
        parent::__construct();
        $settings = $this->getCachedSettings();
        $defaults = static::getDefaults();

        if (!$settings || !is_array($settings)) {
            $settings = $defaults;
        } else {
            $settings = wp_parse_args($settings, $defaults);
            $xpay_instance = NexiPaymentGateway::getInstance();
            $xpay_instance->get_profile_info();
        }

        if (is_array($settings)) {
            $settings = Arr::mergeMissingValues($settings, $defaults);
        }

        $this->settings = $settings;
        $this->setXPaySettings();
    }

    public static function getDefaults()
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test', // test or live
            //nexi fields
            'nexi_alias' => '',
            'nexi_mac' => '',
            'integration_type' => 'redirect',
            'nexi_accounting' => 'C',
            'nexi_oneclick_enabled' => 'no',
            'nexi_xpay_3ds20_enabled' => 'no'
        ];
    }

    public function isActive()
    {
        return $this->settings['settings'] === 'yes';
    }

    public function is3ds20Enabled()
    {
        return $this->get('nexi_xpay_3ds20_enabled') == 'yes';
    }

    public function isOneClickEnabled()
    {
        return $this->get('nexi_oneclick_enabled') == 'yes';
    }

    public function get($key = '')
    {
        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $this->settings;
    }


    public function getMode()
    {
        return $this->get('payment_mode');
    }

    public function getApiKey()
    {
        $mode = $this->get('payment_mode');
        return $this->get($mode . '_api_key');
    }

    public function getSecretKey()
    {
        $mode = $this->get('payment_mode');
        return Helper::decryptKey($this->get($mode . '_secret_key'));
    }

    public function isTestMode()
    {
        return $this->get('payment_mode') === 'test';
    }

    public function getNexiAlias()
    {
        return $this->get('nexi_alias');
    }

    public function getNexiMac()
    {
        return $this->get('nexi_mac');
    }

    public function getBaseURL()
    {
        $base_url = '';

        if (!$this->isTestMode()) {
            $base_url = 'https://ecommerce.nexi.it/';
        } else {
            $base_url = 'https://int-ecommerce.nexi.it/';
        }

        return $base_url;

    }

    private function setXPaySettings()
    {
        $currentConfig = [];
        $currentConfig["nexi_xpay_alias"] = $this->settings["nexi_alias"];
        $currentConfig["nexi_xpay_mac"] = $this->settings["nexi_mac"];
        $currentConfig["nexi_xpay_test_mode"] = $this->settings["payment_mode"];
        $currentConfig["nexi_xpay_accounting"] = $this->settings["nexi_accounting"];
        $currentConfig["nexi_xpay_oneclick_enabled"] = $this->settings["nexi_oneclick_enabled"];
        $currentConfig["nexi_xpay_3ds20_enabled"] = $this->settings["nexi_xpay_3ds20_enabled"];
        $currentConfig["nexi_gateway"] = GATEWAY_XPAY;
        $currentConfig["integration_type"] = $this->settings["integration_type"];

        // SUBSCRIPTIONS KEYS

        /*  $currentConfig["nexi_xpay_recurring_enabled"] = $currentConfig["abilita_modulo_ricorrenze"];
          $currentConfig["nexi_xpay_recurring_alias"] = $currentConfig["cartasi_alias_rico"];
          $currentConfig["nexi_xpay_recurring_mac"] = $currentConfig["cartasi_mac_rico"];
          $currentConfig["nexi_xpay_group"] = $currentConfig["gruppo_rico"];*/



        update_option(FC_SETTINGS_KEY, $currentConfig);
    }

    public function getCachedSettings()
    {
        return Arr::get(self::$allSettings, $this->methodHandler);
    }

}