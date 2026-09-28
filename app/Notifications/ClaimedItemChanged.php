<?php

namespace App\Notifications;

use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimedItemChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, array{from: ?string, to: ?string}>  $changes  Keyed by field label.
     */
    public function __construct(
        public WishlistItem $item,
        public array $changes,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Always sent: the claimer may otherwise buy the wrong thing.
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
        $ownerName = $this->item->user->name;

        $message = (new MailMessage)
            ->subject(__('A gift you claimed changed: :title', ['title' => $this->item->title]))
            ->line(__(':owner updated ":title", which you\'ve claimed. Here\'s what changed:', [
                'owner' => $ownerName,
                'title' => $this->item->title,
            ]));

        foreach ($this->changes as $label => $change) {
            $message->line(__(':label: :from → :to', [
                'label' => $label,
                'from' => $change['from'] ?? __('(empty)'),
                'to' => $change['to'] ?? __('(empty)'),
            ]));
        }

        return $message
            ->action(__('Review my gifts'), route('gifts.index'))
            ->line(__(':owner doesn\'t know you\'ve claimed it.', ['owner' => $ownerName]));
    }
}
