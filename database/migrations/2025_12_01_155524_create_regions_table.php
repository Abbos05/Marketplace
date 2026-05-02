<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedTinyInteger('delivery_hours')->default(24);
            $table->softDeletes();
            $table->timestamps();
        });
    
        // Тестовые данные
        DB::table('regions')->insert([
            ['name' => 'Иркутск', 'delivery_hours' => 2],
            ['name' => 'Ангарск', 'delivery_hours' => 4],
            ['name' => 'Братск', 'delivery_hours' => 8],
            ['name' => 'Шелехов', 'delivery_hours' => 3],
            ['name' => 'Усолье-Сибирское', 'delivery_hours' => 6],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
