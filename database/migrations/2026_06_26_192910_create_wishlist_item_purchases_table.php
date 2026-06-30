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
        Schema::create('wishlist_item_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('purchased_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('purchased_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_item_purchases');
    }
};
