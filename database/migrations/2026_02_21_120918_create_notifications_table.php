<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('system'); // system, order_update, promo
            $table->json('data')->nullable(); // extra payload
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Add lat/lng columns to restaurants table if not exist
        Schema::table('restaurants', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurants', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
