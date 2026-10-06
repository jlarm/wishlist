<?php

namespace App\Http\Resources;

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WishlistItem
 *
 * Privacy boundary: this resource is the single place wishlist items become
 * Inertia props. Purchase data is ONLY ever added for admins viewing someone
 * else's item. Owners and non-admin members never get a purchase key (not even
 * for their own claims), so claim status cannot leak through props, JSON, or
 * page source.
 */
class WishlistItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $isOwner = $viewer !== null && $viewer->id === $this->user_id;

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'owner_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'size' => $this->size,
            'color' => $this->color,
            'tags' => $this->tags,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_weight' => $this->priority->weight(),
            'notes' => $this->notes,
            'visibility_status' => $this->visibility_status->value,
            'position' => $this->position,
            'is_received' => $this->received_at !== null,
            'availability' => $this->availability?->value,
            'availability_label' => $this->availability?->label(),
            'price_history' => $this->whenLoaded('priceHistories', fn () => $this->priceHistories
                ->map(fn ($point): array => [
                    'price' => $point->price,
                    'recorded_at' => $point->recorded_at->toIso8601String(),
                ])
                ->values()),
            'is_owner' => $isOwner,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => [
                'update' => $viewer !== null && $viewer->can('update', $this->resource),
                'delete' => $viewer !== null && $viewer->can('delete', $this->resource),
            ],
        ];

        // Only admins buy gifts, so nobody else gets any claim data or controls.
        if (! $isOwner && $viewer?->isAdmin() !== true) {
            $data['can']['purchase'] = false;

            return $data;
        }

        // Purchase data is exposed exclusively to admins who don't own the
        // item. For the owner we deliberately omit every purchase-related key.
        if (! $isOwner) {
            $purchase = $this->whenLoaded('purchase');
            $hasPurchase = $this->relationLoaded('purchase') && $this->purchase !== null;

            $data['can']['purchase'] = $viewer !== null
                && ! $hasPurchase
                && $viewer->can('purchase', $this->resource);

            // "is_purchased" here means the item is claimed (taken) in any state;
            // the precise reserved-vs-bought state lives in purchase.status.
            $data['is_purchased'] = $hasPurchase;

            $isMine = $hasPurchase
                && $viewer !== null
                && $viewer->id === $this->purchase->purchased_by_user_id;

            $data['purchase'] = $hasPurchase ? [
                'status' => $this->purchase->status->value,
                'purchased_by_name' => $this->purchase->purchasedBy?->name,
                'purchased_at' => $this->purchase->purchased_at->toIso8601String(),
                'note' => $this->purchase->note,
                'purchased_by_me' => $isMine,
                'can_unmark' => $isMine,
                'can_mark_bought' => $isMine && $this->purchase->status === PurchaseStatus::Reserved,
                'can_mark_delivered' => $isMine && $this->purchase->status === PurchaseStatus::Purchased,
                // A stale reservation we've nudged the claimer about.
                'needs_confirmation' => $isMine
                    && $this->purchase->status === PurchaseStatus::Reserved
                    && $this->purchase->reminded_at !== null,
                'can_confirm' => $isMine && $this->purchase->status === PurchaseStatus::Reserved,
                // What the giver paid, and the price when the item was added to
                // compare it against. Only the giver themselves sees these.
                'price_paid' => $isMine ? $this->purchase->price_paid : null,
                'original_price' => $isMine && $this->relationLoaded('originalPrice')
                    ? $this->originalPrice?->price
                    : null,
            ] : null;
        }

        return $data;
    }
}
