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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();

    $table->foreignId('user_id')->constrained()->onDelete('cascade');

    $table->string('name');
    $table->text('description')->nullable();

    $table->decimal('delivery_fee', 8, 2)->default(0);
    $table->decimal('minimum_order_amount', 8, 2)->default(0);

    $table->boolean('is_open')->default(true);
    $table->boolean('is_approved')->default(false);

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
