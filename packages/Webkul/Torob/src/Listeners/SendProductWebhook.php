<?php

namespace Webkul\Torob\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Webkul\Torob\Services\TorobWebhookService;

class SendProductWebhook implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public string $connection = 'database';

    public string $queue = 'torob';

    public int $tries = 50;

    public array $backoff = [
        60,
        120,
        300,
        600,
    ];

    public function __construct(
        protected TorobWebhookService $torobWebhookService
    ) {}

    public function handle($product): void
    {
        if (! $product || ! $product->id) {
            return;
        }

        $this->torobWebhookService->sendProduct(
            (int) $product->id
        );
    }

    public function middleware(): array
    {
        return [
            new RateLimited('torob-webhook'),
        ];
    }
}