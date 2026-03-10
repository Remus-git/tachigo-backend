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
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone')->nullable()->after('email');
        $table->timestamp('phone_verified_at')->nullable()->after('phone');
        $table->string('gender')->nullable()->after('phone_verified_at');
        $table->date('date_of_birth')->nullable()->after('gender');
        $table->string('profile_photo_url')->nullable()->after('date_of_birth');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'phone', 'phone_verified_at', 'gender',
            'date_of_birth', 'profile_photo_url'
        ]);
    });
}
};
