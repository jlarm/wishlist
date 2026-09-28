<?php

namespace App\Notifications;

use App\Enums\ClaimRemovalReason;
use App\Enums\PurchaseStatus;
use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimedItemRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  PurchaseStatus  $claimStatus  The claim's state when the item was removed.
     */
    public function __construct(
        public WishlistItem $item,
        public ClaimRemovalReason $reason,
        public PurchaseStatus $claimStatus,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Always sent: the claimer may otherwise buy something that's not wanted.
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
        $title = $this->item->title;
        $wasReserved = $this->claimStatus === PurchaseStatus::Reserved;

        $message = (new MailMessage)->subject(__('Heads up about ":title"', ['title' => $title]));

        $message->line(match ($this->reason) {
            ClaimRemovalReason::Deleted => __(':owner removed ":title" from their wishlist.', ['owner' => $owner, 'title' => $title]),
            ClaimRemovalReason::Hidden => __(':owner hid ":title" on their wishlist.', ['owner' => $owner, 'title' => $title]),
            ClaimRemovalReason::Received => __(':owner marked ":title" as already received.', ['owner' => $owner, 'title' => $title]),
        });

        if ($this->reason === ClaimRemovalReason::Received && $wasReserved) {
            $message->line(__('It looks like they got it elsewhere, so we released your reservation.'));
        } elseif ($this->reason === ClaimRemovalReason::Received) {
            $message->line(__('If you\'ve already given it to them, you\'re all set. If not, they may have gotten it elsewhere.'));
        } elseif ($wasReserved) {
            $message->line(__('You had it reserved — you may want to pick something else.'));
        } else {
            $message->line(__('You\'d marked it as bought, so you may want to check whether it\'s still wanted or keep the receipt handy.'));
        }

        return $message
            ->action(__('Review my gifts'), route('gifts.index'))
            ->line(__(':owner doesn\'t know you\'d claimed it.', ['owner' => $owner]));
    }
}
