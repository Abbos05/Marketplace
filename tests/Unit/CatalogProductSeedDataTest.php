<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Support\CatalogProductSeedData;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductSeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_leaf_categories_have_four_products_with_three_variants(): void
    {
        $this->seed(CategorySeeder::class);

        $slugs = Category::query()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('id')
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();

        $this->assertCount(40, $slugs);

        CatalogProductSeedData::flushCache();
        CatalogProductSeedData::assertCoverage($slugs, 4, 3);

        $all = CatalogProductSeedData::all();
        $this->assertCount(40, $all);
        $this->assertSame(160, array_sum(array_map('count', $all)));
    }

    public function test_product_attribute_keys_match_category_definitions(): void
    {
        $this->seed(CategorySeeder::class);

        CatalogProductSeedData::flushCache();
        $all = CatalogProductSeedData::all();

        foreach ($all as $slug => $products) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();

            $productAttrNames = CategoryAttribute::query()
                ->where('category_id', $category->id)
                ->where('applies_to', 'product')
                ->pluck('name')
                ->all();

            $variantAttrNames = CategoryAttribute::query()
                ->where('category_id', $category->id)
                ->where('applies_to', 'variant')
                ->pluck('name')
                ->all();

            foreach ($products as $product) {
                foreach (array_keys($product['product_attrs'] ?? []) as $attrName) {
                    $this->assertContains(
                        $attrName,
                        $productAttrNames,
                        "Unknown product attribute [{$attrName}] for {$slug}"
                    );
                }

                foreach ($product['variants'] ?? [] as $variant) {
                    foreach (array_keys($variant['options'] ?? []) as $optName) {
                        $this->assertContains(
                            $optName,
                            $variantAttrNames,
                            "Unknown variant option [{$optName}] for {$slug}"
                        );
                    }
                }
            }
        }
    }

    public function test_select_attribute_values_are_from_allowed_options(): void
    {
        $this->seed(CategorySeeder::class);

        CatalogProductSeedData::flushCache();
        $all = CatalogProductSeedData::all();

        foreach ($all as $slug => $products) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();

            $selectAttrs = CategoryAttribute::query()
                ->where('category_id', $category->id)
                ->where('type', 'select')
                ->get()
                ->keyBy('name');

            foreach ($products as $product) {
                foreach ($product['product_attrs'] ?? [] as $name => $value) {
                    $def = $selectAttrs->get($name);
                    if (! $def || $def->applies_to !== 'product') {
                        continue;
                    }
                    $opts = is_string($def->options) ? json_decode($def->options, true) : $def->options;
                    $this->assertContains(
                        $value,
                        $opts ?? [],
                        "Invalid product attr value [{$value}] for {$name} in {$slug}"
                    );
                }

                foreach ($product['variants'] ?? [] as $variant) {
                    foreach ($variant['options'] ?? [] as $name => $value) {
                        $def = $selectAttrs->get($name);
                        if (! $def || $def->applies_to !== 'variant') {
                            continue;
                        }
                        $opts = is_string($def->options) ? json_decode($def->options, true) : $def->options;
                        $this->assertContains(
                            $value,
                            $opts ?? [],
                            "Invalid variant option [{$value}] for {$name} in {$slug}"
                        );
                    }
                }
            }
        }
    }
}
