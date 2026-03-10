<?php
// ─────────────────────────────────────────────────────────────────────────────
// Run: php artisan make:migration create_riders_table
// Then replace the up() content with this
// ─────────────────────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MIGRATION 1: create_riders_table
return new class extends Migration {
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('vehicle_type')->default('motorcycle'); // motorcycle, bicycle, car
            $table->string('vehicle_plate')->nullable();
            $table->string('id_card_number')->nullable();
            $table->string('profile_image')->nullable();

            // Approval
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->timestamp('approved_at')->nullable();

            // Online/availability
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(true); // false when on a delivery
            $table->timestamp('last_seen_at')->nullable();

            // Current location (updated every few seconds when online)
            $table->decimal('current_latitude',  10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();

            // Earnings
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->decimal('rating', 3, 2)->default(5.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
