<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogSecurityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $event,
        public array $data = [],
        public ?User $user = null
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('security-logs');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'security-log';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'user_id' => $this->user?->id,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast critical events
        return in_array($this->event, [
            'brute_force_attack',
            'sql_injection_attempt',
            'xss_attempt',
            'impossible_travel_detected',
            'session_hijacking_attempt',
        ]);
    }
}