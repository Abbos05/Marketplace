<?php

namespace Tests\Unit;

use App\Services\CatalogTextSearchService;
use Tests\TestCase;

class CatalogTextSearchServiceTest extends TestCase
{
    private CatalogTextSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogTextSearchService;
    }

    public function test_build_phrases_splits_on_em_dash(): void
    {
        $phrases = $this->service->buildPhrases('Одежда для детей — Стандарт');

        $this->assertContains('Одежда для детей — Стандарт', $phrases);
        $this->assertContains('Одежда для детей', $phrases);
        $this->assertContains('Стандарт', $phrases);
    }

    public function test_build_phrases_puts_longer_segments_first(): void
    {
        $phrases = $this->service->buildPhrases('Одежда для детей — Стандарт');

        $this->assertSame('Одежда для детей — Стандарт', $phrases[0]);
    }

    public function test_extract_words_skips_stop_words(): void
    {
        $words = $this->service->extractWords('Одежда для детей');

        $this->assertSame(['одежда', 'детей'], $words);
    }

    public function test_short_query_segments_are_ignored(): void
    {
        $phrases = $this->service->buildPhrases('ab — Одежда для детей');

        $this->assertNotContains('ab', $phrases);
        $this->assertContains('Одежда для детей', $phrases);
    }

    public function test_extract_card_segments_splits_on_middot(): void
    {
        $segments = $this->service->extractCardSegments('ТВ и медиа · 512GB · Черный');

        $this->assertSame(['ТВ и медиа', '512GB', 'Черный'], $segments);
    }

    public function test_extract_structured_segments_splits_on_em_dash(): void
    {
        $segments = $this->service->extractStructuredSegments('Одежда для детей — Стандарт');

        $this->assertSame(['Одежда для детей', 'Стандарт'], $segments);
    }

    public function test_split_title_and_variant_specs(): void
    {
        $split = $this->service->splitTitleAndVariantSpecs('ТВ и медиа 512GB Черный');

        $this->assertNotNull($split);
        $this->assertSame('ТВ и медиа', $split['title']);
        $this->assertSame(['512GB', 'Черный'], $split['specs']);
    }

    public function test_split_title_and_tail(): void
    {
        $split = $this->service->splitTitleAndTail('ТВ и медиа Черный');

        $this->assertNotNull($split);
        $this->assertSame('ТВ и медиа', $split['title']);
        $this->assertSame('Черный', $split['tail']);
    }

    public function test_split_does_not_treat_title_words_as_variant_specs(): void
    {
        $this->assertNull($this->service->splitTitleAndVariantSpecs('Maybelline Lash Sensational Sky High'));
    }

    public function test_split_peels_volume_from_title(): void
    {
        $split = $this->service->splitTitleAndVariantSpecs('Maybelline Lash Sensational Sky High 30 мл');

        $this->assertNotNull($split);
        $this->assertSame('Maybelline Lash Sensational Sky High', $split['title']);
        $this->assertSame(['30 мл'], $split['specs']);
    }

    public function test_normalize_yo_to_e(): void
    {
        $words = $this->service->extractWords('Чёрный');

        $this->assertContains('черный', $words);
    }
}
