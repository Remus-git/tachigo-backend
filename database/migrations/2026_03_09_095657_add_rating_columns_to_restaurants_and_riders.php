<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('restaurants', 'rating')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->decimal('rating', 3, 2)->default(0)->after('delivery_radius');
                $table->unsignedInteger('rating_count')->default(0)->after('rating');
            });
        }
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_count']);
        });
    }
};
