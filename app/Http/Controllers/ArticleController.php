<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\ArticleNumberService;
use Illuminate\Http\RedirectResponse;

class ArticleController extends Controller
{
    public function show(string $sku, ArticleNumberService $articles): RedirectResponse
    {
        $normalizedSku = trim($sku);
        if (! $articles->isStrictArticleQuery($normalizedSku)) {
            return redirect()->route('home', ['search' => $normalizedSku]);
        }

        $variant = $articles->findVariantForArticleSearch($normalizedSku)
            ?? ProductVariant::query()->where('sku', $normalizedSku)->first();

        if (! $variant?->product) {
            return redirect()->route('home', ['search' => $normalizedSku]);
        }

        return redirect()->route('product.show', [
            'product' => $variant->product_id,
            'variant' => $variant->id,
        ]);
    }
}
