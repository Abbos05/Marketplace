<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\CatalogProductSeedData;
use App\Support\CatalogSeedImagePool;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    private const PRODUCTS_PER_CATEGORY = 4;

    private const SELLER_IDS = [6, 7];

    public function run(): void
    {
        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        $categorySlugs = $leaves->pluck('slug')->filter()->values()->all();
        CatalogProductSeedData::assertCoverage($categorySlugs, self::PRODUCTS_PER_CATEGORY, 3);

        $imagePool = new CatalogSeedImagePool;
        $imagePool->prepareSellers(self::SELLER_IDS);
        $imagePool->registerSeedPlan($categorySlugs, self::PRODUCTS_PER_CATEGORY);

        $now = Carbon::now();
        $globalSeq = 0;

        foreach ($leaves as $leaf) {
            $templates = CatalogProductSeedData::forCategorySlug((string) $leaf->slug);

            foreach ($templates as $productIndexInCategory => $template) {
                $globalSeq++;
                $sellerId = ($globalSeq % 2 === 1) ? 6 : 7;

                $product = Product::query()->create([
                    'seller_id' => $sellerId,
                    'category_id' => $leaf->id,
                    'title' => (string) $template['title'],
                    'description' => (string) $template['description'],
                    'short_description' => (string) $template['short_description'],
                    'min_price' => 0,
                    'sales_count' => rand(0, 120),
                    'status' => 'approved',
                    'is_on_action' => true,
                    'created_at' => $now->copy()->subDays(rand(1, 45)),
                    'updated_at' => $now,
                ]);

                $productAttrs = is_array($template['product_attrs'] ?? null)
                    ? $template['product_attrs']
                    : [];
                $this->seedAttributes($product, $leaf->id, $productAttrs);

                $variantRows = is_array($template['variants'] ?? null)
                    ? $template['variants']
                    : [];

                $productVariantSources = $imagePool->resolveProductVariants(
                    $sellerId,
                    (string) $leaf->slug,
                    $productIndexInCategory,
                );

                $this->seedVariants(
                    $product,
                    $variantRows,
                    $sellerId,
                    $imagePool,
                    $productVariantSources,
                );

                $minPrice = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->min('price');

                $product->update(['min_price' => $minPrice]);
            }
        }
    }

    private function seedAttributes(Product $product, int $categoryId, array $attrs): void
    {
        $definitions = CategoryAttribute::query()
            ->where('category_id', $categoryId)
            ->where('applies_to', 'product')
            ->get()
            ->keyBy('name');

        foreach ($attrs as $name => $value) {
            $def = $definitions->get($name);
            if (! $def) {
                continue;
            }
            ProductAttributeValue::query()->create([
                'product_id' => $product->id,
                'attribute_id' => $def->id,
                'value' => (string) $value,
            ]);
        }

        foreach ($definitions as $def) {
            if (isset($attrs[$def->name])) {
                continue;
            }
            if (! $def->required) {
                continue;
            }
            $fallback = $this->fallbackAttributeValue($def);
            if ($fallback !== null) {
                ProductAttributeValue::query()->create([
                    'product_id' => $product->id,
                    'attribute_id' => $def->id,
                    'value' => $fallback,
                ]);
            }
        }
    }

    private function fallbackAttributeValue(CategoryAttribute $def): ?string
    {
        if ($def->type === 'select' && $def->options) {
            $opts = is_string($def->options) ? json_decode($def->options, true) : $def->options;

            return is_array($opts) && $opts !== [] ? (string) $opts[0] : '—';
        }

        return match ($def->name) {
            'Бренд' => 'Без бренда',
            'Диагональ экрана', 'Диагональ' => '6',
            default => '—',
        };
    }

    /**
     * @param  list<array{options?: array<string, string>, price: float|int, old_price?: float|int|null, stock?: int}>  $variants
     * @param  list<array{main: string, extras: list<string>}>|null  $productVariantSources
     */
    private function seedVariants(
        Product $product,
        array $variants,
        int $sellerId,
        CatalogSeedImagePool $imagePool,
        ?array $productVariantSources,
    ): void {
        foreach ($variants as $vi => $v) {
            $price = (float) $v['price'];
            $oldPrice = isset($v['old_price']) ? (float) $v['old_price'] : null;
            $discount = ($oldPrice && $oldPrice > $price)
                ? round((1 - $price / $oldPrice) * 100, 2)
                : 0;

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'options' => $v['options'] ?? [],
                'price' => $price,
                'old_price' => $oldPrice,
                'discount_percent' => $discount,
                'stock' => (int) ($v['stock'] ?? 10),
                'views_count' => rand(200, 9000),
                'weight_grams' => rand(200, 12000),
                'is_active' => true,
            ]);

            $sources = $productVariantSources[$vi] ?? $productVariantSources[0] ?? ['main' => '', 'extras' => []];

            foreach ($imagePool->materializeVariantImages($sellerId, $variant->id, $sources) as $row) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'url' => $row['url'],
                    'sort_order' => $row['sort_order'],
                    'is_main' => $row['is_main'],
                ]);
            }
        }
    }
}
