<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nfts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('description');
            $table->string('image', 255);
            $table->decimal('price', 12, 2);
            $table->decimal('previous_price', 12, 2)->default(0);
            $table->string('tags')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('status', ['rejection','moderation', 'relevant', 'sold']);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Сначала восстановить связь (если таблица существует)
        Schema::table('nfts', function (Blueprint $table) {
        
            Schema::table('nfts', function (Blueprint $table) {
                $table->dropForeign(['user_id']); // Удаляем ограничение
            });
    
            Schema::table('nfts', function (Blueprint $table) {
                // Для изменения столбца с внешним ключом его нужно сначала удалить
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
    
            // Теперь добавляем обратно с каскадом
            Schema::table('nfts', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
            });
        });
    
        Schema::dropIfExists('nfts');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users'); // Удаляем users последним, так как на него ссылаются другие таблицы
    }
};
