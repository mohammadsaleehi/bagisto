<?php

namespace Webkul\Torob\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webkul\Product\Repositories\ProductRepository;

class TorobProductService
{
    public function __construct(
        protected ProductRepository $productRepository,
        protected TorobProductMapper $productMapper
    ) {}

    public function handle(Request $request): array
    {
        $data = $request->json()->all();

        if (isset($data['page_urls'])) {
            return $this->getByUrls($data['page_urls']);
        }

        if (isset($data['page_uniques'])) {
            return $this->getByUniques($data['page_uniques']);
        }

        if (
            isset($data['sort'])
            && $data['sort'] === 'product_id_desc'
        ) {
            return $this->getByCursor($data);
        }

        if (isset($data['page'])) {
            return $this->getByPage($data);
        }

        throw new BadRequestHttpException(
            'Invalid request parameters.'
        );
    }

    protected function productRelations(): array
    {
        return [
            'product_flats',
            'categories',
            'images',
            'attribute_values',

            'attribute_family.attribute_groups.custom_attributes',
            'attribute_family.attribute_groups.custom_attributes.options',
            'attribute_family.attribute_groups.custom_attributes.options.translations',

            'variants',
            'variants.product_flats',
            'variants.attribute_values',
            'variants.inventory_indices',
            'variants.attribute_family.attribute_groups.custom_attributes',
            'variants.attribute_family.attribute_groups.custom_attributes.options',
            'variants.attribute_family.attribute_groups.custom_attributes.options.translations',
        ];
    }

    protected function getByPage(array $data): array
    {
        if (! isset($data['sort'])) {
            throw new BadRequestHttpException(
                'sort parameter is not provided'
            );
        }

        if (! in_array($data['sort'], [
            'date_added_desc',
            'date_updated_desc',
        ], true)) {
            throw new BadRequestHttpException(
                'Invalid sort parameter.'
            );
        }

        $orderColumn = match ($data['sort']) {
            'date_added_desc' => 'created_at',
            'date_updated_desc' => 'updated_at',
        };

        $products = $this->productRepository
            ->with($this->productRelations())
            ->orderBy($orderColumn, 'desc')
            ->paginate(100);

        return [
            'api_version' => 'torob_api_v3',
            'current_page' => $products->currentPage(),
            'total' => $products->total(),
            'max_pages' => $products->lastPage(),
            'next_cursor' => null,
            'products' => $products->map(
                fn ($product) => $this->productMapper->map($product)
            )->values()->all(),
        ];
    }

    protected function getByCursor(array $data): array
    {
        if (! isset($data['sort'])) {
            throw new BadRequestHttpException(
                'sort parameter is not provided'
            );
        }

        if ($data['sort'] !== 'product_id_desc') {
            throw new BadRequestHttpException(
                'Invalid sort parameter for cursor pagination.'
            );
        }

        $query = $this->productRepository
            ->with($this->productRelations())
            ->orderBy('id', 'desc');

        if (isset($data['cursor'])) {
            $cursor = (string) $data['cursor'];

            if ($cursor === '' || ! ctype_digit($cursor)) {
                throw new BadRequestHttpException(
                    'Invalid cursor parameter.'
                );
            }

            $query->where('id', '<', (int) $cursor);
        }

        $products = $query
            ->take(101)
            ->get();

        $hasMore = $products->count() > 100;

        $products = $products->take(100);

        return [
            'api_version' => 'torob_api_v3',
            'current_page' => 1,
            'total' => null,
            'max_pages' => null,
            'next_cursor' => (
                $hasMore && $products->isNotEmpty()
            )
                ? (string) $products->last()->id
                : null,
            'products' => $products->map(
                fn ($product) => $this->productMapper->map($product)
            )->values()->all(),
        ];
    }

    protected function getByUrls(array $urls): array
    {
        if (empty($urls)) {
            throw new BadRequestHttpException(
                'page_urls must contain at least one URL.'
            );
        }

        $channel = core()->getRequestedChannelCode();
        $locale = core()->getRequestedLocaleCodeInRequestedChannel();

        $urlKeys = collect($urls)
            ->map(function ($url) {
                $path = parse_url((string) $url, PHP_URL_PATH);

                return trim($path ?: '', '/');
            })
            ->filter()
            ->unique()
            ->values();

        if ($urlKeys->isEmpty()) {
            return $this->emptyResult();
        }

        $productIds = DB::table('product_flat')
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->whereIn('url_key', $urlKeys)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return $this->emptyResult();
        }

        $products = $this->findProductsByIds($productIds);

        return [
            'api_version' => 'torob_api_v3',
            'current_page' => 1,
            'total' => $products->count(),
            'max_pages' => 1,
            'next_cursor' => null,
            'products' => $products->map(
                fn ($product) => $this->productMapper->map($product)
            )->values()->all(),
        ];
    }

    protected function getByUniques(array $uniques): array
    {
        if (empty($uniques)) {
            throw new BadRequestHttpException(
                'page_uniques must contain at least one identifier.'
            );
        }

        $productIds = collect($uniques)
            ->map(function ($unique) {
                return explode('_', (string) $unique)[0];
            })
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return $this->emptyResult();
        }

        $products = $this->findProductsByIds($productIds);

        return [
            'api_version' => 'torob_api_v3',
            'current_page' => 1,
            'total' => $products->count(),
            'max_pages' => 1,
            'next_cursor' => null,
            'products' => $products->map(
                fn ($product) => $this->productMapper->map($product)
            )->values()->all(),
        ];
    }

    protected function findProductsByIds(array $productIds)
    {
        $products = $this->productRepository
            ->with($this->productRelations())
            ->findWhereIn('id', $productIds);

        $positions = array_flip($productIds);

        return $products
            ->sortBy(
                fn ($product) => $positions[(int) $product->id] ?? PHP_INT_MAX
            )
            ->values();
    }

    protected function emptyResult(): array
    {
        return [
            'api_version' => 'torob_api_v3',
            'current_page' => 1,
            'total' => 0,
            'max_pages' => 1,
            'next_cursor' => null,
            'products' => [],
        ];
    }
}