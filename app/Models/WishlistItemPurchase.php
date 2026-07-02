<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Database\Factories\WishlistItemPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wishlist_item_id
 * @property int $purchased_by_user_id
 * @property PurchaseStatus $status
 * @property Carbon $purchased_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WishlistItemPurchase extends Model
{
    /** @use HasFactory<WishlistItemPurchaseFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wishlist_item_id',
        'purchased_by_user_id',
        'status',
        'purchased_at',
        'note',
    ];

    /**
     * The model's default attribute values, mirroring the migration default.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => PurchaseStatus::Reserved->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'purchased_at' => 'datetime',
        ];
    }

    /**
     * The wishlist item that was purchased.
     *
     * @return BelongsTo<WishlistItem, $this>
     */
    public function wishlistItem(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class);
    }

    /**
     * The user who marked the item as purchased.
     *
     * @return BelongsTo<User, $this>
     */
    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by_user_id');
    }
}
