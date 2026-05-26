<?php

namespace App\Services;

use App\Models\ProductVariant;

class ArticleNumberService
{
    public function prefix(): string
    {
        return (string) config('marketplace.article.prefix', '000');
    }

    public function format(int $variantId): string
    {
        return $this->prefix().$variantId;
    }

    public function normalize(string $input): string
    {
        return preg_replace('/\D+/', '', trim($input)) ?? '';
    }

    /**
     * Resolve variant id from scanned or typed article string.
     */
    public function parse(string $input): ?int
    {
        $digits = $this->normalize($input);
        if ($digits === '') {
            return null;
        }

        $prefix = $this->prefix();
        if (str_starts_with($digits, $prefix)) {
            $suffix = substr($digits, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                return (int) $suffix;
            }
        }

        if (strlen($digits) > strlen($prefix) && ctype_digit($digits)) {
            $suffix = substr($digits, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                return (int) $suffix;
            }
        }

        return null;
    }

    public function isArticleQuery(string $input): bool
    {
        $digits = $this->normalize($input);
        $prefix = $this->prefix();

        if ($digits === '' || ! str_starts_with($digits, $prefix)) {
            return false;
        }

        $suffix = substr($digits, strlen($prefix));

        return $suffix !== '' && ctype_digit($suffix);
    }

    /** Поиск по артикулу: только строка из цифр, без букв и лишних символов. */
    public function isStrictArticleQuery(string $input): bool
    {
        $trimmed = trim($input);
        if ($trimmed === '' || ! preg_match('/^\d+$/', $trimmed)) {
            return false;
        }

        return $this->isArticleQuery($trimmed);
    }

    /**
     * Exact article match for catalog search — opens a specific variant, not the whole product.
     */
    public function findVariantForArticleSearch(string $input): ?ProductVariant
    {
        if (! $this->isStrictArticleQuery($input)) {
            return null;
        }

        $normalized = trim($input);
        if ($normalized === '') {
            return null;
        }

        return ProductVariant::query()
            ->where('sku', $normalized)
            ->where('is_active', true)
            ->whereHas('product', function ($q) {
                $q->where('status', 'approved')->where('is_on_action', true);
            })
            ->first();
    }

    /**
     * @return array{prefix: string, id: string}
     */
    public function split(string $sku): array
    {
        $prefix = $this->prefix();
        if (str_starts_with($sku, $prefix)) {
            return [
                'prefix' => $prefix,
                'id' => substr($sku, strlen($prefix)),
            ];
        }

        return [
            'prefix' => '',
            'id' => $sku,
        ];
    }
}
