<?php

namespace App\Services;

use App\Models\AppUser;
use App\Models\CafeBranch;
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
        $branch = $order->branch;
        if (! $branch || ! $this->hasCoordinates($branch)) {
            return null;
        }

        $delegate = $this->nearestAvailableDelegate($branch->latitude, $branch->longitude);

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
            ->where('user_type_id', $this->delegateTypeId())
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('location_updated_at', '>=', Carbon::now()->subMinutes(30));

        if ($excludeDelegateId) {
            $query->where('id', '!=', $excludeDelegateId);
        }

        return $query
            ->get()
            ->sortBy(fn (AppUser $d) => $this->distance($latitude, $longitude, $d->latitude, $d->longitude))
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

    protected function hasCoordinates(CafeBranch $branch): bool
    {
        return ! is_null($branch->latitude) && ! is_null($branch->longitude);
    }

    protected function delegateTypeId(): int
    {
        return \App\Models\UserType::where('name', 'delegate')->value('id')
            ?? throw new \RuntimeException('Delegate user type not found');
    }
}
