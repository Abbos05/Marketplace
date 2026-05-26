<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class CatalogProductSeedData
{
    private const ROOT_SLUGS = [
        'electronics',
        'clothing',
        'home',
        'sports',
        'auto',
        'books',
        'beauty',
        'kids',
        'pets',
        'furniture',
    ];

  /** @var array<string, list<array<string, mixed>>|null> */
    private static ?array $cache = null;

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $merged = [];
        foreach (self::ROOT_SLUGS as $rootSlug) {
            $path = database_path("data/catalog/{$rootSlug}.php");
            if (! is_file($path)) {
                throw new RuntimeException("Catalog seed data file missing: {$path}");
            }
            /** @var array<string, list<array<string, mixed>>> $chunk */
            $chunk = require $path;
            $merged = array_merge($merged, $chunk);
        }

        self::$cache = $merged;

        return $merged;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forCategorySlug(string $slug): array
    {
        $all = self::all();
        if (! isset($all[$slug])) {
            throw new InvalidArgumentException("No catalog seed products for category slug [{$slug}].");
        }

        return $all[$slug];
    }

    /**
     * @param  list<string>  $expectedSlugs
     */
    public static function assertCoverage(array $expectedSlugs, int $productsPerCategory = 4, int $variantsPerProduct = 3): void
    {
        $all = self::all();
        $errors = [];

        foreach ($expectedSlugs as $slug) {
            if (! isset($all[$slug])) {
                $errors[] = "Missing category slug: {$slug}";

                continue;
            }

            $products = $all[$slug];
            if (count($products) !== $productsPerCategory) {
                $errors[] = "{$slug}: expected {$productsPerCategory} products, got ".count($products);
            }

            foreach ($products as $i => $product) {
                $label = "{$slug}[{$i}]";

                if (empty($product['title']) || ! is_string($product['title'])) {
                    $errors[] = "{$label}: missing title";
                } elseif (stripos($product['title'], 'демо') !== false) {
                    $errors[] = "{$label}: title contains «Демо»";
                }

                foreach (['short_description', 'description'] as $field) {
                    if (empty($product[$field]) || stripos((string) $product[$field], 'демо') !== false) {
                        $errors[] = "{$label}: invalid {$field}";
                    }
                }

                if (! isset($product['product_attrs']) || ! is_array($product['product_attrs'])) {
                    $errors[] = "{$label}: missing product_attrs";
                }

                $variants = $product['variants'] ?? [];
                if (count($variants) !== $variantsPerProduct) {
                    $errors[] = "{$label}: expected {$variantsPerProduct} variants, got ".count($variants);
                }

                foreach ($variants as $vi => $variant) {
                    if (empty($variant['options']) || ! is_array($variant['options'])) {
                        $errors[] = "{$label} variant[{$vi}]: missing options";
                    }
                    if (! isset($variant['price'])) {
                        $errors[] = "{$label} variant[{$vi}]: missing price";
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("Catalog seed data validation failed:\n- ".implode("\n- ", $errors));
        }
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
