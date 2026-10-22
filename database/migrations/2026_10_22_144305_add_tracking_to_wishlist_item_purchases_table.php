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
            // An optional tracking number for a bought gift, and the carrier it
            // ships with (detected from the number unless the giver picks one).
            $table->string('tracking_number', 64)->nullable()->after('price_paid');
            $table->string('carrier', 20)->nullable()->after('tracking_number');

            // The latest shipment status pulled from the tracking provider.
            $table->string('tracker_id')->nullable()->after('carrier');
            $table->string('tracking_status', 20)->nullable()->after('tracker_id');
            $table->string('tracking_detail')->nullable()->after('tracking_status');
            $table->date('tracking_estimated_delivery_at')->nullable()->after('tracking_detail');
            $table->timestamp('tracking_checked_at')->nullable()->after('tracking_estimated_delivery_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_item_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_number',
                'carrier',
                'tracker_id',
                'tracking_status',
                'tracking_detail',
                'tracking_estimated_delivery_at',
                'tracking_checked_at',
            ]);
        });
    }
};
