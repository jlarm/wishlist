<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite does not auto-index foreign-key columns, so add indexes on the
     * columns we look up by (User::invitations / User::purchases).
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->index('invited_by_user_id');
        });

        Schema::table('wishlist_item_purchases', function (Blueprint $table): void {
            $table->index('purchased_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropIndex(['invited_by_user_id']);
        });

        Schema::table('wishlist_item_purchases', function (Blueprint $table): void {
            $table->dropIndex(['purchased_by_user_id']);
        });
    }
};
