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
     */
    public function __construct(
        public WishlistItem $item,
        public string $oldPrice,
        public string $newPrice,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Suppressed per-recipient when a member has turned off price-drop emails.
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
        $ownerName = $this->item->user->name;
        $link = $this->item->url ?? route('wishlists.show', $this->item->user_id);

        return (new MailMessage)
            ->subject(__('Price drop: :title', ['title' => $this->item->title]))
            ->greeting(__('Good news!'))
            ->line(__('":title" on :owner\'s wishlist just dropped in price.', [
                'title' => $this->item->title,
                'owner' => $ownerName,
            ]))
            ->line(__('It went from :old to :new — a good moment to grab it.', [
                'old' => number_format((float) $this->oldPrice, 2),
                'new' => number_format((float) $this->newPrice, 2),
            ]))
            ->action(__('View the item'), $link)
            ->line(__('You are getting this because you could gift this item. :owner has not been told.', ['owner' => $ownerName]));
    }
}
