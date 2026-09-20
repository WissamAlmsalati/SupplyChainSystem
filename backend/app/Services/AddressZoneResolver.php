<?php

namespace App\Services;

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Models\Notification;

/**
 * Which delivery zone a point on the map falls in, decided by the server.
 *
 * The zone sets the delivery fee, so it cannot be something the client sends:
 * a modified app, or just a second client doing its own hexagon maths, would
 * pick the fee. Clients send coordinates; this finds the zone.
 */
class AddressZoneResolver
{
    public function resolve(float $latitude, float $longitude): ?DeliveryZone
    {
        $zones = DeliveryZone::where('is_active', true)->get(['id', 'hex_id']);

        // Zones are drawn on the res-4 grid today, but nothing forces that, so
        // look the point up at every resolution actually in use. The resolution
        // is four bits inside the H3 index; reading it here saves a Node call.
        $resolutions = $zones->map(fn ($z) => self::resolutionOf($z->hex_id))->filter(fn ($r) => $r !== null)->unique();

        foreach ($resolutions as $resolution) {
            $cell = H3Service::latLngToCell($latitude, $longitude, $resolution);
            if ($zone = $zones->firstWhere('hex_id', $cell)) {
                return DeliveryZone::find($zone->id);
            }
        }

        return null;
    }

    /**
     * Keeps the promise made to a cafe whose address was outside coverage: when
     * a zone starts delivering, every uncovered address inside it joins the zone
     * and its owner is told they can order now. Returns how many were adopted.
     */
    public function adopt(DeliveryZone $zone): int
    {
        $resolution = self::resolutionOf((string) $zone->hex_id);
        if (! $zone->is_active || $resolution === null) {
            return 0;
        }

        $adopted = 0;
        Address::whereNull('delivery_zone_id')->whereNotNull('latitude')->whereNotNull('longitude')
            ->each(function (Address $address) use ($zone, $resolution, &$adopted) {
                if (H3Service::latLngToCell((float) $address->latitude, (float) $address->longitude, $resolution) !== $zone->hex_id) {
                    return;
                }
                $address->update(['delivery_zone_id' => $zone->id]);
                Notification::sendTo(
                    [$address->user_id],
                    'بدأنا التوصيل إلى منطقتك',
                    "عنوانك «{$address->name}» صار ضمن نطاق التوصيل، ويمكنك الطلب إليه الآن.",
                    '/addresses',
                    'address',
                    ['address', $address->id],
                );
                $adopted++;
            });

        return $adopted;
    }

    public static function resolutionOf(string $hexId): ?int
    {
        if (! preg_match('/^[0-9a-f]{15,16}$/i', $hexId)) {
            return null;
        }

        return (int) ((hexdec($hexId) >> 52) & 0xF);
    }
}
