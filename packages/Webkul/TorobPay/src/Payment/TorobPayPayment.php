<?php

namespace Webkul\TorobPay\Payment;

use Illuminate\Support\Facades\Storage;

class TorobPayPayment extends TorobPay
{
    /**
     * Payment method code.
     */
    protected $code = 'torobpay';

    /**
     * Get redirect URL for TorobPay.
     */
    public function getRedirectUrl()
    {
        return route('torobpay.payment.redirect');
    }

    /**
     * Get payment method image.
     */
    public function getImage()
    {
        $image = $this->getConfigData('image');

        if (! $image) {
            return parent::getImage();
        }

        return Storage::url($image);
    }

    /**
     * Get payment method details for checkout.
     */
    public function getAdditionalDetails()
    {
        return [
            'title' => $this->getConfigData('title'),

            'description' => $this->getConfigData('description'),
            
            'requires_card_details' => false,
        ];
    }
}