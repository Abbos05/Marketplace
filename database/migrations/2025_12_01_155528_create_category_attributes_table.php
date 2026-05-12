<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
  Schema::create('category_attributes', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('name');

    // text, number, select, boolean
    $table->enum('type', [
        'text',
        'number',
        'select',
        'boolean'
    ]);

    // Для select
    $table->json('options')->nullable();

    $table->boolean('required')->default(false);

    $table->timestamps();
});
    }

    public function down()
    {
        Schema::dropIfExists('category_attributes');
    }
};
