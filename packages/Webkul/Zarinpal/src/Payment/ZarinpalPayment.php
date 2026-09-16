<?php

namespace Webkul\Zarinpal\Payment;

use Illuminate\Support\Facades\Storage;


class ZarinpalPayment extends Zarinpal
{

    protected $code = 'zarinpal';

    public function getRedirectUrl()
    {
        return route('zarinpal.payment.redirect');
    }

    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : parent::getImage();
    }
}