<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $receiverId;
    public $unreadCount;
    public $message;
    public $url;

    /**
     * Create a new event instance.
     *
     * @param int $receiverId
     * @param int $unreadCount
     * @param string|null $message Optional notification message for rich toast
     * @param string|null $url Optional URL for clickable toast navigation
     */
    public function __construct($receiverId, $unreadCount, $message = null, $url = null)
    {
        $this->receiverId = $receiverId;
        $this->unreadCount = (int) $unreadCount;
        $this->message = $message;
        $this->url = $url;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->receiverId);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $data = [
            'receiver_id' => $this->receiverId,
            'unread_count' => $this->unreadCount,
            'timestamp' => now()->toISOString(),
            'type' => 'notification_count_updated'
        ];
        if ($this->message !== null) {
            $data['message'] = $this->message;
        }
        if ($this->url !== null) {
            $data['url'] = $this->url;
        }
        return $data;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'notification.count.updated';
    }
}
