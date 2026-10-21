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
            // An item an admin put on someone else's list. It stays hidden from
            // that person even if the admin who added it is later deleted,
            // which is why this is a flag of its own rather than inferred from
            // added_by_user_id.
            $table->boolean('is_surprise')->default(false)->after('visibility_status');
            $table->foreignId('added_by_user_id')->nullable()->after('is_surprise')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by_user_id');
            $table->dropColumn('is_surprise');
        });
    }
};
