<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 25);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->text('description')->nullable(); // 175 символов
            $table->string('avatar', 255)->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('phone', 15)->nullable(); 
            $table->boolean('newPassw')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken(); 
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
        DB::table('users')->insert([
            'id' => '1',
            'name' => 'Администратор',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin'),
            'role' => 'admin',
            'balance' => 0,
            'phone' => 79959460905,
            'description' => 'Taken Lorem Ipsum to the next level with our HTML-Ipsum tool. As you can see, this Lorem Ipsum is tailor-made for websites and other online projects that are based in HTML. Most web design projects use Lorem Ipsum excerpts to begin with, but you always have to spend an annoying extra minute or two formatting it properly for the web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id' => '2',
            'name' => 'Пользователь',
            'email' => 'user@gmail.com',
            'password' => Hash::make('user'),
            'role' => 'user',
            'balance' => 100000,
            'phone' => 79648111105,
            'description' => 'Taken Lorem Ipsum to the next level with our HTML-Ipsum tool. As you can see, this Lorem Ipsum is tailor-made for websites and other online projects that are based in HTML. Most web design projects use Lorem Ipsum excerpts to begin with, but you always have to spend an annoying extra minute or two formatting it properly for the web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id' => '3',
            'name' => 'Пользователь Два',
            'email' => 'user2@gmail.com',
            'password' => Hash::make('user'),
            'role' => 'user',
            'balance' => 100000,
            'phone' => 79999999999,
            'description' => 'Taken Lorem Ipsum to the next level with our HTML-Ipsum tool. As you can see, this Lorem Ipsum is tailor-made for websites and other online projects that are based in HTML. Most web design projects use Lorem Ipsum excerpts to begin with, but you always have to spend an annoying extra minute or two formatting it properly for the web',
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
