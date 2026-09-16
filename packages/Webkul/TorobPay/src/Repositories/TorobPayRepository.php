<?php

namespace Webkul\TorobPay\Repositories;

use Webkul\Core\Eloquent\Repository;

class TorobPayRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\TorobPay\Contracts\TorobPay';
    }

    /**
     * Get transaction by local Bagisto order ID.
     */
    public function getByOrderId(int $orderId)
    {
        return $this->where('order_id', $orderId)->first();
    }

    /**
     * Get transaction by TorobPay payment token.
     */
    public function getByToken(string $token)
    {
        return $this->where('token', $token)->first();
    }

    /**
     * Get transaction by merchant transaction ID.
     */
    public function getByTransactionId(string $transactionId)
    {
        return $this->where(
            'transaction_id',
            $transactionId
        )->first();
    }

    /**
     * Get transaction by TorobPay reference ID.
     */
    public function getByReferenceId(string $referenceId)
    {
        return $this->where(
            'reference_id',
            $referenceId
        )->first();
    }
}