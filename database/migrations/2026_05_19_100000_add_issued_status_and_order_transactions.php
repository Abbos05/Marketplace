<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE orders MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT "NEW"');
            }

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('NEW','INTRANSIT','DELIVERED','ISSUED','CANCELED','REFUSED') NOT NULL DEFAULT 'NEW'");
            }
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'order_id')) {
                    $table->foreignId('order_id')->nullable()->after('id')->constrained('orders')->nullOnDelete();
                }
                if (! Schema::hasColumn('transactions', 'type')) {
                    $table->string('type', 32)->default('payment')->after('order_id');
                }
                if (! Schema::hasColumn('transactions', 'description')) {
                    $table->string('description', 500)->nullable()->after('amount');
                }
            });

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            DB::table('orders')->where('status', 'ISSUED')->update(['status' => 'DELIVERED']);
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('transactions', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('transactions', 'order_id')) {
                    $table->dropConstrainedForeignId('order_id');
                }
            });
        }
    }
};
