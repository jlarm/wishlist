<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WishlistItem
 *
 * Privacy boundary: this resource is the single place wishlist items become
 * Inertia props. Purchase data is ONLY ever added when the viewer is not the
 * item owner. The owner's payload never contains a purchase key, so purchase
 * status cannot leak through props, JSON, or page source.
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
            // The target price is a personal alert threshold, shown only to the
            // owner — gift-givers never see it.
            'target_price' => $this->when($isOwner, fn () => $this->target_price),
            'size' => $this->size,
            'color' => $this->color,
            'tags' => $this->tags,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_weight' => $this->priority->weight(),
            'notes' => $this->notes,
            'visibility_status' => $this->visibility_status->value,
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

        // Purchase data is exposed exclusively to non-owners. For the owner we
        // deliberately omit every purchase-related key.
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
                'can_mark_bought' => $isMine && ! $this->purchase->isPurchased(),
            ] : null;
        }

        return $data;
    }
}
