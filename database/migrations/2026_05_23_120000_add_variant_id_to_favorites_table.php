<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->index('user_id', 'favorites_user_id_fk_index');
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product_id']);
            $table->unique(['user_id', 'product_id', 'variant_id'], 'favorites_user_product_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique('favorites_user_product_variant_unique');
            $table->dropConstrainedForeignId('variant_id');
            $table->unique(['user_id', 'product_id']);
            $table->dropIndex('favorites_user_id_fk_index');
        });
    }
};
