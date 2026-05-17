<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CatalogTextSearchService
{
    private const MIN_QUERY_LENGTH = 3;

    private const MIN_WORD_LENGTH = 3;

    private const MIN_VARIANT_PHRASE_LENGTH = 4;

  /** Разделители как на карточке каталога: «Название · 512GB · Черный». */
    private const SEGMENT_SPLIT_PATTERN = '/\s*(?:·|•|—|–|»|«|\||\n|\r\n)\s*|\s+-\s+/u';

    /** @var array<string> */
    private const COLOR_TOKENS = [
        'черный', 'чёрный', 'белый', 'синий', 'красный', 'серый', 'бежевый',
        'зеленый', 'зелёный', 'желтый', 'жёлтый', 'розовый', 'фиолетовый',
        'коричневый', 'оранжевый', 'золотой', 'titanium', 'венге', 'дуб',
    ];

    /** @var array<string> */
    private array $stopWords = [
        'и', 'в', 'во', 'на', 'с', 'со', 'по', 'из', 'от', 'до', 'для', 'при', 'без', 'над', 'под',
        'the', 'a', 'an', 'of', 'in', 'on', 'at', 'to', 'for',
    ];

    /**
     * @param  array<string>  $fields  columns on products table without prefix
     */
    public function apply(Builder $query, string $search, array $fields): void
    {
        $search = $this->normalizeText(trim(preg_replace('/\s+/u', ' ', $search) ?? ''));
        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            $query->whereRaw('1 = 0');

            return;
        }

        $structuredSegments = $this->extractStructuredSegments($search);
        $titleSpecs = $structuredSegments === null ? $this->splitTitleAndVariantSpecs($search) : null;

        // Запрос как на карточке (название + характеристики) — только точное совпадение,
        // без OR по отдельным «128GB», «Белый» и т.д.
        if ($structuredSegments !== null) {
            $this->applyCardStyleSegments($query, $fields, $structuredSegments);

            return;
        }

        if ($titleSpecs !== null) {
            $this->applyCardStyleSegments($query, $fields, array_merge([$titleSpecs['title']], $titleSpecs['specs']));

            return;
        }

        $this->applyFlexibleSearch($query, $fields, $search);
    }

    /**
     * Ранжирование: точное совпадение → начало названия → вхождение фразы.
     *
     * @param  array<string>  $fields
     */
    public function applyRelevanceOrder(Builder $query, string $search, array $fields): void
    {
        $search = $this->normalizeText(trim(preg_replace('/\s+/u', ' ', $search) ?? ''));
        if ($search === '') {
            return;
        }

        $lower = mb_strtolower($search, 'UTF-8');
        $likeContains = '%'.$this->escapeLike($search).'%';
        $likeStarts = $this->escapeLike($search).'%';
        $titleNorm = "LOWER(REPLACE(REPLACE(products.title, 'ё', 'е'), 'Ё', 'Е'))";

        $query->orderByRaw(
            "(CASE
                WHEN {$titleNorm} = ? THEN 0
                WHEN products.title LIKE ? THEN 10
                WHEN products.title LIKE ? THEN 20
                ELSE 100
            END) ASC",
            [$lower, $likeStarts, $likeContains],
        );
    }

    /**
     * Сегменты как на карточке: «Название · 512GB · Белый» или «Название — Стандарт».
     *
     * @return array<string>|null
     */
    public function extractStructuredSegments(string $search): ?array
    {
        $search = $this->normalizeText(trim(preg_replace('/\s+/u', ' ', $search) ?? ''));
        if ($search === '') {
            return null;
        }

        $segments = preg_split(self::SEGMENT_SPLIT_PATTERN, $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $segments = array_values(array_filter(array_map('trim', $segments), fn (string $s) => $s !== ''));

        if (count($segments) < 2 || mb_strlen($segments[0]) < 2) {
            return null;
        }

        return $segments;
    }

    /** @return array<string>|null */
    public function extractCardSegments(string $search): ?array
    {
        if ($search === '' || ! preg_match('/[·•]/u', $search)) {
            return null;
        }

        return $this->extractStructuredSegments($search);
    }

    /**
     * Без точки: «ТВ и медиа 512GB Черный» → название + характеристики варианта с конца.
     *
     * @return array{title: string, specs: array<string>}|null
     */
    public function splitTitleAndVariantSpecs(string $search): ?array
    {
        $parts = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        $specs = [];

        while (count($parts) >= 2) {
            $lastTwo = $parts[count($parts) - 2].' '.$parts[count($parts) - 1];
            if ($this->isVolumeSpec($lastTwo)) {
                array_pop($parts);
                array_pop($parts);
                array_unshift($specs, $lastTwo);

                continue;
            }
            break;
        }

        while (count($parts) > 1) {
            $last = (string) $parts[array_key_last($parts)];
            if ($this->isStrictVariantSpec($last) || $this->isColorSpec($last)) {
                array_pop($parts);
                array_unshift($specs, $last);

                continue;
            }
            break;
        }

        $title = trim(implode(' ', $parts));
        if ($title === '' || mb_strlen($title) < 2 || $specs === []) {
            return null;
        }

        return [
            'title' => $title,
            'specs' => $specs,
        ];
    }

    /**
     * @deprecated Используйте splitTitleAndVariantSpecs / extractCardSegments
     *
     * @return array{title: string, tail: string}|null
     */
    public function splitTitleAndTail(string $search): ?array
    {
        $split = $this->splitTitleAndVariantSpecs($search);
        if ($split === null) {
            return null;
        }

        $specs = $split['specs'];

        return [
            'title' => $split['title'],
            'tail' => (string) end($specs),
        ];
    }

    /**
     * @return array<string>
     */
    public function buildPhrases(string $search): array
    {
        $search = $this->normalizeText(trim(preg_replace('/\s+/u', ' ', $search) ?? ''));
        if ($search === '') {
            return [];
        }

        $phrases = [$search];

        $segments = preg_split(self::SEGMENT_SPLIT_PATTERN, $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '' || mb_strlen($segment) < self::MIN_QUERY_LENGTH) {
                continue;
            }
            if (! in_array($segment, $phrases, true)) {
                $phrases[] = $segment;
            }
        }

        usort($phrases, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values($phrases);
    }

    /**
     * @return array<string>
     */
    public function extractWords(string $text): array
    {
        $text = mb_strtolower($this->normalizeText(trim($text)));
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) < self::MIN_WORD_LENGTH) {
                continue;
            }
            if (in_array($part, $this->stopWords, true)) {
                continue;
            }
            if (! in_array($part, $words, true)) {
                $words[] = $part;
            }
        }

        return $words;
    }

    /**
     * @param  array<string>  $fields
     */
    protected function applyFlexibleSearch(Builder $query, array $fields, string $search): void
    {
        $phrases = array_values(array_filter(
            $this->buildPhrases($search),
            fn (string $p) => mb_strlen($p) >= self::MIN_QUERY_LENGTH,
        ));

        $words = array_values(array_filter(
            $this->extractWords($search),
            fn (string $w) => mb_strlen($w) >= self::MIN_WORD_LENGTH,
        ));

        $query->where(function (Builder $outer) use ($fields, $phrases, $words, $search) {
            $applied = false;

            $this->orWhereGroup($outer, function (Builder $q) use ($fields, $search) {
                $this->applyPhrase($q, $fields, $search);
            }, $applied);

            foreach ($phrases as $phrase) {
                if ($phrase === $search) {
                    continue;
                }
                $this->orWhereGroup($outer, function (Builder $q) use ($fields, $phrase) {
                    $this->applyPhrase($q, $fields, $phrase);
                }, $applied);
            }

            if (count($words) >= 2) {
                $this->orWhereGroup($outer, function (Builder $q) use ($fields, $words) {
                    $this->applyAllWords($q, $fields, $words);
                }, $applied);
            }

            if (count($words) === 1) {
                $this->orWhereGroup($outer, function (Builder $q) use ($fields, $words) {
                    $this->applyVariantOrAttributeWord($q, $words[0]);
                }, $applied);
            }

            if (count($words) >= 2) {
                $this->orWhereGroup($outer, function (Builder $q) use ($fields, $words) {
                    $this->applyAnyWord($q, $fields, $words);
                }, $applied);
            }

            foreach ($phrases as $phrase) {
                if (mb_strlen($phrase) < self::MIN_VARIANT_PHRASE_LENGTH) {
                    continue;
                }
                $this->orWhereGroup($outer, function (Builder $q) use ($phrase) {
                    $this->applyVariantPhrase($q, $phrase);
                }, $applied);
            }

            if (! $applied) {
                $outer->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Название + все характеристики варианта (память, цвет, …) в одном варианте.
     *
     * @param  array<string>  $segments  [title, spec1, spec2, …]
     * @param  array<string>  $fields
     */
    protected function applyCardStyleSegments(Builder $query, array $fields, array $segments): void
    {
        $title = trim((string) ($segments[0] ?? ''));
        $specs = array_values(array_filter(
            array_map('trim', array_slice($segments, 1)),
            fn (string $s) => $s !== '' && mb_strlen($s) >= 2,
        ));

        if ($title === '' || mb_strlen($title) < 2) {
            $query->whereRaw('1 = 0');

            return;
        }

        $titleForms = $this->caseForms($title);

        $query->where(function (Builder $q) use ($fields, $title, $titleForms, $specs) {
            $q->where(function (Builder $titleQuery) use ($fields, $title, $titleForms) {
                $this->applyPhrase($titleQuery, $fields, $title);
                if ($titleForms !== []) {
                    $titleQuery->orWhereIn('products.title', $titleForms);
                }
            });

            if ($specs === []) {
                return;
            }

            $q->whereHas('variants', function (Builder $vq) use ($specs) {
                foreach ($specs as $spec) {
                    $vq->where(function (Builder $specQuery) use ($spec) {
                        $this->applyVariantOptionMatch($specQuery, $spec);
                    });
                }
            });
        });
    }

    protected function applyVariantOptionMatch(Builder $query, string $spec): void
    {
        $forms = $this->caseForms($spec);
        if ($forms === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $inner) use ($forms) {
            $this->applyLikeForms($inner, 'options', $forms, useWhereForFirst: true);
        });
    }

    protected function isStrictVariantSpec(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return (bool) preg_match('/^\d+\s*(?:GB|TB|MB|ГБ|ТБ|МБ)$/ui', $token)
            || (bool) preg_match('/^(?:XXS|XS|S|M|L|XL|XXL|XXXL|\d{1,3})$/ui', $token);
    }

    protected function isVolumeSpec(string $token): bool
    {
        return (bool) preg_match('/^\d+(?:[.,]\d+)?\s*(?:мл|ml|л|l|г|g|кг|kg)$/ui', trim($token));
    }

    protected function isColorSpec(string $token): bool
    {
        return in_array(mb_strtolower($this->normalizeText(trim($token)), 'UTF-8'), self::COLOR_TOKENS, true);
    }

    /**
     * @param  array<string>  $fields
     * @param  array<string>  $words
     */
    protected function applyAnyWord(Builder $query, array $fields, array $words): void
    {
        $query->where(function (Builder $outer) use ($fields, $words) {
            foreach ($words as $word) {
                $forms = $this->caseForms($word);
                if ($forms === []) {
                    continue;
                }

                $outer->orWhere(function (Builder $q) use ($fields, $forms) {
                    $q->where(function (Builder $productMatch) use ($fields, $forms) {
                        foreach ($fields as $field) {
                            $this->applyLikeForms($productMatch, 'products.'.$field, $forms);
                        }
                        $this->applyAttributeValueForms($productMatch, $forms);
                    });
                    $q->orWhereHas('variants', function (Builder $vq) use ($forms) {
                        $vq->where(function (Builder $inner) use ($forms) {
                            $this->applyLikeForms($inner, 'options', $forms, useWhereForFirst: true);
                        });
                    });
                });
            }
        });
    }

    /**
     * @param  array<string>  $fields
     */
    protected function applyPhrase(Builder $query, array $fields, string $phrase): void
    {
        $forms = $this->caseForms($phrase);
        if ($forms === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($fields, $forms) {
            foreach ($fields as $field) {
                $this->applyLikeForms($q, 'products.'.$field, $forms);
            }
            $q->orWhere(function (Builder $attrQuery) use ($forms) {
                $this->applyAttributeValueForms($attrQuery, $forms);
            });
        });
    }

    /**
     * @param  array<string>  $fields
     * @param  array<string>  $words
     */
    protected function applyAllWords(Builder $query, array $fields, array $words): void
    {
        foreach ($words as $word) {
            $forms = $this->caseForms($word);
            if ($forms === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $q) use ($fields, $forms) {
                $q->where(function (Builder $productMatch) use ($fields, $forms) {
                    foreach ($fields as $field) {
                        $this->applyLikeForms($productMatch, 'products.'.$field, $forms);
                    }
                    $this->applyAttributeValueForms($productMatch, $forms);
                });
                $q->orWhereHas('variants', function (Builder $vq) use ($forms) {
                    $vq->where(function (Builder $inner) use ($forms) {
                        $this->applyLikeForms($inner, 'options', $forms, useWhereForFirst: true);
                    });
                });
            });
        }
    }

    protected function applyVariantOrAttributeWord(Builder $query, string $word): void
    {
        $forms = $this->caseForms($word);
        if ($forms === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($forms) {
            $q->whereHas('variants', function (Builder $vq) use ($forms) {
                $vq->where(function (Builder $inner) use ($forms) {
                    $this->applyLikeForms($inner, 'options', $forms, useWhereForFirst: true);
                });
            });
            $this->applyAttributeValueForms($q, $forms);
        });
    }

    protected function applyVariantPhrase(Builder $query, string $phrase): void
    {
        $forms = $this->caseForms($phrase);
        if ($forms === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('variants', function (Builder $vq) use ($forms) {
            $vq->where(function (Builder $inner) use ($forms) {
                $this->applyLikeForms($inner, 'options', $forms, useWhereForFirst: true);
            });
        });
    }

    /**
     * @param  array<string>  $forms
     */
    protected function applyAttributeValueForms(Builder $query, array $forms): void
    {
        $query->orWhereHas('attributeValues', function (Builder $av) use ($forms) {
            $av->where(function (Builder $inner) use ($forms) {
                $this->applyLikeForms($inner, 'value', $forms, useWhereForFirst: true);
            });
        });
    }

    /**
     * @param  array<string>  $forms
     */
    protected function applyLikeForms(
        Builder $query,
        string $column,
        array $forms,
        bool $useWhereForFirst = false,
    ): void {
        $patterns = $column === 'options'
            ? $this->likePatternsForJsonColumn($forms)
            : $this->likePatterns($forms);

        foreach ($patterns as $index => $pattern) {
            if ($useWhereForFirst && $index === 0) {
                $query->where($column, 'like', $pattern);
            } else {
                $query->orWhere($column, 'like', $pattern);
            }
        }
    }

    /**
     * @param  array<string>  $forms
     * @return array<string>
     */
    protected function likePatterns(array $forms): array
    {
        $patterns = [];
        foreach ($forms as $form) {
            $patterns[] = '%'.$this->escapeLike($form).'%';
        }

        return array_values(array_unique($patterns));
    }

    /**
     * JSON в БД часто с \\uXXXX — ищем и обычный текст, и json_encode-форму.
     *
     * @param  array<string>  $forms
     * @return array<string>
     */
    protected function likePatternsForJsonColumn(array $forms): array
    {
        $patterns = $this->likePatterns($forms);

        foreach ($forms as $form) {
            $unicode = json_encode($form, JSON_UNESCAPED_UNICODE);
            if (is_string($unicode)) {
                $patterns[] = '%'.$this->escapeLikeJsonFragment(trim($unicode, '"')).'%';
            }

            $escaped = json_encode($form);
            if (is_string($escaped)) {
                $patterns[] = '%'.$this->escapeLikeJsonFragment(trim($escaped, '"')).'%';
            }
        }

        return array_values(array_unique($patterns));
    }

    protected function orWhereGroup(Builder $outer, callable $callback, bool &$applied): void
    {
        $outer->orWhere(function (Builder $group) use ($callback) {
            $callback($group);
        });
        $applied = true;
    }

    /**
     * @return array<string>
     */
    protected function caseForms(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $forms = [
            $value,
            mb_strtolower($value, 'UTF-8'),
            mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
            $this->normalizeText($value),
            mb_strtolower($this->normalizeText($value), 'UTF-8'),
            mb_convert_case(mb_strtolower($this->normalizeText($value), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        ];

        $withYo = [];
        foreach ($forms as $form) {
            if ($form === '') {
                continue;
            }
            $withYo[] = str_replace(['е', 'Е'], ['ё', 'Ё'], $form);
            $withYo[] = str_replace(['ё', 'Ё'], ['е', 'Е'], $form);
        }

        return array_values(array_unique(array_filter(
            array_merge($forms, $withYo),
            fn ($v) => $v !== '',
        )));
    }

    protected function normalizeText(string $text): string
    {
        return str_replace(['ё', 'Ё'], ['е', 'Е'], $text);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->normalizeText($value));
    }

    /** Для фрагментов \\uXXXX в JSON — не удваиваем обратный слэш. */
    protected function escapeLikeJsonFragment(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
