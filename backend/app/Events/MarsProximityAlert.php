<?php

namespace App\Events;

use App\Models\MarsAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarsProximityAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MarsAlert $alert)
    {
        $this->alert->load('hazard');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mars.user.'.$this->alert->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'mars.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'severity' => $this->alert->severity,
            'status' => $this->alert->status,
            'distance_km' => $this->alert->distance_km,
            'hazard' => [
                'id' => $this->alert->hazard->id,
                'category' => $this->alert->hazard->category,
                'title' => $this->alert->hazard->title,
                'description' => $this->alert->hazard->description,
                'latitude' => $this->alert->hazard->latitude,
                'longitude' => $this->alert->hazard->longitude,
                'source_name' => $this->alert->hazard->source_name,
                'source_url' => $this->alert->hazard->source_url,
                'observed_at' => $this->alert->hazard->observed_at,
            ],
            'updated_at' => $this->alert->updated_at,
        ];
    }
}
