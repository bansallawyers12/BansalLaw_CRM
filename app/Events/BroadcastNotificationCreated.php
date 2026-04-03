<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class BroadcastNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $batchUuid
     * @param  string  $message
     * @param  string|null  $title
     * @param  int  $senderId
     * @param  array<int,int>  $channelRecipientIds
     * @param  array<int,int>  $payloadRecipientIds
     * @param  int  $recipientCount
     * @param  string  $scope
     * @param  \Illuminate\Support\Carbon  $sentAt
     */
    public function __construct(
        public string $batchUuid,
        public string $message,
        public ?string $title,
        public int $senderId,
        public string $senderName,
        public array $channelRecipientIds,
        public array $payloadRecipientIds,
        public int $recipientCount,
        public string $scope,
        public Carbon $sentAt
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int,\Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        \Log::info('📡 BroadcastNotificationCreated::broadcastOn called', [
            'scope' => $this->scope,
            'channel_recipient_count' => count($this->channelRecipientIds)
        ]);
        
        $channels = [];

        if ($this->scope === 'all') {
            $channels[] = new Channel('broadcasts');
            \Log::info('✅ Added public "broadcasts" channel');
        }

        foreach ($this->channelRecipientIds as $recipientId) {
            $channels[] = new PrivateChannel("user.{$recipientId}");
        }
        
        \Log::info('✅ Total channels created', [
            'count' => count($channels),
            'first_5' => array_slice(array_map(fn($c) => $c->name ?? 'broadcasts', $channels), 0, 5)
        ]);

        return $channels;
    }

    /**
     * Customize the event name.
     */
    public function broadcastAs(): string
    {
        return 'BroadcastNotificationCreated';
    }

    /**
     * Data sent to the frontend upon broadcast.
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'batch_uuid' => $this->batchUuid,
            'message' => $this->message,
            'title' => $this->title,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'recipient_count' => $this->recipientCount,
            'scope' => $this->scope,
            'sent_at' => $this->sentAt->toIso8601String(),
        ];

        if (!empty($this->payloadRecipientIds)) {
            $payload['recipient_ids'] = $this->payloadRecipientIds;
        }

        return $payload;
    }
}


