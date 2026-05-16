<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Для баз, где уже выполнялась старая версия 2026_05_17_*: повторно приводит status к модели доставки.
 * Идемпотентна: безопасно запускать повторно.
 */
return new class extends Migration
{
    /** @return array<string, string> */
    private function canonicalMap(): array
    {
        return [
            'new' => 'NEW',
            'paid' => 'NEW',
            'processing' => 'NEW',
            'ready_for_pickup' => 'INTRANSIT',
            'in_transit' => 'INTRANSIT',
            'at_pvz' => 'INTRANSIT',
            'issued' => 'DELIVERED',
            'canceled' => 'CANCELED',
            'returned' => 'REFUSED',
            'NEW' => 'NEW',
            'PAID' => 'NEW',
            'PROCESSING' => 'NEW',
            'READYPICKUP' => 'INTRANSIT',
            'INTRANSIT' => 'INTRANSIT',
            'ATPVZ' => 'INTRANSIT',
            'ISSUED' => 'DELIVERED',
            'CANCELED' => 'CANCELED',
            'RETURNED' => 'REFUSED',
            'DELIVERED' => 'DELIVERED',
            'REFUSED' => 'REFUSED',
        ];
    }

    private function allowedEnumList(): string
    {
        return "'NEW','INTRANSIT','DELIVERED','CANCELED','REFUSED'";
    }

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT "NEW"');
        }

        foreach ($this->canonicalMap() as $from => $to) {
            if ($from === $to) {
                continue;
            }
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
        }

        DB::table('orders')->whereNotIn('status', ['NEW', 'INTRANSIT', 'DELIVERED', 'CANCELED', 'REFUSED'])
            ->update(['status' => 'NEW']);

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY COLUMN status ENUM('.$this->allowedEnumList().") NOT NULL DEFAULT 'NEW'");
        }
    }

    public function down(): void
    {
    }
};
