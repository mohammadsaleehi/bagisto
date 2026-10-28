<?php

namespace Webkul\Torob\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TorobProductMapper
{
    /**
     * Map a Bagisto product to Torob Product API v3 format.
     *
     * Bagisto stores prices in Toman.
     * Torob expects prices in Rial.
     *
     * Therefore, prices are multiplied by 10 only in the API output.
     */
    public function map($product): array
    {
        $flat = $this->getCurrentProductFlat($product);

        $basePrice = $this->toRial(
            data_get($flat, 'price', 0)
        );

        $specialPrice = $this->toRial(
            data_get($flat, 'special_price')
        );

        $currentPrice = $basePrice;
        $oldPrice = null;

        if ($this->isSpecialPriceActive($flat, $specialPrice)) {
            $currentPrice = $specialPrice;
            $oldPrice = $basePrice;
        }

        return [
            'page_unique' => (string) $product->id,

            'page_url' => $this->getProductUrl($flat),

            'product_group_id' => $product->parent_id
                ? (string) $product->parent_id
                : null,

            'title' => $this->limitString(
                (string) data_get($flat, 'name', ''),
                500
            ),

            'subtitle' => null,

            'current_price' => $currentPrice,

            'old_price' => $oldPrice,

            'availability' => $this->isAvailable($flat),

            'category_name' => $this->getCategoryName($product),

            'image_links' => $this->getImageLinks($product),

            'spec' => (object) $this->getSpecifications($product),

            'guarantee' => $this->getGuarantee($product),

            'short_desc' => $this->getShortDescription($flat),

            'date_added' => $product->created_at?->toIso8601String(),

            'date_updated' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get product flat for the current channel and locale.
     *
     * This method never lazy-loads product_flats.
     */
    protected function getCurrentProductFlat($product)
    {
        if (! $product->relationLoaded('product_flats')) {
            return null;
        }

        $channel = core()->getRequestedChannelCode();
        $locale = core()->getRequestedLocaleCodeInRequestedChannel();

        return $product->product_flats->first(
            fn ($flat) => $flat->channel === $channel
                && $flat->locale === $locale
        ) ?? $product->product_flats->first();
    }

    /**
     * Build the absolute storefront product URL.
     */
    protected function getProductUrl($flat): string
    {
        $urlKey = data_get($flat, 'url_key');

        return $urlKey
            ? url('/'.ltrim($urlKey, '/'))
            : url('/');
    }

    /**
     * Determine whether the product is currently available for sale.
     */
    protected function isAvailable($flat): bool
    {
        if (! $flat) {
            return false;
        }

        if (! (bool) data_get($flat, 'status', false)) {
            return false;
        }

        if (! (bool) data_get($flat, 'manage_stock', false)) {
            return true;
        }

        $quantity = (float) data_get($flat, 'quantity', 0);

        if ($quantity > 0) {
            return true;
        }

        return (bool) core()->getConfigData(
            'catalog.inventory.stock_options.back_orders'
        );
    }

    /**
     * Get the first loaded product category name.
     *
     * Never lazy-load categories.
     */
    protected function getCategoryName($product): ?string
    {
        if (! $product->relationLoaded('categories')) {
            return null;
        }

        $category = $product->categories->first();

        if (! $category) {
            return null;
        }

        $name = trim((string) ($category->name ?? ''));

        return $name !== ''
            ? $this->limitString($name, 200)
            : null;
    }

    /**
     * Get product images.
     *
     * Never lazy-load images.
     */
    protected function getImageLinks($product): array
    {
        if (! $product->relationLoaded('images')) {
            return [];
        }

        return $product->images
            ->map(function ($image) {
                $imageUrl = trim((string) ($image->url ?? ''));

                if ($imageUrl === '') {
                    return null;
                }

                if (! preg_match('#^https?://#i', $imageUrl)) {
                    $imageUrl = url('/'.ltrim($imageUrl, '/'));
                }

                return $imageUrl;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get all custom attributes that were already eager-loaded.
     *
     * Avoid accessing the AttributeFamily custom_attributes accessor,
     * which may execute additional queries.
     */
    protected function getLoadedFamilyAttributes($product): Collection
    {
        if (! $product->relationLoaded('attribute_family')) {
            return collect();
        }

        $family = $product->attribute_family;

        if (! $family) {
            return collect();
        }

        if (! $family->relationLoaded('attribute_groups')) {
            return collect();
        }

        return $family->attribute_groups
            ->filter(
                fn ($group) => $group->relationLoaded('custom_attributes')
            )
            ->flatMap(
                fn ($group) => $group->custom_attributes
            )
            ->unique('id')
            ->values();
    }

    /**
     * Get product-facing specifications.
     *
     * Product attribute values must already be eager-loaded.
     */
    protected function getSpecifications($product): array
    {
        $excludedAttributes = [
            'sku',
            'name',
            'url_key',
            'price',
            'special_price',
            'special_price_from',
            'special_price_to',
            'cost',
            'description',
            'short_description',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'status',
            'visible_individually',
            'featured',
            'new',
            'guest_checkout',
            'manage_stock',
            'allow_rma',
            'product_number',
            'weight',
            'tax_category_id',
            'attribute_family_id',
        ];

        $attributes = $this->getLoadedFamilyAttributes($product);

        if ($attributes->isEmpty()) {
            return [];
        }

        if (! $product->relationLoaded('attribute_values')) {
            return [];
        }

        $attributeValues = $product->attribute_values
            ->keyBy('attribute_id');

        $locale = core()->getRequestedLocaleCodeInRequestedChannel();

        $specifications = [];

        foreach ($attributes as $attribute) {
            $code = (string) $attribute->code;

            if (
                $code === ''
                || in_array($code, $excludedAttributes, true)
            ) {
                continue;
            }

            $attributeValue = $attributeValues->get($attribute->id);

            if (! $attributeValue) {
                continue;
            }

            $value = $this->extractAttributeValue($attributeValue);

            if ($value === null || $value === '') {
                continue;
            }

            $value = $this->resolveAttributeValue(
                $attribute,
                $value,
                $locale
            );

            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $specifications[$code] = $value;
            }
        }

        return $specifications;
    }

    /**
     * Extract the actual value from a Bagisto ProductAttributeValue model.
     *
     * No relationship is accessed here.
     */
    protected function extractAttributeValue($attributeValue): mixed
    {
        foreach ([
            'text_value',
            'integer_value',
            'boolean_value',
            'float_value',
            'datetime_value',
            'date_value',
        ] as $column) {
            $value = $attributeValue->{$column} ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve select/multiselect/checkbox values into translated labels.
     *
     * All required option relations must already be eager-loaded.
     */
    protected function resolveAttributeValue(
        $attribute,
        mixed $value,
        string $locale
    ): mixed {
        if (! in_array($attribute->type, [
            'select',
            'multiselect',
            'checkbox',
        ], true)) {
            return $value;
        }

        if (! $attribute->relationLoaded('options')) {
            return null;
        }

        $optionIds = is_array($value)
            ? $value
            : preg_split(
                '/\s*,\s*/',
                (string) $value,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        if (empty($optionIds)) {
            return null;
        }

        $labels = [];

        foreach ($optionIds as $optionId) {
            $option = $attribute->options->firstWhere(
                'id',
                (int) $optionId
            );

            if (! $option) {
                continue;
            }

            if (! $option->relationLoaded('translations')) {
                continue;
            }

            $translation = $option->translations
                ->firstWhere('locale', $locale);

            $label = $translation?->label
                ?? $option->admin_name
                ?? null;

            if ($label !== null && $label !== '') {
                $labels[] = (string) $label;
            }
        }

        return $labels
            ? implode(', ', $labels)
            : null;
    }

    /**
     * Get guarantee from the already-loaded product attributes.
     */
    protected function getGuarantee($product): ?string
    {
        $attributes = $this->getLoadedFamilyAttributes($product);

        if ($attributes->isEmpty()) {
            return null;
        }

        if (! $product->relationLoaded('attribute_values')) {
            return null;
        }

        $attribute = $attributes->firstWhere('code', 'guarantee');

        if (! $attribute) {
            return null;
        }

        $attributeValue = $product->attribute_values
            ->firstWhere('attribute_id', $attribute->id);

        if (! $attributeValue) {
            return null;
        }

        $value = $this->extractAttributeValue($attributeValue);

        if ($value === null || $value === '') {
            return null;
        }

        $value = $this->resolveAttributeValue(
            $attribute,
            $value,
            core()->getRequestedLocaleCodeInRequestedChannel()
        );

        return $value !== null
            ? $this->limitString((string) $value, 200)
            : null;
    }

    /**
     * Get the short product description.
     */
    protected function getShortDescription($flat): ?string
    {
        $description = data_get(
            $flat,
            'short_description'
        );

        if (! $description) {
            return null;
        }

        $description = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                strip_tags((string) $description)
            )
        );

        return $description !== ''
            ? $this->limitString($description, 500)
            : null;
    }

    /**
     * Check whether the special price is currently active.
     */
    protected function isSpecialPriceActive(
        $flat,
        int $specialPrice
    ): bool {
        if ($specialPrice <= 0) {
            return false;
        }

        $from = data_get($flat, 'special_price_from');
        $to = data_get($flat, 'special_price_to');

        $now = now();

        if ($from && $now->lt($from)) {
            return false;
        }

        if ($to && $now->gt($to)) {
            return false;
        }

        return true;
    }

    /**
     * Convert a Toman price to Rial for Torob's API.
     *
     * This conversion affects API output only, not database values.
     */
    protected function toRial($value): int
    {
        return $this->toInteger($value) * 10;
    }

    /**
     * Convert price to integer.
     */
    protected function toInteger($value): int
    {
        return (int) round((float) $value);
    }

    /**
     * Limit a UTF-8 string without breaking multibyte characters.
     */
    protected function limitString(
        string $value,
        int $limit
    ): string {
        return Str::limit($value, $limit, '');
    }
}
