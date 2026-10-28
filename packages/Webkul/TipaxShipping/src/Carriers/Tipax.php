<?php

namespace Webkul\TipaxShipping\Carriers;

use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Shipping\Carriers\AbstractShipping;
use Webkul\TipaxShipping\Exceptions\TipaxApiException;
use Webkul\TipaxShipping\Services\TipaxApiClient;
use Throwable;

class Tipax extends AbstractShipping
{
    protected $code = 'tipax';

    protected $method = 'tipax_tipax_express';

    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();
        if (! $cart || ! $cart->items || $cart->items->isEmpty()) {
            return false;
        }

        // The cart estimator may call calculate() before a complete shipping address exists.
        $address = $cart->shipping_address;
        if (! $address || trim((string) ($address->city ?? '')) === '') {
            return false;
        }

        try {
            $client = app(TipaxApiClient::class);
            $destinationCityId = $client->resolveCityId(
                (string) $address->city,
                (string) ($address->state ?? '')
            );

            $package = $this->buildPackageInput($cart, $destinationCityId);

            // Safe diagnostics: request dimensions/IDs only; never log tokens or addresses.

            $baseAmount = $client->quote($package);

            $rate = new CartShippingRate;
            $rate->carrier = $this->getCode();
            $rate->carrier_title = $this->getConfigData('title') ?: 'ارسال تیپاکس';
            $rate->method = $this->getMethod();
            $rate->method_title = $this->getConfigData('title') ?: 'ارسال تیپاکس (اکسپرس)';
            $rate->method_description = $this->getConfigData('description') ?: 'محاسبه آنلاین هزینه ارسال با تیپاکس';
            $rate->price = core()->convertPrice($baseAmount);
            $rate->base_price = $baseAmount;

            return $rate;
        } catch (Throwable $exception) {
            $context = [
                'exception' => get_class($exception),
            ];

            if ($exception instanceof TipaxApiException) {
                $context['message'] = $exception->getMessage();
            } elseif ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                // Log the underlying transport error, but not request data or credentials.
                $context['message'] = mb_substr(
                    $exception->getMessage(),
                    0,
                    500
                );
            } else {
                $context['message'] = 'Unexpected error while calculating Tipax shipping rate.';
            }

            Log::warning('Tipax shipping quote unavailable.', $context);

            return false;
        }
    }

    private function buildPackageInput($cart, int $destinationCityId): array
    {
        $weightKg = 0.0;
        $quantity = 0;
        $maxLength = 0.0;
        $maxWidth = 0.0;
        $stackedHeight = 0.0;

        foreach ($cart->items as $item) {
            $product = $item->product ?? null;

            if (! $product) {
                throw new TipaxApiException('اطلاعات یکی از محصولات سبد خرید برای محاسبه ارسال در دسترس نیست.');
            }

            if (method_exists($product, 'getTypeInstance') && ! $product->getTypeInstance()->isStockable()) {
                continue;
            }

            $qty = max(0, (int) ($item->quantity ?? 0));
            if ($qty === 0) {
                continue;
            }

            // Weight is read from the product record / Bagisto product attribute values.
            $rawWeight = (float) $product->weight;
            if ($rawWeight <= 0) {
                $sku = (string) ($product->sku ?? 'unknown');
                throw new TipaxApiException("وزن محصول {$sku} در اطلاعات محصول ثبت نشده است.");
            }

            $unit = strtolower((string) config('services.tipax.product_weight_unit', 'kg'));
            if (in_array($unit, ['g', 'gram', 'grams', 'گرم'], true)) {
                $rawWeight /= 1000;
            }

            $length = $this->readProductDimension($product, 'length');
            $width  = $this->readProductDimension($product, 'width');
            $height = $this->readProductDimension($product, 'height');

            if ($length === null || $width === null || $height === null) {
                $sku = (string) ($product->sku ?? 'unknown');
                $missing = [];

                if ($length === null) {
                    $missing[] = 'طول';
                }
                if ($width === null) {
                    $missing[] = 'عرض';
                }
                if ($height === null) {
                    $missing[] = 'ارتفاع';
                }

                throw new TipaxApiException(
                    'اطلاعات '.implode('، ', $missing)." محصول {$sku} در مشخصات محصول ثبت نشده است."
                );
            }

            $weightKg += $rawWeight * $qty;
            $quantity += $qty;

            // For a single item these are the exact dimensions entered by the admin.
            // For multiple items, use a conservative stack: largest length/width and
            // sum of heights for every unit. This avoids the old weight-based fake box sizes.
            $maxLength = max($maxLength, $length);
            $maxWidth  = max($maxWidth, $width);
            $stackedHeight += $height * $qty;
        }

        if ($quantity === 0 || $weightKg <= 0) {
            throw new TipaxApiException('وزن سفارش برای محاسبه هزینه تیپاکس قابل تعیین نیست.');
        }

        if ($maxLength <= 0 || $maxWidth <= 0 || $stackedHeight <= 0) {
            throw new TipaxApiException('ابعاد بسته از اطلاعات محصولات قابل محاسبه نیست.');
        }

        $cartValue = (float) ($cart->base_sub_total ?? $cart->sub_total ?? 0);
        $packageValue = max(1, (int) round($cartValue * 10));

        return [
            'origin' => [
                // Fixed source city: Saveh (Tipax numeric city ID from /Cities/plusstate).
                'cityId' => (int) config('services.tipax.origin_city_id', 1911),
            ],
            'destination' => [
                // Destination is resolved from the customer's checkout address.
                'cityId' => $destinationCityId,
            ],
            'weight'           => round($weightKg, 3),
            'packageValue'     => $packageValue,
            'length'            => round($maxLength, 2),
            'width'             => round($maxWidth, 2),
            'height'            => round($stackedHeight, 2),
            'packingId'         => (int) config('services.tipax.packing_id', 7),
            'packageContentId'  => (int) config('services.tipax.package_content_id', 520),
            'packType'          => (int) config('services.tipax.pack_type', 20),
            'paymentType'       => (int) config('services.tipax.payment_type', 20),
            'pickupType'        => (int) config('services.tipax.pickup_type', 0),
            'distributionType'  => (int) config('services.tipax.distribution_type', 10),
            'serviceId'         => (int) config('services.tipax.service_id', 1),
        ];
    }

    /**
     * Read a numeric product attribute, supporting Bagisto's EAV attributes as well as
     * custom physical columns. Dimensions are expected in centimeters.
     */
    private function readProductDimension($product, string $attribute): ?float
    {
        $value = null;

        if (method_exists($product, 'getAttribute')) {
            $value = $product->getAttribute($attribute);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value > 0 ? $value : null;
    }

}