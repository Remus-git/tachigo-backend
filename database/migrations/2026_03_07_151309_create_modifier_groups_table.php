<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->string('name');                     // e.g. "Choose Soup Base", "Toppings"
            $table->boolean('required')->default(false);// must the customer pick at least one?
            $table->integer('min_selections')->default(0); // minimum picks (0 = optional)
            $table->integer('max_selections')->default(1); // maximum picks (1 = single choice)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
