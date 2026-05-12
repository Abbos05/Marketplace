<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attribute_id')
                ->constrained('category_attributes')
                ->cascadeOnDelete();

            $table->text('value')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
