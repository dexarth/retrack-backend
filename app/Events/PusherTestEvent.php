<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PusherTestEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('test-channel');
    }

    public function broadcastAs(): string
    {
        return 'test-event';
    }

    // this will send your exact payload array
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
