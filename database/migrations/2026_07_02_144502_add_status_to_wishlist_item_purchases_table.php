<?php

use App\Enums\PurchaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            $table->string('status')->default(PurchaseStatus::Reserved->value)->after('purchased_by_user_id');
        });

        // Existing records predate the reserve step and represent committed
        // claims, so treat them as bought.
        DB::table('wishlist_item_purchases')->update(['status' => PurchaseStatus::Purchased->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
