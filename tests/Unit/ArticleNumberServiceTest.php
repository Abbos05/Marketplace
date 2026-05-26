<?php

namespace Tests\Unit;

use App\Services\ArticleNumberService;
use Tests\TestCase;

class ArticleNumberServiceTest extends TestCase
{
    private ArticleNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.article.prefix' => '000']);
        $this->service = new ArticleNumberService;
    }

    public function test_format_builds_prefix_and_variant_id(): void
    {
        $this->assertSame('0001', $this->service->format(1));
        $this->assertSame('00042', $this->service->format(42));
        $this->assertSame('0001234', $this->service->format(1234));
    }

    public function test_normalize_strips_non_digits(): void
    {
        $this->assertSame('00042', $this->service->normalize(' 000-42 '));
        $this->assertSame('00042', $this->service->normalize('00042'));
    }

    public function test_parse_returns_variant_id_from_article(): void
    {
        $this->assertSame(42, $this->service->parse('00042'));
        $this->assertSame(42, $this->service->parse('000-42'));
        $this->assertNull($this->service->parse('abc'));
    }

    public function test_is_article_query_detects_prefix(): void
    {
        $this->assertTrue($this->service->isArticleQuery('00015'));
        $this->assertFalse($this->service->isArticleQuery('phone'));
        $this->assertFalse($this->service->isArticleQuery('000'));
    }

    public function test_strict_article_query_requires_digits_only(): void
    {
        $this->assertTrue($this->service->isStrictArticleQuery('00042'));
        $this->assertFalse($this->service->isStrictArticleQuery('А00042'));
        $this->assertFalse($this->service->isStrictArticleQuery('000'));
        $this->assertFalse($this->service->isStrictArticleQuery('art 00042'));
    }

    public function test_split_separates_prefix_and_suffix(): void
    {
        $this->assertSame(['prefix' => '000', 'id' => '42'], $this->service->split('00042'));
    }
}
