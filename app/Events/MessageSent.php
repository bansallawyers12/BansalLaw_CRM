<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
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
            // Broadcast to specific user channel (matching WebSocket client subscription)
            return new PrivateChannel('user.' . $this->targetUserId);
        }
        
        // Broadcast to matter-specific channel
        if (isset($this->message['client_matter_id'])) {
            return new PrivateChannel('matter.' . $this->message['client_matter_id']);
        }
        
        // Fallback to general channel
        return new Channel('messages');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $message = $this->normalizeMessagePayload();

        return [
            'message' => $message,
            'id' => $message['id'],
            'sender' => $message['sender'],
            'sender_id' => $message['sender_id'],
            'sender_shortname' => $message['sender_shortname'],
            'is_sender' => $message['is_sender'],
            'is_recipient' => $message['is_recipient'],
            'recipient_ids' => $message['recipient_ids'],
            'recipient_count' => $message['recipient_count'],
            'sent_at' => $message['sent_at'],
            'is_read' => $message['is_read'],
            'read_at' => $message['read_at'],
            'client_matter_id' => $message['client_matter_id'],
            'created_at' => $message['created_at'],
            'updated_at' => $message['updated_at'],
            'attachments' => $message['attachments'],
            'timestamp' => now()->toISOString(),
            'type' => 'message_sent'
        ];
    }

    private function normalizeMessagePayload(): array
    {
        $message = is_array($this->message) ? $this->message : [];
        $recipientIds = $message['recipient_ids'] ?? $message['recipients'] ?? [];

        if (!is_array($recipientIds)) {
            $recipientIds = [];
        }

        $timestamp = $message['sent_at'] ?? $message['created_at'] ?? now()->toISOString();

        $viewer = $this->deriveViewerFlags($message, $recipientIds);

        return [
            'id' => (int) ($message['id'] ?? 0),
            'message' => (string) ($message['message'] ?? ''),
            'sender' => (string) ($message['sender'] ?? $message['sender_name'] ?? ''),
            'sender_id' => (int) ($message['sender_id'] ?? 0),
            'sender_shortname' => (string) ($message['sender_shortname'] ?? $message['sender_initials'] ?? ''),
            'is_sender' => $viewer['is_sender'],
            'is_recipient' => $viewer['is_recipient'],
            'recipient_ids' => array_values($recipientIds),
            'recipient_count' => (int) ($message['recipient_count'] ?? count($recipientIds)),
            'sent_at' => $timestamp,
            'is_read' => array_key_exists('is_read', $message) ? $message['is_read'] : null,
            'read_at' => $message['read_at'] ?? null,
            'client_matter_id' => (int) ($message['client_matter_id'] ?? 0),
            'created_at' => $message['created_at'] ?? $timestamp,
            'updated_at' => $message['updated_at'] ?? ($message['created_at'] ?? $timestamp),
            'attachments' => is_array($message['attachments'] ?? null) ? $message['attachments'] : [],
        ];
    }

    /**
     * Per-subscriber flags: private channels use targetUserId; matter/general channels keep payload or defaults.
     *
     * @param  array  $message  Raw message array
     * @param  array  $recipientRows  recipient_ids or recipients entries
     */
    private function deriveViewerFlags(array $message, array $recipientRows): array
    {
        if ($this->targetUserId === null) {
            return [
                'is_sender' => array_key_exists('is_sender', $message) ? (bool) $message['is_sender'] : false,
                'is_recipient' => array_key_exists('is_recipient', $message) ? (bool) $message['is_recipient'] : true,
            ];
        }

        $targetId = (int) $this->targetUserId;
        $senderId = (int) ($message['sender_id'] ?? 0);
        $isSender = $senderId !== 0 && $senderId === $targetId;

        $isRecipient = false;
        foreach ($recipientRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rid = $row['recipient_id'] ?? null;
            if ($rid !== null && (int) $rid === $targetId) {
                $isRecipient = true;
                break;
            }
        }

        if (! $isRecipient && isset($message['recipient_id']) && (int) $message['recipient_id'] === $targetId) {
            $isRecipient = true;
        }

        return [
            'is_sender' => $isSender,
            'is_recipient' => $isRecipient,
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }
}
