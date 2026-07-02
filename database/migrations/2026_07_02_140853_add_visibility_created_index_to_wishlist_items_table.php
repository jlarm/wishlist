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
        Schema::table('wishlist_items', function (Blueprint $table) {
            // Serves the dashboard's cross-user "recent visible items" query
            // (WHERE visibility_status = ? ORDER BY created_at DESC LIMIT n).
            $table->index(['visibility_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropIndex(['visibility_status', 'created_at']);
        });
    }
};
