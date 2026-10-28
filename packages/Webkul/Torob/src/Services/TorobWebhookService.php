<?php

namespace Webkul\Torob\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Webkul\Product\Repositories\ProductRepository;

class TorobWebhookService
{
    protected string $endpoint =
        'https://api.torob.com/update/webhook/v1/';

    public function __construct(
        protected ProductRepository $productRepository
    ) {}

    /**
     * Send one product update to Torob.
     */
    public function sendProduct(int $productId): void
    {
        if (! config('torob.enabled', true)) {
            return;
        }

        $product = $this->productRepository
            ->with([
                'product_flats',
            ])
            ->find($productId);

        if (! $product) {
            Log::warning('Torob webhook skipped: product not found.', [
                'product_id' => $productId,
            ]);

            return;
        }

        $flat = $this->getCurrentProductFlat($product);

        if (! $flat) {
            Log::warning(
                'Torob webhook skipped: product flat not found.',
                [
                    'product_id' => $productId,
                ]
            );

            return;
        }

        $urlKey = trim((string) ($flat->url_key ?? ''));

        if ($urlKey === '') {
            Log::warning(
                'Torob webhook skipped: product URL key is empty.',
                [
                    'product_id' => $productId,
                ]
            );

            return;
        }

        $pageUrl = url('/'.ltrim($urlKey, '/'));

        $this->send([
            [
                'page_url' => $pageUrl,
                'page_unique' => (string) $product->id,
            ],
        ]);
    }

    /**
     * Send webhook items to Torob.
     *
     * @param array<int, array{
     *     page_url: string,
     *     page_unique: string
     * }> $items
     */
    public function send(array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        if (! config('torob.enabled', true)) {
            return false;
        }

        $token = trim(
            (string) config('torob.webhook_token')
        );

        if ($token === '') {
            throw new RuntimeException(
                'TOROB_WEBHOOK_TOKEN is not configured.'
            );
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(10)
            ->connectTimeout(5)
            ->post($this->endpoint, [
                'items' => array_values($items),
            ]);

        if ($response->successful()) {
            Log::info('Torob webhook sent successfully.', [
                'items_count' => count($items),
            ]);

            return true;
        }

        $status = $response->status();

        /*
         * Temporary failures:
         * let the queue retry the listener.
         */
        if ($status === 429 || $status >= 500) {
            throw new RuntimeException(
                'Torob webhook temporary failure: HTTP '.$status
            );
        }

        /*
         * Permanent/request/configuration errors:
         * do not retry indefinitely.
         */
        Log::warning('Torob webhook request rejected.', [
            'status' => $status,
            'response' => $response->json(),
            'items_count' => count($items),
        ]);
        
        return false;
    }

    protected function getCurrentProductFlat($product)
    {
        if (! $product->relationLoaded('product_flats')) {
            return null;
        }

        $channel = core()->getRequestedChannelCode();

        $locale = core()
            ->getRequestedLocaleCodeInRequestedChannel();

        return $product->product_flats->first(
            fn ($flat) =>
                $flat->channel === $channel
                && $flat->locale === $locale
        ) ?? $product->product_flats->first();
    }
}