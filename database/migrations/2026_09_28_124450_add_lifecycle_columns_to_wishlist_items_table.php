<?php

use App\Enums\Priority;
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
        Schema::table('wishlist_items', function (Blueprint $table) {
            // The owner's hand-picked ranking (lower comes first).
            $table->unsignedInteger('position')->default(0)->after('priority');

            // Archive: the owner has the gift, and may tick off a thank-you.
            $table->timestamp('received_at')->nullable()->after('visibility_status');
            $table->timestamp('thanked_at')->nullable()->after('received_at');

            // Result of the nightly link check.
            $table->string('availability')->nullable()->after('thanked_at');
            $table->unsignedTinyInteger('link_failures')->default(0)->after('availability');
            $table->timestamp('availability_checked_at')->nullable()->after('link_failures');

            $table->index(['user_id', 'received_at', 'position']);
        });

        // Seed the ranking from the old default sort (priority, then newest) so
        // existing lists look exactly as they did before.
        $weights = array_combine(
            array_map(fn (Priority $priority): string => $priority->value, Priority::cases()),
            array_map(fn (Priority $priority): int => $priority->weight(), Priority::cases()),
        );

        DB::table('wishlist_items')
            ->orderBy('user_id')
            ->get(['id', 'user_id', 'priority', 'created_at'])
            ->groupBy('user_id')
            ->each(function ($items) use ($weights): void {
                $items
                    ->sort(fn ($a, $b): int => [$weights[$b->priority] ?? 0, $b->created_at] <=> [$weights[$a->priority] ?? 0, $a->created_at])
                    ->values()
                    ->each(fn ($item, int $index) => DB::table('wishlist_items')
                        ->where('id', $item->id)
                        ->update(['position' => $index + 1]));
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'received_at', 'position']);
            $table->dropColumn([
                'position',
                'received_at',
                'thanked_at',
                'availability',
                'link_failures',
                'availability_checked_at',
            ]);
        });
    }
};
