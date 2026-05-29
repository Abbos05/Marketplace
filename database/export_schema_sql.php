<?php

/**
 * Export schema SQL safe for MySQL Workbench import.
 * Strips CHECK (json_valid(...)) etc. — Workbench 8.0.x crashes on those (ColumnDefinitionListener SIGSEGV).
 *
 * Usage: php database/export_schema_sql.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outDir = __DIR__ . '/schema';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$rawPath = $outDir . '/schema_raw.sql';
$wbPath = $outDir . '/workbench_import.sql';

$sql = "-- ALVORA schema dump\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

$tables = collect(DB::select('SHOW TABLES'))
    ->map(fn ($row) => array_values((array) $row)[0])
    ->sort()
    ->values();

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $row = DB::selectOne("SHOW CREATE TABLE {$quoted}");
    $ddl = $row->{'Create Table'} ?? '';
    $sql .= "DROP TABLE IF EXISTS {$quoted};\n{$ddl};\n\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
file_put_contents($rawPath, $sql);

$workbenchSql = preg_replace(
    '/\s+CHECK\s+\(json_valid\(`[^`]+`\)\)/i',
    '',
    $sql
);
$workbenchSql = preg_replace(
    '/\s+CHECK\s+\([^)]*\)/i',
    '',
    $workbenchSql
);

file_put_contents($wbPath, $workbenchSql);

echo "Wrote raw dump: {$rawPath}\n";
echo "Wrote Workbench-safe dump: {$wbPath}\n";
