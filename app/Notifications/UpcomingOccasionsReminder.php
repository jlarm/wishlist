<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingOccasionsReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  list<array{owner_id: int, owner_name: string, occasion: string, days_until: int, unclaimed: int, total: int}>  $occasions
     */
    public function __construct(public array $occasions) {}

    /**
     * Get the notification's delivery channels.
     *
     * Suppressed per-recipient when a member has turned off occasion reminders.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->notify_occasion_reminders) {
            return [];
        }

        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(count($this->occasions) === 1
                ? __(":owner's :occasion is coming up", [
                    'owner' => $this->occasions[0]['owner_name'],
                    'occasion' => $this->occasions[0]['occasion'],
                ])
                : __('Occasions coming up'))
            ->line(__('A quick heads-up so nobody is left scrambling:'));

        foreach ($this->occasions as $occasion) {
            $replace = [
                'owner' => $occasion['owner_name'],
                'occasion' => $occasion['occasion'],
                'days' => $occasion['days_until'],
                'unclaimed' => $occasion['unclaimed'],
                'total' => $occasion['total'],
            ];

            $message->line($occasion['total'] === 0
                ? __(":owner's :occasion is in :days days — their list is empty so far.", $replace)
                : __(":owner's :occasion is in :days days — :unclaimed of :total wishes still unclaimed.", $replace));
        }

        return $message->action(__("Browse everyone's lists"), route('wishlists.index'));
    }
}
