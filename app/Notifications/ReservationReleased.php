<?php

namespace App\Notifications;

use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReleased extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WishlistItem $item) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $owner = $this->item->user->name;

        return (new MailMessage)
            ->subject(__('Reservation released: :title', ['title' => $this->item->title]))
            ->line(__('We didn\'t hear back, so your reservation of ":title" for :owner has been released and it\'s up for grabs again.', [
                'title' => $this->item->title,
                'owner' => $owner,
            ]))
            ->line(__('Still want to get it? You can reserve it again if nobody else has.'))
            ->action(__(":owner's wishlist", ['owner' => $owner]), route('wishlists.show', $this->item->user_id));
    }
}
