<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 100)->unique();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('password', 255);
            $table->string('name', 80);
            // Исправлено: default значение теперь есть в списке ENUM
            $table->enum('role', ['user', 'seller', 'admin', 'moderator'])->default('user');
            $table->string('avatar', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('newPassw')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('role');
            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Исправлены поля и данные для вставки
        DB::table('users')->insert([
            'id' => 1,
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin'),
            'name' => 'Администратор',
            'role' => 'admin',
            'phone' => '79959460905',
            'avatar' => null,
            'is_active' => true,
            'newPassw' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 2,
            'email' => 'user@gmail.com',
            'password' => Hash::make('user'),
            'name' => 'Пользователь',
            'role' => 'seller',
            'phone' => '79648111105',
            'avatar' => null,
            'is_active' => true,
            'newPassw' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 3,
            'email' => 'user2@gmail.com',
            'password' => Hash::make('user'),
            'name' => 'Пользователь Два',
            'role' => 'seller',
            'phone' => '79999999999',
            'avatar' => null,
            'is_active' => true,
            'newPassw' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
