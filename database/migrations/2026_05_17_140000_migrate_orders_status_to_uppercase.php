<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Ранее: нормализация snake_case → верхний регистр.
 * Сейчас: логика объединена в миграции
 * `2026_05_18_000000_consolidate_order_status_to_delivery_flow.php`.
 * Файл сохранён, чтобы не ломать уже применённые записи в таблице `migrations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — см. 2026_05_18_000000_consolidate_order_status_to_delivery_flow
    }

    public function down(): void
    {
    }
};
