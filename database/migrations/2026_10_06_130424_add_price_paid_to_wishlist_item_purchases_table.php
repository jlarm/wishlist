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
            // What the giver actually paid, when they told us. Null falls back
            // to the item's listed price.
            $table->decimal('price_paid', 10, 2)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            $table->dropColumn('price_paid');
        });
    }
};
