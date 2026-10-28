<?php

namespace Webkul\TipaxShipping\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Webkul\TipaxShipping\Exceptions\TipaxApiException;

class TipaxApiClient
{
    private string $baseUrl;

    private string $authUrl = 'https://igtgatewayapi.tipax.ir/Token';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.tipax.base_url', 'https://omtestapi.tipax.ir'), '/');
    }

    public function getCities(): array
    {
        $path = __DIR__.'/../Resources/data/tipax-cities.json';
    
        if (! is_file($path) || ! is_readable($path)) {
            throw new TipaxApiException(
                'فایل محلی شهرهای تیپاکس پیدا نشد یا قابل خواندن نیست.'
            );
        }
    
        $contents = file_get_contents($path);
        $cities = is_string($contents)
            ? json_decode($contents, true)
            : null;
    
        if (! is_array($cities) || $cities === []) {
            throw new TipaxApiException(
                'فهرست محلی شهرهای تیپاکس معتبر نیست.'
            );
        }
    
        $cities = array_values(array_filter(
            $cities,
            fn ($city) => is_array($city)
                && isset($city['title'], $city['id'])
                && is_numeric($city['id'])
        ));
    
        if ($cities === []) {
            throw new TipaxApiException(
                'هیچ شهر معتبری در فایل محلی تیپاکس پیدا نشد.'
            );
        }
    
        return $cities;
    }

    /** Resolve a Bagisto address city name to Tipax's numeric city ID. */
    public function resolveCityId(string $city, ?string $state = null): int
    {
        $city = $this->normalizePersian($city);
        $state = $this->normalizePersian((string) $state);

        if ($city === '') {
            throw new TipaxApiException('شهر مقصد در آدرس مشتری مشخص نشده است.');
        }

        $matches = array_values(array_filter($this->getCities(), function (array $item) use ($city): bool {
            return $this->normalizePersian((string) ($item['title'] ?? '')) === $city;
        }));

        if (count($matches) > 1 && $state !== '') {
            $stateMatches = array_values(array_filter($matches, function (array $item) use ($state): bool {
                return $this->normalizePersian((string) ($item['stateTitle'] ?? '')) === $state;
            }));
            if (count($stateMatches) > 0) {
                $matches = $stateMatches;
            }
        }

        if (count($matches) !== 1) {
            throw new TipaxApiException(
                count($matches) === 0
                    ? 'شهر مقصد با فهرست شهرهای تیپاکس تطبیق داده نشد: '.$city
                    : 'نام شهر مقصد در تیپاکس مبهم است؛ تطبیق شهر و استان نیاز به نگاشت دستی دارد.'
            );
        }

        $id = filter_var($matches[0]['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new TipaxApiException('شناسه عددی شهر مقصد در پاسخ تیپاکس معتبر نیست.');
        }

        return $id;
    }

    public function servicesBetweenCities(int $sourceCityId, int $destinationCityId): array
    {
        $cacheKey = 'tipax_shipping.services.'.sha1($this->baseUrl.'|'.$sourceCityId.'|'.$destinationCityId);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($sourceCityId, $destinationCityId): array {
            $response = $this->request('POST', '/api/OM/v4/Pricing/GetServicesBetweenCities', [
                'sourceCityId'      => $sourceCityId,
                'destinationCityId' => $destinationCityId,
            ]);
            $json = $response->json();
            $list = data_get($json, 'data.serviceList');

            if (! is_array($list)) {
                throw new TipaxApiException('فهرست سرویس‌های تیپاکس برای مسیر انتخاب‌شده قابل تشخیص نیست.');
            }

            return $list;
        });
    }

    /**
     * Get a live quote only. This method never creates a shipment/order.
     */
    public function quote(array $packageInput): float
    {
        $originId = (int) ($packageInput['origin']['cityId'] ?? 0);
        $destinationId = (int) ($packageInput['destination']['cityId'] ?? 0);
        $serviceId = (int) ($packageInput['serviceId'] ?? 0);

        if ($originId < 1 || $destinationId < 1 || $serviceId < 1) {
            throw new TipaxApiException('شناسه مبدأ، مقصد یا سرویس تیپاکس تنظیم نشده است.');
        }

        $services = $this->servicesBetweenCities($originId, $destinationId);
        $serviceExists = false;
        foreach ($services as $service) {
            if ((int) ($service['serviceId'] ?? 0) === $serviceId) {
                $serviceExists = true;
                break;
            }
        }

        if (! $serviceExists) {
            throw new TipaxApiException('سرویس انتخاب‌شده برای مسیر مبدأ و مقصد در تیپاکس موجود نیست.');
        }

        $response = $this->request('POST', '/api/OM/v4/Pricing', [
            'packageInputs' => [$packageInput],
        ]);

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new TipaxApiException('قالب پاسخ قیمت تیپاکس JSON object معتبر نیست.');
        }

        if (($payload['isSuccess'] ?? null) === false) {
            throw new TipaxApiException((string) ($payload['message'] ?? 'تیپاکس محاسبه قیمت را ناموفق اعلام کرد.'));
        }

        $amount = $this->extractAmount($payload);
        if ($amount === null || $amount < 0) {
            throw new TipaxApiException(
                'درخواست قیمت پاسخ داده، اما فیلد مبلغ از قالب پاسخ قابل تشخیص نیست؛ ساختار پاسخ باید بررسی شود.'
            );
        }

        // Tipax returns shipping amount in Rials.
        // Hochehr stores and displays prices in Tomans.
        return round($amount / 10, 2);
    }

    private function request(string $method, string $path, ?array $body = null)
    {
        $attempt = 0;

        beginning:
        $token = $this->accessToken();
        $request = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.tipax.timeout', 15));

        $response = $method === 'GET'
            ? $request->get($this->baseUrl.$path)
            : $request->asJson()->post($this->baseUrl.$path, $body ?? []);

        if ($response->status() === 401 && $attempt === 0) {
            Cache::forget($this->tokenCacheKey());
            $attempt++;
            goto beginning;
        }

        if (! $response->successful()) {
            $message = data_get($response->json(), 'message');
            throw new TipaxApiException(
                'درخواست تیپاکس ناموفق بود (HTTP '.$response->status().')'.($message ? ': '.$message : '.')
            );
        }

        $json = $response->json();
        if (is_array($json) && array_key_exists('isSuccess', $json) && $json['isSuccess'] === false) {
            throw new TipaxApiException((string) ($json['message'] ?? 'تیپاکس درخواست را ناموفق اعلام کرد.'));
        }

        return $response;
    }

    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $username = (string) config('services.tipax.username', '');
        $password = (string) config('services.tipax.password', '');
        if ($username === '' || $password === '') {
            throw new TipaxApiException('نام کاربری و رمز عبور تیپاکس در تنظیمات سرور وارد نشده‌اند.');
        }

        $response = Http::asJson()->acceptJson()->timeout((int) config('services.tipax.timeout', 15))
            ->post($this->authUrl, [
                'username'   => $username,
                'password'   => $password,
                'grant_type' => 'password',
            ]);

        $payload = $response->json();
        $token = data_get($payload, 'data.access_token');

        if (! $response->successful() || data_get($payload, 'isSuccess') !== true || ! is_string($token) || $token === '') {
            throw new TipaxApiException('دریافت توکن تیپاکس ناموفق بود؛ اطلاعات ورود و دسترسی API را بررسی کنید.');
        }

        $expiresIn = (int) data_get($payload, 'data.expires_in', 3600);
        $cacheSeconds = max(60, min($expiresIn - 300, 90 * 60 * 60));
        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds($cacheSeconds));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'tipax_shipping.access_token.'.sha1($this->baseUrl.'|'.(string) config('services.tipax.username', ''));
    }

    private function collectCities($node, array &$cities): void
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['title'], $node['id']) && is_numeric($node['id'])) {
            $cities[] = [
                'title'     => (string) $node['title'],
                'stateTitle'=> (string) ($node['stateTitle'] ?? ''),
                'id'        => (int) $node['id'],
            ];
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectCities($child, $cities);
            }
        }
    }

    private function normalizePersian(string $value): string
    {
        $value = trim($value);
        $value = str_replace([
            'ي', 'ى', 'ك', "\u{200c}", "\u{200f}", "\u{200e}",
            "\u{00a0}",
        ], [
            'ی', 'ی', 'ک', '', '', '', ' ',
        ], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }

    /** Extract only known monetary scalar fields. Unknown schemas fail closed. */
    private function extractAmount(array $payload): ?float
    {
        $prioritizedKeys = [
            'totalAmount', 'totalPrice', 'finalAmount', 'finalPrice', 'payableAmount',
            'amountToPay', 'totalCost', 'shippingCost', 'sumTotalAmount', 'price', 'amount', 'cost',
        ];

        foreach ($prioritizedKeys as $key) {
            $value = $this->findScalarByKey($payload, $key);
            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function findScalarByKey(array $node, string $wantedKey): mixed
    {
        foreach ($node as $key => $value) {
            if (is_string($key) && strcasecmp($key, $wantedKey) === 0 && (is_numeric($value) || is_string($value))) {
                return is_numeric($value) ? $value : null;
            }

            if (is_array($value)) {
                $found = $this->findScalarByKey($value, $wantedKey);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
