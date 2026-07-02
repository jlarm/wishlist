<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WishlistItemPurchased extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WishlistItem $item,
        public User $purchaser,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Suppressed per-recipient when a gift-giver has turned off purchase emails.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->notify_gift_purchases) {
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

        return (new MailMessage)
            ->subject(__('A gift has been claimed: :title', ['title' => $this->item->title]))
            ->line(__(':buyer marked ":title" on :owner\'s wishlist as purchased.', [
                'buyer' => $this->purchaser->name,
                'title' => $this->item->title,
                'owner' => $ownerName,
            ]))
            ->line(__('No need to buy this one — you can pick something else from their list.'))
            ->action(__(":owner's wishlist", ['owner' => $ownerName]), route('wishlists.show', $this->item->user_id))
            ->line(__('Only you and the other gift-givers can see this — :owner has not been told.', ['owner' => $ownerName]));
    }
}
