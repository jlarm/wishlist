<?php

namespace App\Console\Commands;

use App\Models\Occasion;
use App\Models\User;
use App\Notifications\UpcomingOccasionsReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

#[Signature('occasions:remind')]
#[Description('Email gift-givers a digest of occasions coming up in 30 or 7 days.')]
class RemindUpcomingOccasions extends Command
{
    /**
     * How many days ahead of an occasion the group is reminded.
     *
     * @var list<int>
     */
    public const REMIND_DAYS_BEFORE = [30, 7];

    /**
     * Send each active member one digest covering everyone else's occasions
     * that hit a reminder threshold today. Owners are never reminded of their
     * own occasions, and the unclaimed counts never reach them.
     */
    public function handle(): int
    {
        $today = Date::now()->startOfDay();

        $upcoming = Occasion::query()
            ->whereHas('user', fn (Builder $query) => $query->whereNull('disabled_at'))
            ->with('user')
            ->get()
            ->map(function (Occasion $occasion) use ($today): ?array {
                $next = $occasion->nextOccurrence($today);
                $daysUntil = $next === null ? null : (int) $today->diffInDays($next);

                if (! in_array($daysUntil, self::REMIND_DAYS_BEFORE, true)) {
                    return null;
                }

                $items = $occasion->user->wishlistItems()->visible()->active();

                return [
                    'owner_id' => $occasion->user_id,
                    'owner_name' => $occasion->user->name,
                    'occasion' => $occasion->name,
                    'days_until' => $daysUntil,
                    'unclaimed' => (clone $items)->doesntHave('purchase')->count(),
                    'total' => $items->count(),
                ];
            })
            ->filter()
            ->values();

        if ($upcoming->isEmpty()) {
            $this->info('No occasions to remind about today.');

            return self::SUCCESS;
        }

        $sent = 0;

        User::query()
            ->whereNull('disabled_at')
            ->each(function (User $recipient) use ($upcoming, &$sent): void {
                $theirs = array_values($upcoming
                    ->reject(fn (array $occasion): bool => $occasion['owner_id'] === $recipient->id)
                    ->all());

                if ($theirs !== []) {
                    $recipient->notify(new UpcomingOccasionsReminder($theirs));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} occasion reminder(s).");

        return self::SUCCESS;
    }
}
