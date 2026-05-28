<?php

namespace App\Support;

/**
 * Источники фото для DemoCatalogSeeder:
 * 1) public/img/products/6|7/ — батчи {NNN}_timestamp_v*_g* или timestamp_v*_g* по порядку номера
 * 2) Опционально папки из config/catalog_seed_upload_folders.php
 * 3) Пустые слоты — донор из той же категории (без random/remix)
 */
class CatalogSeedImagePool
{
    private const MIN_REAL_FILE_BYTES = 512;

    private const IMAGE_EXT = 'webp';

    private const PRODUCTS_PER_CATEGORY_DEFAULT = 4;

    /** @var list<string> */
    private array $categorySlugsInSeedOrder = [];

    private int $productsPerCategory = self::PRODUCTS_PER_CATEGORY_DEFAULT;

    /** @var array<int, list<array{variants: list<array{main: string, extras: list<string>}>}>> */
    private array $allBatchesBySeller = [];

    /** @var array<string, array{variants: list<array{main: string, extras: list<string>}>}> */
    private array $productAssignments = [];

    /** @var array<int, array<string, list<array{variants: list<array{main: string, extras: list<string>}>}>>> */
    private array $categoryDonors = [];

    private bool $ready = false;

    /** @param  list<int>  $sellerIds */
    public function prepareSellers(array $sellerIds): void
    {
        foreach ($sellerIds as $sellerId) {
            $sellerId = (int) $sellerId;
            $this->deleteGeneratedSeedFiles($sellerId);
            $this->allBatchesBySeller[$sellerId] = $this->scanUploadedBatches($sellerId);
            $this->categoryDonors[$sellerId] = [];
        }
        $this->productAssignments = [];
        $this->ready = true;
    }

    /**
     * @param  list<string>  $categorySlugsInSeedOrder
     */
    public function registerSeedPlan(array $categorySlugsInSeedOrder, int $productsPerCategory = self::PRODUCTS_PER_CATEGORY_DEFAULT): void
    {
        $this->assertReady();

        $this->categorySlugsInSeedOrder = $categorySlugsInSeedOrder;
        $this->productsPerCategory = $productsPerCategory;

        $this->applyStagingFolderUploads();

        $sellerBatchIndex = [];

        $globalSeq = 0;
        foreach ($categorySlugsInSeedOrder as $slug) {
            for ($productIndex = 0; $productIndex < $productsPerCategory; $productIndex++) {
                $globalSeq++;
                $sellerId = ($globalSeq % 2 === 1) ? 6 : 7;
                $key = $this->planKey($sellerId, $slug, $productIndex);

                if (isset($this->productAssignments[$key])) {
                    continue;
                }

                $sellerBatchIndex[$sellerId] ??= 0;
                $batches = $this->allBatchesBySeller[$sellerId] ?? [];
                $idx = $sellerBatchIndex[$sellerId];

                if ($idx < count($batches)) {
                    $batch = $batches[$idx];
                    $this->productAssignments[$key] = $batch;
                    $this->categoryDonors[$sellerId][$slug][] = $batch;
                    $sellerBatchIndex[$sellerId]++;
                }
            }
        }
    }

    /**
     * @return list<array{main: string, extras: list<string>}>|null
     */
    public function resolveProductVariants(int $sellerId, string $categorySlug, int $productIndexInCategory): ?array
    {
        $this->assertReady();

        $sellerId = (int) $sellerId;
        $key = $this->planKey($sellerId, $categorySlug, $productIndexInCategory);

        if (isset($this->productAssignments[$key])) {
            return $this->productAssignments[$key]['variants'];
        }

        $donors = $this->categoryDonors[$sellerId][$categorySlug] ?? [];
        if ($donors === []) {
            $donors = [];
            foreach ($this->categoryDonors[$sellerId] ?? [] as $list) {
                $donors = array_merge($donors, $list);
            }
        }

        if ($donors === []) {
            $all = $this->allBatchesBySeller[$sellerId] ?? [];
            if ($all === []) {
                return null;
            }
            $offset = abs(crc32($categorySlug.'|'.$productIndexInCategory)) % count($all);

            return $all[$offset]['variants'];
        }

        $donorIndex = $productIndexInCategory % count($donors);

        return $donors[$donorIndex]['variants'];
    }

    private function applyStagingFolderUploads(): void
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = config('catalog_seed_upload_folders', []);

        foreach ($config as $folderName => $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $path = $this->resolveStagingFolderPath((string) $folderName);
            if ($path === null) {
                continue;
            }

            $this->applyFolderSpec($path, $spec);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function applyFolderSpec(string $folderPath, array $spec): void
    {
        $batches = $this->scanBatchesInDirectory($folderPath);
        if ($batches === []) {
            return;
        }

        $custom = $this->readFolderMappingFile($folderPath);
        if ($custom !== null) {
            foreach ($batches as $batchIndex => $batch) {
                if (! isset($custom[$batchIndex])) {
                    break;
                }
                [$categorySlug, $productIndex] = $custom[$batchIndex];
                $this->assignStagingBatch($categorySlug, $productIndex, $batch);
            }

            return;
        }

        if (isset($spec['targets']) && is_array($spec['targets'])) {
            $batchIndex = 0;
            foreach ($spec['targets'] as $target) {
                if (! is_array($target)) {
                    continue;
                }
                $categorySlug = (string) ($target['category'] ?? '');
                if ($categorySlug === '') {
                    continue;
                }
                $fromIndex = (int) ($target['from_index'] ?? 0);
                $limit = (int) ($target['limit'] ?? $this->productsPerCategory);

                for ($offset = 0; $offset < $limit && $batchIndex < count($batches); $offset++) {
                    $this->assignStagingBatch($categorySlug, $fromIndex + $offset, $batches[$batchIndex]);
                    $batchIndex++;
                }
            }

            return;
        }

        $categorySlug = (string) ($spec['category'] ?? '');
        if ($categorySlug === '') {
            return;
        }

        $fromIndex = (int) ($spec['from_index'] ?? 0);
        foreach ($batches as $batchIndex => $batch) {
            $productIndex = $fromIndex + $batchIndex;
            if ($productIndex >= $this->productsPerCategory) {
                break;
            }
            $this->assignStagingBatch($categorySlug, $productIndex, $batch);
        }
    }

    /**
     * @param  array{variants: list<array{main: string, extras: list<string>}>}  $batch
     */
    private function assignStagingBatch(string $categorySlug, int $productIndex, array $batch): void
    {
        if ($productIndex < 0 || $productIndex >= $this->productsPerCategory) {
            return;
        }

        $sellerId = $this->sellerIdForSlot($categorySlug, $productIndex);
        $key = $this->planKey($sellerId, $categorySlug, $productIndex);

        $this->productAssignments[$key] = $batch;
        $this->categoryDonors[$sellerId][$categorySlug][] = $batch;
    }

    private function sellerIdForSlot(string $categorySlug, int $productIndex): int
    {
        $globalSeq = 0;
        foreach ($this->categorySlugsInSeedOrder as $slug) {
            for ($pi = 0; $pi < $this->productsPerCategory; $pi++) {
                $globalSeq++;
                if ($slug === $categorySlug && $pi === $productIndex) {
                    return ($globalSeq % 2 === 1) ? 6 : 7;
                }
            }
        }

        return 6;
    }

  /**
     * @return list<array{0: string, 1: int}>|null
     */
    private function readFolderMappingFile(string $folderPath): ?array
    {
        $mappingFile = $folderPath.'/_mapping.txt';
        if (! is_file($mappingFile)) {
            return null;
        }

        $lines = file($mappingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $line = str_replace([':', "\t"], ' ', $line);
            $parts = preg_split('/\s+/', $line, 2);
            if (! is_array($parts) || count($parts) < 2) {
                continue;
            }
            $parsed[] = [$parts[0], (int) $parts[1]];
        }

        return $parsed === [] ? null : $parsed;
    }

    private function resolveStagingFolderPath(string $folderName): ?string
    {
        $root = public_path('img/products');
        $direct = $root.'/'.$folderName;
        if (is_dir($direct)) {
            return $direct;
        }

        $target = $this->normalizeFolderName($folderName);
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_dir($root.'/'.$entry)) {
                continue;
            }
            if ($entry === '6' || $entry === '7') {
                continue;
            }
            if ($this->normalizeFolderName($entry) === $target) {
                return $root.'/'.$entry;
            }
        }

        return null;
    }

    private function normalizeFolderName(string $name): string
    {
        $name = trim($name);
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);

            return is_string($normalized) ? $normalized : $name;
        }

        return $name;
    }

    private function planKey(int $sellerId, string $categorySlug, int $productIndexInCategory): string
    {
        return $sellerId.'|'.$categorySlug.'|'.$productIndexInCategory;
    }

    public function deleteGeneratedSeedFiles(int $sellerId): void
    {
        $dir = public_path("img/products/{$sellerId}");
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/img_*.*') ?: [] as $path) {
            $base = basename($path);
            if (preg_match('#^img_\d+(_\d+)?\.(png|webp)$#i', $base)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return list<array{variants: list<array{main: string, extras: list<string>}>}>
     */
    public function scanUploadedBatches(int $sellerId): array
    {
        $dir = public_path("img/products/{$sellerId}");
        if (! is_dir($dir)) {
            return [];
        }

        return $this->scanBatchesInDirectory($dir);
    }

    /**
     * @return list<array{variants: list<array{main: string, extras: list<string>}>}>
     */
    private function scanBatchesInDirectory(string $dir): array
    {
        /** @var array<string, array{sort: int, variants: array<int, array<int, string>>}> $grouped */
        $grouped = [];

        foreach (glob($dir.'/*.webp') ?: [] as $absolute) {
            if (! is_file($absolute) || filesize($absolute) < self::MIN_REAL_FILE_BYTES) {
                continue;
            }

            $base = basename($absolute);
            if (! preg_match('#^(?:(\d{1,4})_)?(\d+)_v(\d+)_g(\d+)_[A-Za-z0-9]+\.webp$#i', $base, $m)) {
                continue;
            }

            $slot = $m[1] !== '' ? (int) $m[1] : null;
            $timestamp = $m[2];
            $variantIndex = (int) $m[3];
            $galleryIndex = (int) $m[4];

            $batchKey = $slot !== null ? 'slot:'.$slot : 'ts:'.$timestamp;
            $sort = $slot ?? (int) $timestamp;

            if (! isset($grouped[$batchKey])) {
                $grouped[$batchKey] = ['sort' => $sort, 'variants' => []];
            }

            $grouped[$batchKey]['variants'][$variantIndex][$galleryIndex] = $absolute;
        }

        uasort($grouped, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        $batches = [];

        foreach ($grouped as $entry) {
            $variantsRaw = $entry['variants'];
            ksort($variantsRaw);
            $variants = [];

            foreach ($variantsRaw as $galleryRaw) {
                ksort($galleryRaw);
                $paths = array_values($galleryRaw);
                if ($paths === []) {
                    continue;
                }

                $variants[] = [
                    'main' => $paths[0],
                    'extras' => array_values(array_slice($paths, 1)),
                ];
            }

            if ($variants !== []) {
                $batches[] = ['variants' => $variants];
            }
        }

        return $batches;
    }

    /**
     * @param  array{main: string, extras: list<string>}  $sources
     * @return list<array{url: string, sort_order: int, is_main: bool}>
     */
    public function materializeVariantImages(int $sellerId, int $variantId, array $sources): array
    {
        $dir = public_path("img/products/{$sellerId}");
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $allSources = array_values(array_unique(array_merge(
            [$sources['main']],
            $sources['extras'] ?? []
        )));

        $imageCount = min(max(3, count($allSources)), 4);
        $mainSource = $sources['main'];
        $extraSources = array_values(array_filter($allSources, fn ($p) => $p !== $mainSource));

        $picked = [$mainSource];
        foreach ($extraSources as $extra) {
            if (count($picked) >= $imageCount) {
                break;
            }
            $picked[] = $extra;
        }

        while (count($picked) < $imageCount && $allSources !== []) {
            $picked[] = $allSources[count($picked) % count($allSources)];
        }

        $picked = array_values(array_unique($picked));
        $picked = array_slice($picked, 0, $imageCount);

        $rows = [];

        foreach ($picked as $imageIndex => $sourcePath) {
            if (! is_file($sourcePath)) {
                continue;
            }

            $fileName = $imageIndex === 0
                ? 'img_'.$variantId.'.'.self::IMAGE_EXT
                : 'img_'.$variantId.'_'.$imageIndex.'.'.self::IMAGE_EXT;

            $target = $dir.'/'.$fileName;
            if (! @copy($sourcePath, $target)) {
                continue;
            }

            $rows[] = [
                'url' => '/img/products/'.$sellerId.'/'.$fileName,
                'sort_order' => $imageIndex,
                'is_main' => $imageIndex === 0,
            ];
        }

        if ($rows === []) {
            $rows[] = $this->createMinimalPlaceholder($sellerId, $variantId);
        }

        return $rows;
    }

    /** @return array{url: string, sort_order: int, is_main: bool} */
    private function createMinimalPlaceholder(int $sellerId, int $variantId): array
    {
        $dir = public_path("img/products/{$sellerId}");
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fileName = 'img_'.$variantId.'.'.self::IMAGE_EXT;
        $target = $dir.'/'.$fileName;

        if (! is_file($target)) {
            $png1x1 = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8Bf8QAAAAASUVORK5CYII=',
                true
            );
            file_put_contents($target, $png1x1 !== false ? $png1x1 : '');
        }

        return [
            'url' => '/img/products/'.$sellerId.'/'.$fileName,
            'sort_order' => 0,
            'is_main' => true,
        ];
    }

    private function assertReady(): void
    {
        if (! $this->ready) {
            throw new \RuntimeException('CatalogSeedImagePool::prepareSellers() must be called first.');
        }
    }
}
