<?php

namespace App\Notifications;

use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WishlistItem $item,
        public int $releaseInDays,
    ) {}

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
        return (new MailMessage)
            ->subject(__('Still getting ":title"?', ['title' => $this->item->title]))
            ->line(__('You reserved ":title" for :owner a while ago, but haven\'t marked it as bought yet.', [
                'title' => $this->item->title,
                'owner' => $this->item->user->name,
            ]))
            ->line(__('Keep the reservation, mark it bought, or release it so someone else can get it. If we don\'t hear from you, it will be released in :days days.', [
                'days' => $this->releaseInDays,
            ]))
            ->action(__('Review my gifts'), route('gifts.index'));
    }
}
