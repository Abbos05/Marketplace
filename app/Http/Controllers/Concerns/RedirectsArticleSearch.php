<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ArticleNumberService;
use Illuminate\Http\RedirectResponse;

trait RedirectsArticleSearch
{
    protected function redirectIfArticleSearch(?string $search): ?RedirectResponse
    {
        $search = trim((string) $search);
        $articles = app(ArticleNumberService::class);
        if ($search === '' || ! $articles->isStrictArticleQuery($search)) {
            return null;
        }

        $variant = $articles->findVariantForArticleSearch($search);
        if (! $variant) {
            return null;
        }

        return redirect()->route('product.show', [
            'product' => $variant->product_id,
            'variant' => $variant->id,
        ]);
    }
}
