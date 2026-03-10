<?php
// ─────────────────────────────────────────────────────────────────────────────
// Run: php artisan make:migration add_rider_to_orders_table
// ─────────────────────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->timestamp('rider_accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('rider_earnings', 8, 2)->nullable(); // ฿10/km calculated on accept
            $table->decimal('delivery_distance_km', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['rider_id']);
            $table->dropColumn(['rider_id', 'rider_accepted_at', 'picked_up_at', 'delivered_at', 'rider_earnings', 'delivery_distance_km']);
        });
    }
};
