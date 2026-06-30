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
            'size' => $this->size,
            'color' => $this->color,
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_weight' => $this->priority->weight(),
            'notes' => $this->notes,
            'visibility_status' => $this->visibility_status->value,
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

            $data['is_purchased'] = $hasPurchase;
            $data['purchase'] = $hasPurchase ? [
                'purchased_by_name' => $this->purchase->purchasedBy?->name,
                'purchased_at' => $this->purchase->purchased_at->toIso8601String(),
                'note' => $this->purchase->note,
                'purchased_by_me' => $viewer !== null
                    && $viewer->id === $this->purchase->purchased_by_user_id,
                'can_unmark' => $viewer !== null
                    && $viewer->id === $this->purchase->purchased_by_user_id,
            ] : null;
        }

        return $data;
    }
}
