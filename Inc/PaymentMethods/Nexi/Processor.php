<?php

namespace Inc\PaymentMethods\Nexi;

use FluentCart\Api\CurrencySettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\ProductVariation;
use FluentCart\App\Services\Payments\PaymentHelper;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Services\Payments\SubscriptionHelper;
use FluentCart\Framework\Support\Arr;

class Processor
{
    /**
     * Handle single payment processing
     */
    public function handleSinglePayment(PaymentInstance $paymentInstance)
    {

        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;

        try {

            /*
            if (!empty($_REQUEST['installments'])) {
                $order->updateMeta('installments', sanitize_text_field($_REQUEST['installments']));
            } else if (isset($_POST['nexi_xpay_number_of_installments'])) {
                $order->updateMeta('installments', sanitize_text_field($_REQUEST['nexi_xpay_number_of_installments']));
            } */

            $resultArray = [
                'result' => 'success',
                'redirect' => $transaction->getReceiptPageUrl(),
            ];

            return $resultArray;

        } catch (\Exception $e) {
            return new \WP_Error(
                'nexi_payment_error',
                $e->getMessage()
            );
        }






    }
}