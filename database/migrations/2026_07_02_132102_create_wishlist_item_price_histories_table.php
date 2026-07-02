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
        Schema::create('wishlist_item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            // The chart reads the latest points for one item, oldest-to-newest.
            $table->index(['wishlist_item_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_item_price_histories');
    }
};
