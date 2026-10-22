<?php

namespace App\Notifications;

use App\Enums\TrackingStatus;
use App\Models\User;
use App\Models\WishlistItemPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WishlistItemPurchase $purchase) {}

    /**
     * Get the notification's delivery channels.
     *
     * Suppressed per-recipient when the giver has turned off shipment emails.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->notify_shipment_updates) {
            return [];
        }

        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->purchase->wishlistItem;
        $status = $this->purchase->tracking_status;

        $message = (new MailMessage)
            ->subject(match ($status) {
                TrackingStatus::OutForDelivery => __(':title is out for delivery', ['title' => $item->title]),
                TrackingStatus::Delivered => __(':title was delivered', ['title' => $item->title]),
                TrackingStatus::Exception => __('Delivery problem: :title', ['title' => $item->title]),
                default => __(':title is on its way', ['title' => $item->title]),
            })
            ->line(__('The gift you bought for :owner, ":title", is now: :status.', [
                'owner' => $item->user->name,
                'title' => $item->title,
                'status' => strtolower($status?->label() ?? ''),
            ]));

        if ($this->purchase->tracking_detail !== null) {
            $message->line(__('Latest update: :detail', ['detail' => $this->purchase->tracking_detail]));
        }

        if ($this->purchase->tracking_estimated_delivery_at !== null && $status !== TrackingStatus::Delivered) {
            $message->line(__('Expected by :date.', [
                'date' => $this->purchase->tracking_estimated_delivery_at->format('l, F j'),
            ]));
        }

        if ($status === TrackingStatus::Delivered) {
            $message->line(__('We\'ve marked it as delivered for you.'));
        }

        if ($status === TrackingStatus::Exception) {
            $message->line(__('The carrier reported a problem, so it\'s worth checking the tracking page.'));
        }

        return $message
            ->action(__('Track the package'), $this->purchase->trackingUrl() ?? route('gifts.index'))
            ->line(__(':owner has not been told.', ['owner' => $item->user->name]));
    }
}
