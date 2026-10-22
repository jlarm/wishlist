<?php

namespace App\Models;

use App\Enums\Carrier;
use App\Enums\PurchaseStatus;
use App\Enums\TrackingStatus;
use Carbon\CarbonImmutable;
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
 * @property string|null $price_paid
 * @property string|null $tracking_number
 * @property Carrier|null $carrier
 * @property string|null $tracker_id
 * @property TrackingStatus|null $tracking_status
 * @property string|null $tracking_detail
 * @property CarbonImmutable|null $tracking_estimated_delivery_at
 * @property CarbonImmutable|null $tracking_checked_at
 * @property Carbon $purchased_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $reminded_at
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
        'price_paid',
        'tracking_number',
        'carrier',
        'tracker_id',
        'tracking_status',
        'tracking_detail',
        'tracking_estimated_delivery_at',
        'tracking_checked_at',
        'purchased_at',
        'confirmed_at',
        'reminded_at',
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
            'price_paid' => 'decimal:2',
            'carrier' => Carrier::class,
            'tracking_status' => TrackingStatus::class,
            'tracking_estimated_delivery_at' => 'date',
            'tracking_checked_at' => 'datetime',
            'purchased_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * The carrier's public tracking page, or null without a tracking number or
     * a carrier we can link to.
     */
    public function trackingUrl(): ?string
    {
        if ($this->tracking_number === null) {
            return null;
        }

        return $this->carrier?->trackingUrl($this->tracking_number);
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
