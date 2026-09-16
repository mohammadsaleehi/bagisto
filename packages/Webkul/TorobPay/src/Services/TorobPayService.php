<?php

namespace Webkul\TorobPay\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TorobPayService
{
    /**
     * TorobPay CPG base URL.
     */
    protected string $apiUrl;

    /**
     * OAuth client ID.
     */
    protected string $clientId;

    /**
     * OAuth client secret.
     */
    protected string $clientSecret;

    /**
     * Merchant username.
     */
    protected string $username;

    /**
     * Merchant password.
     */
    protected string $password;

    /**
     * Cached access token for the current service instance.
     */
    protected ?string $accessToken = null;

    /**
     * Create service instance.
     */
    public function __construct()
    {
        $this->apiUrl = rtrim(
            (string) core()->getConfigData(
                'sales.payment_methods.torobpay.api_url'
            ),
            '/'
        );

        $this->clientId = trim(
            (string) core()->getConfigData(
                'sales.payment_methods.torobpay.client_id'
            )
        );

        $this->clientSecret = trim(
            (string) core()->getConfigData(
                'sales.payment_methods.torobpay.client_secret'
            )
        );

        $this->username = trim(
            (string) core()->getConfigData(
                'sales.payment_methods.torobpay.username'
            )
        );

        $this->password = (string) core()->getConfigData(
            'sales.payment_methods.torobpay.password'
        );
    }

    
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $this->validateCredentials();

        $response = Http::asJson()
            ->acceptJson()
            ->withBasicAuth(
                $this->clientId,
                $this->clientSecret
            )
            ->post(
                $this->apiUrl . '/api/online/v1/oauth/token',
                [
                    'username' => $this->username,
                    'password' => $this->password,
                ]
            );

        $data = $this->decodeResponse($response);

        /*
         * Never log the access token itself.
         */
        Log::info('TorobPay OAuth response.', [
            'status' => $response->status(),
            'has_access_token' => ! empty(
                $data['access_token'] ?? null
            ),
            'expires_in' => $data['expires_in'] ?? null,
        ]);

        if ($response->status() === 401) {
            throw new RuntimeException(
                'احراز هویت ترب‌پی ناموفق است.'
            );
        }

        if ($response->status() === 403) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'دسترسی ترب‌پی برای این حساب فعال نیست.'
                )
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'احراز هویت با ترب‌پی ناموفق بود.'
                )
            );
        }

        if (empty($data['access_token'])) {
            throw new RuntimeException(
                'Access Token معتبر از ترب‌پی دریافت نشد.'
            );
        }

        $this->accessToken = (string) $data['access_token'];

        return $this->accessToken;
    }

    /**
     * Check merchant eligibility for credit payment.
     *
     * GET /api/online/offer/v1/eligible?amount=...
     *
     * amount must be in Rial.
     */
    public function checkEligibility(int $amount): array
    {
        if ($amount <= 0) {
            throw new RuntimeException(
                'مبلغ تراکنش ترب‌پی باید بیشتر از صفر باشد.'
            );
        }

        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->get(
                $this->apiUrl . '/api/online/offer/v1/eligible',
                [
                    'amount' => $amount,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay eligibility response.', [
            'status' => $response->status(),
            'amount' => $amount,
            'successful' => $data['successful'] ?? null,
            'eligible' => $data['response']['eligible'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'بررسی شرایط پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        return $data;
    }

    /**
     * Create payment order and receive payment token.
     *
     * POST /api/online/payment/v1/token
     */
    public function createPayment(array $payload): array
    {
        $url = $this->apiUrl
            . '/api/online/payment/v1/token';

        $this->validateCreatePaymentPayload($payload);

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->post(
                $url,
                $payload
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay create payment response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'خطا در ایجاد سفارش ترب‌پی.'
                )
            );
        }

        if (
            empty($data['successful'])
            || empty($data['response']['paymentToken'])
            || empty($data['response']['paymentPageUrl'])
        ) {
            throw new RuntimeException(
                'توکن یا لینک پرداخت ترب‌پی دریافت نشد.'
            );
        }

        return $data;
    }

    /**
     * Verify payment.
     *
     * POST /api/online/payment/v1/verify
     */
    public function verifyPayment(
        string $paymentToken
    ): array {
        $paymentToken = trim($paymentToken);

        if ($paymentToken === '') {
            throw new RuntimeException(
                'Payment Token ترب‌پی خالی است.'
            );
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->post(
                $this->apiUrl
                    . '/api/online/payment/v1/verify',
                [
                    'paymentToken' => $paymentToken,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay verify response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'تأیید پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        if (isset($data['successful']) && ! $data['successful']) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'تراکنش ترب‌پی تأیید نشد.'
                )
            );
        }

        return $data;
    }

    /**
     * Settle payment.
     *
     * POST /api/online/payment/v1/settle
     */
    public function settlePayment(
        string $paymentToken
    ): array {
        $paymentToken = trim($paymentToken);

        if ($paymentToken === '') {
            throw new RuntimeException(
                'Payment Token ترب‌پی خالی است.'
            );
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->post(
                $this->apiUrl
                    . '/api/online/payment/v1/settle',
                [
                    'paymentToken' => $paymentToken,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay settle response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'نهایی‌سازی پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        if (isset($data['successful']) && ! $data['successful']) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'نهایی‌سازی پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        return $data;
    }

    /**
     * Revert payment.
     *
     * POST /api/online/payment/v1/revert
     */
    public function revertPayment(
        string $paymentToken
    ): array {
        $paymentToken = trim($paymentToken);

        if ($paymentToken === '') {
            throw new RuntimeException(
                'Payment Token ترب‌پی خالی است.'
            );
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->post(
                $this->apiUrl
                    . '/api/online/payment/v1/revert',
                [
                    'paymentToken' => $paymentToken,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay revert response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'بازگشت تراکنش ترب‌پی ناموفق بود.'
                )
            );
        }

        if (isset($data['successful']) && ! $data['successful']) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'بازگشت تراکنش ترب‌پی ناموفق بود.'
                )
            );
        }

        return $data;
    }

    /**
     * Get payment status.
     *
     * GET /api/online/payment/v1/status?paymentToken=...
     */
    public function getStatus(
        string $paymentToken
    ): array {
        $paymentToken = trim($paymentToken);

        if ($paymentToken === '') {
            throw new RuntimeException(
                'Payment Token ترب‌پی خالی است.'
            );
        }

        $response = Http::acceptJson()
            ->withToken($this->getAccessToken())
            ->get(
                $this->apiUrl
                    . '/api/online/payment/v1/status',
                [
                    'paymentToken' => $paymentToken,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay status response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'payment_status' =>
                $data['response']['status'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'دریافت وضعیت پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        if (isset($data['successful']) && ! $data['successful']) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'دریافت وضعیت پرداخت ترب‌پی ناموفق بود.'
                )
            );
        }

        return $data;
    }

    /**
     * Cancel payment.
     *
     * POST /api/online/payment/v1/cancel
     */
    public function cancelPayment(
        string $paymentToken
    ): array {
        $paymentToken = trim($paymentToken);

        if ($paymentToken === '') {
            throw new RuntimeException(
                'Payment Token ترب‌پی خالی است.'
            );
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->post(
                $this->apiUrl
                    . '/api/online/payment/v1/cancel',
                [
                    'paymentToken' => $paymentToken,
                ]
            );

        $data = $this->decodeResponse($response);

        Log::info('TorobPay cancel response.', [
            'status' => $response->status(),
            'successful' => $data['successful'] ?? null,
            'error_code' =>
                $data['error']['code']
                ?? $data['errorData']['errorCode']
                ?? null,
            'message' => $this->extractErrorMessage(
                $data,
                null
            ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'لغو سفارش ترب‌پی ناموفق بود.'
                )
            );
        }

        if (isset($data['successful']) && ! $data['successful']) {
            throw new RuntimeException(
                $this->extractErrorMessage(
                    $data,
                    'لغو سفارش ترب‌پی ناموفق بود.'
                )
            );
        }

        return $data;
    }

    /**
     * Validate OAuth credentials.
     */
    protected function validateCredentials(): void
    {
        if (
            $this->clientId === ''
            || $this->clientSecret === ''
            || $this->username === ''
            || $this->password === ''
        ) {
            throw new RuntimeException(
                'تنظیمات اتصال ترب‌پی کامل نیست.'
            );
        }

        if ($this->apiUrl === '') {
            throw new RuntimeException(
                'آدرس API ترب‌پی تنظیم نشده است.'
            );
        }
    }

    /**
     * Validate the required fields of create-payment request.
     *
     * Based on the official CPG documentation.
     */
    protected function validateCreatePaymentPayload(
        array $payload
    ): void {
        $required = [
            'amount',
            'paymentMethodTypeDto',
            'returnURL',
            'transactionId',
            'cartList',
            'address',
            'postalCode',
            'name_full_customer',
            'city',
            'province',
            'number_phone_registration',
        ];

        foreach ($required as $field) {
            if (
                ! array_key_exists($field, $payload)
                || (
                    ! is_array($payload[$field])
                    && trim((string) $payload[$field]) === ''
                )
            ) {
                throw new RuntimeException(
                    "فیلد {$field} در درخواست ترب‌پی ارسال نشده است."
                );
            }
        }

        if (
            ! is_numeric($payload['amount'])
            || (int) $payload['amount'] <= 0
        ) {
            throw new RuntimeException(
                'مبلغ پرداخت ترب‌پی نامعتبر است.'
            );
        }

        if (
            $payload['paymentMethodTypeDto']
            !== 'ONLINE_CREDIT'
        ) {
            throw new RuntimeException(
                'نوع روش پرداخت ترب‌پی باید ONLINE_CREDIT باشد.'
            );
        }

        if (
            ! is_array($payload['cartList'])
            || empty($payload['cartList'])
        ) {
            throw new RuntimeException(
                'cartList ترب‌پی خالی است.'
            );
        }
    }

    /**
     * Decode an HTTP response safely.
     */
    protected function decodeResponse(
        Response $response
    ): array {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'successful' => false,
            'http_status' => $response->status(),
            'raw_body' => $response->body(),
        ];
    }

    /**
     * Extract error message from both documented and
     * currently observed TorobPay response formats.
     */
    protected function extractErrorMessage(
        array $data,
        ?string $fallback = 'خطای نامشخص در ترب‌پی.'
    ): ?string {
        $message =
            $data['error']['user_message']
            ?? $data['error']['message']
            ?? $data['errorData']['user_message']
            ?? $data['errorData']['message']
            ?? $data['result']['message']
            ?? $data['message']
            ?? null;

        if (
            is_string($message)
            && trim($message) !== ''
        ) {
            return $message;
        }

        return $fallback;
    }
}