<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class OfficeVisitNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $notificationId
     * @param  int  $receiverId
     * @param  array  $notificationData
     */
    public function __construct(
        public int $notificationId,
        public int $receiverId,
        public array $notificationData
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int,\Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->receiverId}"),
        ];
    }

    /**
     * Customize the event name.
     */
    public function broadcastAs(): string
    {
        return 'OfficeVisitNotificationCreated';
    }

    /**
     * Data sent to the frontend upon broadcast.
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notificationData,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }
}
