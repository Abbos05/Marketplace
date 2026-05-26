<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CatalogSearchSuggestionService
{
    public const MIN_QUERY_LENGTH = 2;

    public const MAX_SUGGESTIONS = 10;

    public function __construct(
        private readonly ArticleNumberService $articles,
    ) {}

    /**
     * @return array{query: string, suggestions: list<array<string, mixed>>}
     */
    public function suggest(string $rawQuery): array
    {
        $query = $this->normalizeQuery($rawQuery);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return ['query' => $query, 'suggestions' => []];
        }

        if ($this->articles->isArticleQuery($query)) {
            return [
                'query' => $query,
                'suggestions' => [[
                    'type' => 'article',
                    'text' => $query,
                    'label' => 'Артикул '.$query,
                ]],
            ];
        }

        $lower = mb_strtolower($query, 'UTF-8');
        $likeContains = '%'.$this->escapeLike($query).'%';
        $likeStarts = $this->escapeLike($query).'%';
        $titleNorm = "LOWER(REPLACE(REPLACE(products.title, 'ё', 'е'), 'Ё', 'Е'))";

        $products = Product::query()
            ->visibleInCatalog()
            ->select(['products.id', 'products.title', 'products.min_price', 'products.category_id', 'products.sales_count'])
            ->with(['category:id,name,icon'])
            ->where(function ($outer) use ($titleNorm, $lower, $likeContains, $likeStarts) {
                $outer
                    ->whereRaw("{$titleNorm} LIKE ?", [$lower.'%'])
                    ->orWhereRaw("{$titleNorm} LIKE ?", ['%'.$lower.'%'])
                    ->orWhereHas('variants', function ($vq) use ($likeContains) {
                        $vq->where('is_active', true)
                            ->whereRaw('LOWER(CAST(product_variants.options AS CHAR)) LIKE ?', [mb_strtolower($likeContains, 'UTF-8')]);
                    });
            })
            ->orderByRaw(
                "(CASE
                    WHEN {$titleNorm} = ? THEN 0
                    WHEN {$titleNorm} LIKE ? THEN 10
                    WHEN {$titleNorm} LIKE ? THEN 20
                    ELSE 40
                END) ASC",
                [$lower, mb_strtolower($likeStarts, 'UTF-8'), mb_strtolower($likeContains, 'UTF-8')],
            )
            ->orderByDesc('products.sales_count')
            ->limit(12)
            ->get();

        $suggestions = [];
        $seen = [];

        foreach ($this->buildTitleSuggestions($query, $products) as $item) {
            $key = 't:'.$item['text'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $suggestions[] = $item;
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return ['query' => $query, 'suggestions' => $suggestions];
    }

    /**
     * @return list<array{type: string, text: string, label: string, image?: string|null}>
     */
    protected function buildTitleSuggestions(string $query, Collection $products): array
    {
        $lowerQuery = mb_strtolower($query, 'UTF-8');
        $out = [];
        $seenTitles = [];

        foreach ($products as $product) {
            $title = trim((string) $product->title);
            if ($title === '' || isset($seenTitles[$title])) {
                continue;
            }

            $lowerTitle = mb_strtolower($title, 'UTF-8');
            $suggestionText = null;

            if (str_starts_with($lowerTitle, $lowerQuery)) {
                $suggestionText = $title;
            } elseif (str_contains($lowerTitle, $lowerQuery)) {
                $suggestionText = $title;
            } else {
                $suggestionText = $this->completeTitleFromPartialWord($query, $title);
            }

            if ($suggestionText === null) {
                continue;
            }

            $seenTitles[$title] = true;
            $icon = $product->category?->icon;

            $out[] = [
                'type' => 'query',
                'text' => $suggestionText,
                'label' => $suggestionText,
                'image' => $icon ? Product::normalizeListingUrl($icon) : null,
            ];
        }

        return $out;
    }

    protected function completeTitleFromPartialWord(string $query, string $title): ?string
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $lowerQuery = mb_strtolower($query, 'UTF-8');
        $lowerTitle = mb_strtolower($title, 'UTF-8');

        if (str_contains($lowerTitle, $lowerQuery) && ! str_starts_with($lowerTitle, $lowerQuery)) {
            $pos = mb_strpos($lowerTitle, $lowerQuery, 0, 'UTF-8');
            if ($pos !== false && $pos > 0) {
                return $title;
            }
        }

        $queryWords = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $titleWords = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($queryWords === [] || $titleWords === []) {
            return null;
        }

        $prefixWords = array_slice($queryWords, 0, -1);
        $partial = mb_strtolower((string) end($queryWords), 'UTF-8');
        if (mb_strlen($partial) < 2) {
            return null;
        }

        foreach ($titleWords as $idx => $word) {
            $lowerWord = mb_strtolower($word, 'UTF-8');
            if (! str_starts_with($lowerWord, $partial)) {
                continue;
            }

            if ($prefixWords !== []) {
                $titlePrefix = implode(' ', array_slice($titleWords, 0, $idx));
                if (mb_strtolower($titlePrefix, 'UTF-8') !== mb_strtolower(implode(' ', $prefixWords), 'UTF-8')) {
                    continue;
                }
            }

            $completedWords = array_merge($prefixWords, array_slice($titleWords, $idx));
            $completed = implode(' ', $completedWords);
            if (mb_strtolower($completed, 'UTF-8') !== mb_strtolower($query, 'UTF-8')) {
                return $completed;
            }
        }

        return null;
    }

    protected function normalizeQuery(string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');

        return str_replace(['ё', 'Ё'], ['е', 'Е'], $query);
    }

    protected function escapeLike(string $value): string
    {
        $value = str_replace(['ё', 'Ё'], ['е', 'Е'], $value);

        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
