<?php

namespace App\Services;

use App\Enums\Carrier;
use App\Enums\TrackingStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Looks up where a shipment is through 17TRACK, which covers UPS, USPS, FedEx,
 * DHL, Amazon and more behind one API. Tracking is optional: without an API key
 * the app just links to the carrier's own tracking page.
 */
class ShipmentTracker
{
    private const BASE_URL = 'https://api.17track.net/track/v2.4';

    /**
     * 17TRACK's error code for a number that is already registered, which is
     * fine: it's already being tracked and no quota is spent again.
     */
    private const ALREADY_REGISTERED = -18019901;

    /**
     * Whether live tracking is switched on (a 17TRACK key is configured).
     */
    public function isEnabled(): bool
    {
        return filled(config('services.seventeen_track.key'));
    }

    /**
     * Fetch the latest status for a tracking number. The first lookup registers
     * the number with 17TRACK (one unit of quota) and returns the carrier code
     * it settled on as the tracker id; later lookups pass that id back so the
     * number is never registered twice.
     *
     * Returns null when 17TRACK won't track the number (it currently refuses
     * USPS, for one), so the gift falls back to the carrier link.
     *
     * @return array{tracker_id: string, status: TrackingStatus, detail: ?string, estimated_delivery_at: ?string}|null
     */
    public function track(string $trackingNumber, Carrier $carrier, ?string $trackerId = null): ?array
    {
        $carrierCode = $trackerId !== null ? (int) $trackerId : $carrier->trackerCode();

        if ($trackerId === null) {
            $carrierCode = $this->register($trackingNumber, $carrierCode);

            if ($carrierCode === null) {
                return null;
            }
        }

        $trackInfo = $this->post('/gettrackinfo', [$this->shipment($trackingNumber, $carrierCode)])['accepted'][0]['track_info'] ?? [];

        $estimate = $trackInfo['time_metrics']['estimated_delivery_date'] ?? [];

        return [
            'tracker_id' => (string) $carrierCode,
            'status' => TrackingStatus::fromSeventeenTrack($trackInfo['latest_status']['status'] ?? 'NotFound'),
            'detail' => $this->describeEvent($trackInfo['latest_event'] ?? null),
            'estimated_delivery_at' => $estimate['to'] ?? $estimate['from'] ?? null,
        ];
    }

    /**
     * Start tracking a number, returning the carrier code 17TRACK will use
     * (0 when it detects one but doesn't say which), or null when it refuses.
     */
    private function register(string $trackingNumber, ?int $carrierCode): ?int
    {
        $data = $this->post('/register', [$this->shipment($trackingNumber, $carrierCode)]);

        if (isset($data['accepted'][0])) {
            return $data['accepted'][0]['carrier'] ?? $carrierCode ?? 0;
        }

        $rejection = $data['rejected'][0] ?? [];

        if (($rejection['error']['code'] ?? null) === self::ALREADY_REGISTERED) {
            return $rejection['carrier'] ?? $carrierCode ?? 0;
        }

        Log::info('17TRACK refused a tracking number.', [
            'tracking_number' => $trackingNumber,
            'message' => $rejection['error']['message'] ?? null,
        ]);

        return null;
    }

    /**
     * The latest scan as a short line, e.g. "Arrived at facility – Chicago, IL".
     *
     * @param  array{description?: ?string, location?: ?string, address?: array{city?: ?string, state?: ?string}}|null  $event
     */
    private function describeEvent(?array $event): ?string
    {
        if ($event === null || blank($event['description'] ?? null)) {
            return null;
        }

        $place = $event['location'] ?? null;

        if (blank($place)) {
            $place = implode(', ', array_filter([
                $event['address']['city'] ?? null,
                $event['address']['state'] ?? null,
            ]));
        }

        $line = blank($place) ? $event['description'] : "{$event['description']} – {$place}";

        return mb_strimwidth($line, 0, 255, '…');
    }

    /**
     * One shipment in a request, leaving the carrier out for 17TRACK to detect.
     *
     * @return array{number: string, carrier?: int}
     */
    private function shipment(string $trackingNumber, ?int $carrierCode): array
    {
        $shipment = ['number' => $trackingNumber];

        if ($carrierCode) {
            $shipment['carrier'] = $carrierCode;
        }

        return $shipment;
    }

    /**
     * Send a request and return its data, failing loudly on an API error.
     *
     * @param  list<array{number: string, carrier?: int}>  $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        $body = $this->client()->post($endpoint, $payload)->throw()->json();

        if (($body['code'] ?? null) !== 0) {
            throw new RuntimeException('17TRACK request failed with code '.($body['code'] ?? 'unknown').'.');
        }

        return $body['data'] ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['17token' => (string) config('services.seventeen_track.key')])
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
