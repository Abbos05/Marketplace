<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('refused_by_user_id')->nullable()->after('issued_at')->constrained('users')->nullOnDelete();
            $table->timestamp('refused_at')->nullable()->after('refused_by_user_id');
        });

        DB::table('orders')
            ->where('status', 'REFUSED')
            ->whereNotNull('issued_by_user_id')
            ->update([
                'refused_by_user_id' => DB::raw('issued_by_user_id'),
                'refused_at' => DB::raw('issued_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refused_by_user_id');
            $table->dropColumn('refused_at');
        });
    }
};
