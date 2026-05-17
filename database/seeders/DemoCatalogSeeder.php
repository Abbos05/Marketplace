<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\CatalogProductSeedData;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    private const DEFAULT_IMAGE = '/img/products/default.png';

    private const PRODUCTS_PER_CATEGORY = 4;

    public function run(): void
    {
        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        $expectedSlugs = $leaves->pluck('slug')->filter()->values()->all();
        CatalogProductSeedData::assertCoverage($expectedSlugs, self::PRODUCTS_PER_CATEGORY, 3);

        $now = Carbon::now();
        $globalSeq = 0;

        foreach ($leaves as $leaf) {
            $templates = CatalogProductSeedData::forCategorySlug((string) $leaf->slug);

            foreach ($templates as $template) {
                $globalSeq++;
                $sellerId = ($globalSeq % 2 === 1) ? 6 : 7;

                $product = Product::query()->create([
                    'seller_id' => $sellerId,
                    'category_id' => $leaf->id,
                    'title' => (string) $template['title'],
                    'description' => (string) $template['description'],
                    'short_description' => (string) $template['short_description'],
                    'min_price' => 0,
                    'views_count' => rand(200, 9000),
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
                $variantIds = $this->seedVariants($product, $variantRows);

                $imageUrls = $this->resolveCatalogImageUrls($sellerId, $globalSeq);
                $this->seedImages($product, $imageUrls, $variantIds);

                $minPrice = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->min('price');

                $product->update(['min_price' => $minPrice]);
            }
        }
    }

    /** @return list<string> */
    private function resolveCatalogImageUrls(int $sellerId, int $seq): array
    {
        $urls = [];
        for ($k = 1; $k <= 3; $k++) {
            $found = null;
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $rel = "img/catalog/user-{$sellerId}/product-{$seq}/{$k}.{$ext}";
                if (file_exists(public_path($rel))) {
                    $found = '/'.$rel;
                    break;
                }
            }
            $urls[] = $found ?? self::DEFAULT_IMAGE;
        }

        return $urls;
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
     * @return list<int>
     */
    private function seedVariants(Product $product, array $variants): array
    {
        $ids = [];

        foreach ($variants as $v) {
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
                'weight_grams' => rand(200, 12000),
                'is_active' => true,
            ]);

            $ids[] = $variant->id;
        }

        return $ids;
    }

    /** @param list<int> $variantIds */
    private function seedImages(Product $product, array $imageUrls, array $variantIds): void
    {
        $urls = array_values(array_filter($imageUrls, fn ($u) => is_string($u) && $u !== ''));
        if ($urls === []) {
            $urls = [self::DEFAULT_IMAGE];
        }

        $productUrls = array_slice($urls, 0, 3);
        while (count($productUrls) < 3) {
            $productUrls[] = $productUrls[0];
        }

        foreach ($productUrls as $i => $url) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'variant_id' => null,
                'url' => $url,
                'sort_order' => $i,
                'is_main' => $i === 0,
            ]);
        }

        foreach ($variantIds as $vi => $variantId) {
            for ($imgIdx = 0; $imgIdx < 2; $imgIdx++) {
                $preview = $urls[($vi + $imgIdx) % count($urls)];
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'url' => $preview,
                    'sort_order' => 100 + ($vi * 10) + $imgIdx,
                    'is_main' => $imgIdx === 0,
                ]);
            }
        }
    }
}
