<?php

namespace Webkul\TorobPay\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\TorobPay\Contracts\TorobPay as TorobPayContract;

class TorobPay extends Model implements TorobPayContract
{
    protected $table = 'torobpay_transactions';

    protected $fillable = [
        'code',
        'message',
        'token',
        'reference_id',
        'transaction_id',
        'order_id',
        'amount',
        'status',
        'request_data',
        'response_data',
    ];

    protected $casts = [
        'amount' => 'integer',
        'request_data' => 'array',
        'response_data' => 'array',
    ];
}