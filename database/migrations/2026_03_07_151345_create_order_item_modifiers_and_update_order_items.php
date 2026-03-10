<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('order_item_modifiers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_item_id');
        $table->unsignedBigInteger('modifier_option_id');
        $table->string('modifier_group_name');
        $table->string('modifier_option_name');
        $table->decimal('additional_price', 10, 2)->default(0);
        $table->timestamps();

        // Foreign keys defined after both tables exist
        $table->foreign('order_item_id')
              ->references('id')->on('order_items')->onDelete('cascade');
        $table->foreign('modifier_option_id')
              ->references('id')->on('modifier_options')->onDelete('cascade');
    });

    Schema::table('order_items', function (Blueprint $table) {
        if (!Schema::hasColumn('order_items', 'status')) {
            $table->string('status')->default('pending')->after('price');
        }
        if (!Schema::hasColumn('order_items', 'reject_reason')) {
            $table->string('reject_reason')->nullable()->after('status');
        }
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'reject_reason']);
        });
    }
};
