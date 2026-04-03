<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $targetUserId;

    /**
     * Create a new event instance.
     *
     * @param array $message
     * @param int|null $targetUserId
     */
    public function __construct($message, $targetUserId = null)
    {
        $this->message = $message;
        $this->targetUserId = $targetUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        if ($this->targetUserId) {
            return new PrivateChannel('user.' . $this->targetUserId);
        }
        
        if (isset($this->message['client_matter_id'])) {
            return new PrivateChannel('matter.' . $this->message['client_matter_id']);
        }
        
        return new Channel('messages');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
            'type' => 'message_updated'
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.updated';
    }
}
