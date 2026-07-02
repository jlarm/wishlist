<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WishlistItemPriceDropped extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $newPrice  The freshly recorded price that met the target.
     */
    public function __construct(
        public WishlistItem $item,
        public string $newPrice,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Suppressed entirely when the owner has turned off price-drop emails.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->notify_price_drops) {
            return [];
        }

        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $link = $this->item->url ?? route('wishlists.show', $this->item->user_id);

        return (new MailMessage)
            ->subject(__('Price drop: :title', ['title' => $this->item->title]))
            ->greeting(__('Good news!'))
            ->line(__('An item on your wishlist has dropped to your target price.'))
            ->line(__(':title is now :price (your target was :target).', [
                'title' => $this->item->title,
                'price' => number_format((float) $this->newPrice, 2),
                'target' => number_format((float) $this->item->target_price, 2),
            ]))
            ->action(__('View the item'), $link)
            ->line(__('You are getting this because you set a target price for this item.'));
    }
}
