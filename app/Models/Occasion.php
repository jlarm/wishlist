<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\OccasionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property CarbonImmutable $date
 * @property bool $recurs_annually
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Occasion extends Model
{
    /** @use HasFactory<OccasionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'date',
        'recurs_annually',
    ];

    /**
     * The model's default attribute values, mirroring the migration default.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'recurs_annually' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'recurs_annually' => 'boolean',
        ];
    }

    /**
     * The user whose occasion this is.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The next date this occasion falls on, counting today, or null once a
     * one-off occasion has passed.
     *
     * A yearly Feb 29 occasion lands on Feb 28 in non-leap years.
     */
    public function nextOccurrence(CarbonInterface $today): ?CarbonImmutable
    {
        $today = CarbonImmutable::instance($today)->startOfDay();

        if (! $this->recurs_annually) {
            return $this->date->lessThan($today) ? null : $this->date;
        }

        $candidate = $this->onYear($today->year);

        return $candidate->lessThan($today) ? $this->onYear($today->year + 1) : $candidate;
    }

    /**
     * This occasion's month and day in the given year, clamped to the month end.
     */
    private function onYear(int $year): CarbonImmutable
    {
        $monthStart = CarbonImmutable::create($year, $this->date->month, 1);

        return $monthStart->setDay(min($this->date->day, $monthStart->daysInMonth));
    }
}
