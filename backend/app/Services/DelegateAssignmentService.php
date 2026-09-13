<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\Order;
use Illuminate\Support\Carbon;

class DelegateAssignmentService
{
    /**
     * Auto-assign the nearest available delegate to an order.
     * Returns the assigned delegate or null.
     */
    public function assignNearest(Order $order): ?AppUser
    {
        if ($order->delivery_latitude === null || $order->delivery_longitude === null) {
            return null;
        }

        $delegate = $this->nearestAvailableDelegate((float) $order->delivery_latitude, (float) $order->delivery_longitude);

        if ($delegate) {
            $order->update(['delegate_id' => $delegate->id]);
        }

        return $delegate;
    }

    /**
     * Find the nearest available delegate to the given coordinates.
     */
    public function nearestAvailableDelegate(float $latitude, float $longitude, ?int $excludeDelegateId = null): ?AppUser
    {
        $query = AppUser::query()
            ->with('delegateProfile')
            ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))
            ->where('is_active', true)
            ->whereHas('delegateProfile', fn ($q) => $q
                ->where('is_available', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('location_updated_at', '>=', Carbon::now()->subMinutes(30)));

        if ($excludeDelegateId) {
            $query->where('id', '!=', $excludeDelegateId);
        }

        return $query
            ->get()
            ->sortBy(fn (AppUser $d) => $this->distance(
                $latitude,
                $longitude,
                (float) $d->delegateProfile->latitude,
                (float) $d->delegateProfile->longitude,
            ))
            ->first();
    }

    /**
     * Haversine distance between two coordinates in kilometers.
     */
    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
