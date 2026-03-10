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
        Schema::create('ratings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->unique()->constrained()->onDelete('cascade');
    $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('shop_id')->constrained('restaurants')->onDelete('cascade');
    $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
    $table->unsignedTinyInteger('shop_rating');   // 1–5
    $table->unsignedTinyInteger('rider_rating');  // 1–5
    $table->text('comment')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
