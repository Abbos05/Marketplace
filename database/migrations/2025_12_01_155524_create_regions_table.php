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

        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('address', 500);
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('closure_status', 20)->default('none');
            $table->timestamp('closure_requested_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->text('closure_admin_reject_reason')->nullable();
            $table->timestamp('closure_admin_rejected_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('pickup_point_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained('pickup_points')->nullOnDelete();
            $table->enum('type', ['join', 'open']);
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('inn', 12)->nullable();
            $table->enum('org_type', ['ip', 'ooo', 'self'])->nullable();
            $table->string('legal_name', 200)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('proposed_title', 120)->nullable();
            $table->string('proposed_address', 500)->nullable();
            $table->foreignId('proposed_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->json('working_hours')->nullable();
            $table->string('premises_info', 300)->nullable();
            $table->text('application_comment')->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        DB::statement('CREATE UNIQUE INDEX pickup_point_staff_approved_point ON pickup_point_staff (pickup_point_id, status)');

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_pickup_point_id')
                ->nullable()
                ->after('is_blocked')
                ->constrained('pickup_points')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_pickup_point_id']);
        });
        Schema::dropIfExists('pickup_point_staff');
        Schema::dropIfExists('pickup_points');
        Schema::dropIfExists('regions');
    }
};
