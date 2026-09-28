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
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            // When the claimer last said "yes, I'm still getting this".
            $table->timestamp('confirmed_at')->nullable()->after('purchased_at');
            // When we last nudged them about a stale reservation.
            $table->timestamp('reminded_at')->nullable()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'reminded_at']);
        });
    }
};
