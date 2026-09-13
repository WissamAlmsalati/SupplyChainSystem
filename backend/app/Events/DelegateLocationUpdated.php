<?php

namespace App\Events;

use App\Models\AppUser;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DelegateLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppUser $delegate)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('delegates.locations'),
        ];
    }

    public function broadcastWith(): array
    {
        $profile = $this->delegate->delegateProfile;

        return [
            'id' => $this->delegate->id,
            'name' => $this->delegate->name,
            'latitude' => $profile?->latitude,
            'longitude' => $profile?->longitude,
            'is_available' => (bool) $profile?->is_available,
            'location_updated_at' => $profile?->location_updated_at?->toDateTimeString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delegate.location.updated';
    }
}
