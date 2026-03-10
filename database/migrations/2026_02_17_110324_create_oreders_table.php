<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Customer who placed order
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Restaurant receiving order
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');

            // Financials
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2);

            // Delivery Info
            $table->string('delivery_address');
            $table->string('phone');

            // Order Status
            $table->enum('status', [
                'pending',
                'confirmed',
                'preparing',
                'on_the_way',
                'delivered',
                'cancelled'
            ])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
