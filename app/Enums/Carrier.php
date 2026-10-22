<?php

namespace App\Enums;

enum Carrier: string
{
    case Ups = 'ups';
    case Usps = 'usps';
    case FedEx = 'fedex';
    case Dhl = 'dhl';
    case Amazon = 'amazon';
    case Other = 'other';

    /**
     * Human readable label for the carrier.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ups => 'UPS',
            self::Usps => 'USPS',
            self::FedEx => 'FedEx',
            self::Dhl => 'DHL',
            self::Amazon => 'Amazon',
            self::Other => 'Other',
        };
    }

    /**
     * Guess the carrier from the shape of a normalized tracking number, or
     * Other when it doesn't look like any we know.
     */
    public static function detect(string $trackingNumber): self
    {
        return match (true) {
            (bool) preg_match('/^1Z[0-9A-Z]{16}$/', $trackingNumber) => self::Ups,
            (bool) preg_match('/^TBA\d{9,15}$/', $trackingNumber) => self::Amazon,
            (bool) preg_match('/^(9[1-5]\d{18,24}|[A-Z]{2}\d{9}US)$/', $trackingNumber) => self::Usps,
            (bool) preg_match('/^(\d{12}|\d{15})$/', $trackingNumber) => self::FedEx,
            (bool) preg_match('/^\d{10}$/', $trackingNumber) => self::Dhl,
            default => self::Other,
        };
    }

    /**
     * The carrier's public tracking page for a number, or null when there is
     * no page to link to.
     */
    public function trackingUrl(string $trackingNumber): ?string
    {
        $number = rawurlencode($trackingNumber);

        return match ($this) {
            self::Ups => "https://www.ups.com/track?tracknum={$number}",
            self::Usps => "https://tools.usps.com/go/TrackConfirmAction?tLabels={$number}",
            self::FedEx => "https://www.fedex.com/fedextrack/?trknbr={$number}",
            self::Dhl => "https://www.dhl.com/us-en/home/tracking/tracking-express.html?submit=1&tracking-id={$number}",
            self::Amazon => "https://track.amazon.com/tracking/{$number}",
            self::Other => null,
        };
    }

    /**
     * The 17TRACK carrier code, or null to let 17TRACK work it out from the
     * number (Amazon ships under several codes, so it's left to detection).
     */
    public function trackerCode(): ?int
    {
        return match ($this) {
            self::Ups => 100002,
            self::Usps => 21051,
            self::FedEx => 100003,
            self::Dhl => 100001,
            default => null,
        };
    }
}
