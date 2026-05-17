<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\ArticleNumberService;
use Illuminate\Http\RedirectResponse;

class ArticleController extends Controller
{
    public function show(string $sku, ArticleNumberService $articles): RedirectResponse
    {
        if (! $articles->isStrictArticleQuery($sku)) {
            abort(404);
        }

        $variant = $articles->findVariantForArticleSearch($sku)
            ?? ProductVariant::query()->where('sku', trim($sku))->first();

        if (! $variant?->product) {
            abort(404);
        }

        return redirect()->route('product.show', [
            'product' => $variant->product_id,
            'variant' => $variant->id,
        ]);
    }
}
