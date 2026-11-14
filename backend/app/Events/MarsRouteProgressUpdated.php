<?php

namespace App\Events;

use App\Models\MarsRoute;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarsRouteProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MarsRoute $marsRoute,
        public float $latitude,
        public float $longitude,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mars.user.'.$this->marsRoute->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'mars.route.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'route_id' => $this->marsRoute->id,
            'status' => $this->marsRoute->status,
            'progress_percent' => $this->marsRoute->progress_percent,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => $this->marsRoute->updated_at,
        ];
    }
}
