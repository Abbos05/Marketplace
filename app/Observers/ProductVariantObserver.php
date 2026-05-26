<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\ArticleNumberService;
use Illuminate\Support\Str;

class ProductVariantObserver
{
    public function __construct(
        private readonly ArticleNumberService $articles,
    ) {}

    public function creating(ProductVariant $variant): void
    {
        if (empty($variant->sku)) {
            $variant->sku = '__pending__'.Str::uuid();
        }
    }

    public function created(ProductVariant $variant): void
    {
        $sku = $this->articles->format((int) $variant->id);
        if ($variant->sku !== $sku) {
            $variant->forceFill(['sku' => $sku])->saveQuietly();
        }
    }
}
