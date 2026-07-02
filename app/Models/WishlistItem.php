<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\VisibilityStatus;
use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $url
 * @property string|null $image_url
 * @property string|null $price
 * @property string|null $target_price
 * @property string|null $size
 * @property string|null $color
 * @property Priority $priority
 * @property string|null $notes
 * @property VisibilityStatus $visibility_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'url',
        'image_url',
        'price',
        'target_price',
        'size',
        'color',
        'priority',
        'notes',
        'visibility_status',
    ];

    /**
     * The model's default attribute values, mirroring the migration defaults so
     * an unsaved instance already reflects them.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'priority' => Priority::Medium->value,
        'visibility_status' => VisibilityStatus::Visible->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'target_price' => 'decimal:2',
            'priority' => Priority::class,
            'visibility_status' => VisibilityStatus::class,
        ];
    }

    /**
     * The user who owns the wishlist item.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The purchase record for the wishlist item.
     *
     * IMPORTANT: Never expose this relationship to the item owner. Purchase data
     * must only be serialized for users who do not own the item.
     *
     * @return HasOne<WishlistItemPurchase, $this>
     */
    public function purchase(): HasOne
    {
        return $this->hasOne(WishlistItemPurchase::class);
    }

    /**
     * The recorded price history for the item, oldest first.
     *
     * @return HasMany<WishlistItemPriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(WishlistItemPriceHistory::class)->orderBy('recorded_at');
    }

    /**
     * Scope a query to only visible items.
     *
     * @param  Builder<WishlistItem>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('visibility_status', VisibilityStatus::Visible);
    }
}
