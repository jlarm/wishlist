<?php

namespace App\Enums;

enum TrackingStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';

    /**
     * Human readable label for the shipment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Label created',
            self::InTransit => 'In transit',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Exception => 'Delivery problem',
        };
    }

    /**
     * Map a 17TRACK main status onto ours. Unrecognized statuses read as
     * pending rather than failing the check.
     */
    public static function fromSeventeenTrack(string $status): self
    {
        return match ($status) {
            'InTransit', 'AvailableForPickup' => self::InTransit,
            'OutForDelivery' => self::OutForDelivery,
            'Delivered' => self::Delivered,
            'DeliveryFailure', 'Exception', 'Expired' => self::Exception,
            default => self::Pending,
        };
    }
}
