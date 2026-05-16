<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    private const DEFAULT_IMAGE = '/img/products/default.png';

    public function run(): void
    {
        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        $now = Carbon::now();
        $globalSeq = 0;

        foreach ($leaves as $leaf) {
            for ($p = 1; $p <= 4; $p++) {
                $globalSeq++;
                $sellerId = ($globalSeq % 2 === 1) ? 6 : 7;

                $title = "{$leaf->name}";
                $titleDescription = "{$leaf->name} — позиция {$p}";
                $product = Product::query()->create([
                    'seller_id' => $sellerId,
                    'category_id' => $leaf->id,
                    'title' => $title,
                    'description' => "Демо-описание для «{$titleDescription}». Товар для витрины маркетплейса Alvora.",
                    'short_description' => "Кратко: {$leaf->name}, демо-карточка №{$p}.",
                    'min_price' => 0,
                    'views_count' => rand(200, 9000),
                    'sales_count' => rand(0, 120),
                    'status' => 'approved',
                    'is_on_action' => true,
                    'created_at' => $now->copy()->subDays(rand(1, 45)),
                    'updated_at' => $now,
                ]);

                $productAttrs = $this->buildProductAttrValues($leaf->id, $globalSeq + $p);
                $this->seedAttributes($product, $leaf->id, $productAttrs);

                $variantRows = $this->buildThreeVariantRows($leaf->id, $globalSeq);
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

    /** @return array<string, string> */
    private function buildProductAttrValues(int $categoryId, int $salt): array
    {
        $defs = CategoryAttribute::query()
            ->where('category_id', $categoryId)
            ->where('applies_to', 'product')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($defs as $def) {
            $opts = is_string($def->options) ? json_decode($def->options, true) : $def->options;
            if ($def->type === 'select' && is_array($opts) && $opts !== []) {
                $idx = $salt % count($opts);
                $out[$def->name] = (string) $opts[$idx];
            } elseif ($def->type === 'number') {
                $out[$def->name] = (string) (5 + ($salt % 5));
            } else {
                $out[$def->name] = 'Демо '.$def->name.' #'.($salt % 1000);
            }
        }

        return $out;
    }

    /**
     * @return list<array{options: array<string, string>, price: float, old_price: ?float, stock: int}>
     */
    private function buildThreeVariantRows(int $categoryId, int $salt): array
    {
        $defs = CategoryAttribute::query()
            ->where('category_id', $categoryId)
            ->where('applies_to', 'variant')
            ->orderBy('id')
            ->get();

        $parsed = [];
        foreach ($defs as $d) {
            $opts = is_string($d->options) ? json_decode($d->options, true) : $d->options;
            $parsed[] = [
                'name' => $d->name,
                'options' => is_array($opts) ? array_values($opts) : [],
            ];
        }

        $rows = [];
        for ($vi = 0; $vi < 3; $vi++) {
            $options = [];
            foreach ($parsed as $j => $p) {
                $arr = $p['options'];
                if ($arr === []) {
                    $fallback = ['Стандарт', 'Плюс', 'Премиум'];
                    $options[$p['name']] = $fallback[$vi % 3];
                } else {
                    $idx = ($vi + $j + $salt) % count($arr);
                    $options[$p['name']] = (string) $arr[$idx];
                }
            }

            $base = 1200.0 + ($categoryId * 19 % 400) + $vi * 750 + ($salt % 97);
            $old = $vi === 2 ? round($base + 400, 2) : null;

            $rows[] = [
                'options' => $options,
                'price' => round($base, 2),
                'old_price' => $old,
                'stock' => 18 - $vi * 4,
            ];
        }

        return $rows;
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
            'Бренд' => 'NoName',
            'Диагональ экрана', 'Диагональ' => '6',
            default => '—',
        };
    }

    /**
     * @param  list<array{options: array<string, string>, price: float, old_price: ?float, stock: int}>  $variants
     * @return list<int>
     */
    private function seedVariants(Product $product, array $variants): array
    {
        $ids = [];

        foreach ($variants as $i => $v) {
            $price = (float) $v['price'];
            $oldPrice = isset($v['old_price']) ? (float) $v['old_price'] : null;
            $discount = ($oldPrice && $oldPrice > $price)
                ? round((1 - $price / $oldPrice) * 100, 2)
                : 0;

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => 'SKU-'.$product->id.'-'.($i + 1).'-'.Str::upper(Str::random(4)),
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
            $preview = $urls[$vi] ?? $urls[0];
            ProductImage::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'url' => $preview,
                'sort_order' => 100 + $vi,
                'is_main' => true,
            ]);
        }
    }
}
