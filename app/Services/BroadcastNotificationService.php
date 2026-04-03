<?php

namespace App\Services;

use App\Events\BroadcastNotificationCreated;
use App\Models\Staff;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BroadcastNotificationService
{
    /**
     * Create a broadcast notification batch and notify recipients.
     *
     * @param  array  $payload
     * @return array{batch_uuid:string, recipient_count:int, message:string, title:?string, sent_at:Carbon}
     */
    public function createBroadcast(array $payload): array
    {
        \Log::info('🚀 BroadcastNotificationService::createBroadcast started');
        
        $sender = $payload['sender'] ?? Auth::user();

        if (!$sender) {
            \Log::error('❌ No sender found for broadcast');
            throw new \RuntimeException('A valid sender is required to create a broadcast.');
        }

        $scope = $payload['scope'] ?? 'all';
        $messageBody = trim((string) ($payload['message'] ?? ''));
        $title = isset($payload['title']) ? trim((string) $payload['title']) : null;
        
        \Log::info('📋 Resolving recipients', [
            'scope' => $scope,
            'sender_id' => $sender->id,
            'provided_recipient_ids' => $payload['recipient_ids'] ?? []
        ]);
        
        $recipientIds = $this->resolveRecipients($scope, (array) ($payload['recipient_ids'] ?? []), (int) $sender->id);
        
        \Log::info('✅ Recipients resolved', [
            'count' => $recipientIds->count(),
            'ids' => $recipientIds->take(10)->toArray() // Show first 10
        ]);

        if ($recipientIds->isEmpty()) {
            throw new \InvalidArgumentException('Unable to resolve any recipients for the broadcast.');
        }

        $batchUuid = (string) Str::uuid();
        $sentAt = Carbon::now();

        $storedMessage = $title ? "{$title}\n{$messageBody}" : $messageBody;
        $senderName = $this->formatSenderName($sender);

        $notificationRows = $recipientIds->map(function (int $recipientId) use ($sender, $batchUuid, $storedMessage, $sentAt) {
            return [
                'sender_id' => (int) $sender->id,
                'receiver_id' => $recipientId,
                'module_id' => null,
                'url' => $this->formatBroadcastUrl($batchUuid),
                'notification_type' => 'broadcast',
                'message' => $storedMessage,
                'sender_status' => 1,
                'receiver_status' => 0,
                'seen' => 0,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ];
        });

        // Chunk inserts to avoid "too many placeholders" error
        // MySQL has a limit of ~65,535 placeholders per statement
        // With 11 columns per row, we can safely insert ~500 rows per batch
        $notificationRows->chunk(500)->each(function ($chunk) {
            DB::table('notifications')->insert($chunk->all());
        });

        $recipientCount = $recipientIds->count();
        $recipientIdsForChannels = $recipientIds->all();
        $recipientIdsForPayload = $recipientCount <= 50 ? $recipientIdsForChannels : [];

        \Log::info('📡 Broadcasting BroadcastNotificationCreated event', [
            'batch_uuid' => $batchUuid,
            'recipient_count' => $recipientCount,
            'scope' => $scope,
            'channel_count' => count($recipientIdsForChannels),
            'broadcast_driver' => config('broadcasting.default')
        ]);
        
        try {
            broadcast(new BroadcastNotificationCreated(
                batchUuid: $batchUuid,
                message: $messageBody,
                title: $title,
                senderId: (int) $sender->id,
                senderName: $senderName,
                channelRecipientIds: $recipientIdsForChannels,
                payloadRecipientIds: $recipientIdsForPayload,
                recipientCount: $recipientCount,
                scope: $scope,
                sentAt: $sentAt
            ));
            
            \Log::info('✅ Broadcast event dispatched successfully');
        } catch (\Illuminate\Broadcasting\BroadcastException $e) {
            // Log the error but don't fail the entire operation
            \Log::error('⚠️ Reverb broadcast failed (notification still saved to database)', [
                'error' => $e->getMessage(),
                'batch_uuid' => $batchUuid,
                'broadcast_driver' => config('broadcasting.default'),
                'reverb_host' => config('broadcasting.connections.reverb.options.host'),
                'reverb_port' => config('broadcasting.connections.reverb.options.port'),
                'hint' => 'Ensure Reverb server is running: php artisan reverb:start'
            ]);
            // Continue execution - notification is already saved to database
        } catch (\Exception $e) {
            // Log any other broadcast-related errors
            \Log::error('⚠️ Unexpected broadcast error (notification still saved to database)', [
                'error' => $e->getMessage(),
                'batch_uuid' => $batchUuid,
                'exception' => get_class($e)
            ]);
            // Continue execution - notification is already saved to database
        }

        return [
            'batch_uuid' => $batchUuid,
            'recipient_count' => $recipientIds->count(),
            'message' => $messageBody,
            'title' => $title,
            'sent_at' => $sentAt,
        ];
    }

    /**
     * Retrieve broadcast history for a sender.
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public function getBroadcastHistory(int $senderId): Collection
    {
        $rows = Notification::query()
            ->select([
                'url',
                'sender_id',
                DB::raw('MIN(created_at) as sent_at'),
                DB::raw('COUNT(*) as total_recipients'),
                DB::raw('SUM(CASE WHEN receiver_status = 1 THEN 1 ELSE 0 END) as read_count'),
                DB::raw('SUM(CASE WHEN receiver_status = 0 THEN 1 ELSE 0 END) as unread_count'),
                DB::raw('MAX(message) as message'),
            ])
            ->where('notification_type', 'broadcast')
            ->where('sender_id', $senderId)
            ->groupBy('url', 'sender_id')
            ->orderByDesc(DB::raw('MIN(created_at)'))
            ->get();

        return $rows->map(function ($row) {
            return [
                'batch_uuid' => $this->extractBatchUuid($row->url),
                'message' => $this->extractMessageBody($row->message)['message'],
                'title' => $this->extractMessageBody($row->message)['title'],
                'sent_at' => Carbon::parse($row->sent_at),
                'sender_id' => (int) $row->sender_id,
                'sender_name' => $this->getSenderName($row->sender_id),
                'total_recipients' => (int) $row->total_recipients,
                'read_count' => (int) $row->read_count,
                'unread_count' => (int) $row->unread_count,
            ];
        });
    }

    /**
     * Retrieve ALL broadcast history globally (for all users to see).
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public function getAllBroadcastHistory(): Collection
    {
        $rows = Notification::query()
            ->select([
                'url',
                'sender_id',
                DB::raw('MIN(created_at) as sent_at'),
                DB::raw('COUNT(*) as total_recipients'),
                DB::raw('SUM(CASE WHEN receiver_status = 1 THEN 1 ELSE 0 END) as read_count'),
                DB::raw('SUM(CASE WHEN receiver_status = 0 THEN 1 ELSE 0 END) as unread_count'),
                DB::raw('MAX(message) as message'),
            ])
            ->where('notification_type', 'broadcast')
            ->groupBy('url', 'sender_id')
            ->orderByDesc(DB::raw('MIN(created_at)'))
            ->get();

        return $rows->map(function ($row) {
            return [
                'batch_uuid' => $this->extractBatchUuid($row->url),
                'message' => $this->extractMessageBody($row->message)['message'],
                'title' => $this->extractMessageBody($row->message)['title'],
                'sent_at' => Carbon::parse($row->sent_at),
                'sender_id' => (int) $row->sender_id,
                'sender_name' => $this->getSenderName($row->sender_id),
                'total_recipients' => (int) $row->total_recipients,
                'read_count' => (int) $row->read_count,
                'unread_count' => (int) $row->unread_count,
            ];
        });
    }

    /**
     * Get broadcasts that the user has already read (for archive view).
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public function getReadBroadcasts(int $receiverId): Collection
    {
        $notifications = Notification::query()
            ->with('sender:id,first_name,last_name,email')
            ->where('notification_type', 'broadcast')
            ->where('receiver_id', $receiverId)
            ->where('receiver_status', 1) // Only read messages
            ->orderByDesc('updated_at') // Order by when they were marked as read
            ->get();

        return $notifications->map(function (Notification $notification) {
            $messageMeta = $this->extractMessageBody($notification->message);
            $senderName = $notification->sender
                ? $this->formatSenderName($notification->sender)
                : null;

            return [
                'notification_id' => $notification->id,
                'batch_uuid' => $this->extractBatchUuid($notification->url),
                'message' => $messageMeta['message'],
                'title' => $messageMeta['title'],
                'sender_id' => $notification->sender_id,
                'sender_name' => $senderName,
                'sent_at' => Carbon::parse($notification->created_at),
                'read_at' => Carbon::parse($notification->updated_at),
            ];
        });
    }

    /**
     * Delete a broadcast batch (super admin only).
     * This hard deletes all notifications in the batch.
     */
    public function deleteBroadcast(string $batchUuid, int $requesterId): bool
    {
        \Log::info('🗑️ Delete broadcast requested', [
            'batch_uuid' => $batchUuid,
            'requester_id' => $requesterId
        ]);

        $url = $this->formatBroadcastUrl($batchUuid);
        
        $deleted = DB::table('notifications')
            ->where('notification_type', 'broadcast')
            ->where('url', $url)
            ->delete();

        \Log::info('✅ Broadcast deleted', [
            'batch_uuid' => $batchUuid,
            'notifications_deleted' => $deleted
        ]);

        return $deleted > 0;
    }

    /**
     * Get sender name by ID (cached helper).
     */
    protected function getSenderName(int $senderId): string
    {
        static $senderCache = [];
        
        if (!isset($senderCache[$senderId])) {
            $sender = Staff::find($senderId);
            $senderCache[$senderId] = $sender ? $this->formatSenderName($sender) : 'Unknown';
        }
        
        return $senderCache[$senderId];
    }

    /**
     * Retrieve broadcast details (per-recipient status) for a sender.
     */
    public function getBroadcastDetails(string $batchUuid, int $senderId): array
    {
        $notifications = Notification::query()
            ->with(['receiver:id,first_name,last_name,email', 'sender:id,first_name,last_name,email'])
            ->where('notification_type', 'broadcast')
            ->where('sender_id', $senderId)
            ->where('url', $this->formatBroadcastUrl($batchUuid))
            ->orderBy('created_at')
            ->get();

        if ($notifications->isEmpty()) {
            throw new \RuntimeException('Broadcast not found or you do not have access to it.');
        }

        $first = $notifications->first();
        $messageMeta = $this->extractMessageBody($first->message);

        $recipients = $notifications->map(function (Notification $notification) {
            $receiver = $notification->receiver;

            return [
                'notification_id' => $notification->id,
                'receiver_id' => $notification->receiver_id,
                'receiver_name' => $receiver ? trim("{$receiver->first_name} {$receiver->last_name}") : null,
                'receiver_email' => $receiver->email ?? null,
                'read' => (bool) $notification->receiver_status,
                'read_at' => $notification->updated_at && $notification->receiver_status
                    ? Carbon::parse($notification->updated_at)
                    : null,
            ];
        });

        return [
            'batch_uuid' => $batchUuid,
            'message' => $messageMeta['message'],
            'title' => $messageMeta['title'],
            'sent_at' => Carbon::parse($first->created_at),
            'sender_id' => $first->sender_id,
            'sender_name' => $this->formatSenderName($first->sender),
            'total_recipients' => $notifications->count(),
            'read_count' => $recipients->where('read', true)->count(),
            'unread_count' => $recipients->where('read', false)->count(),
            'recipients' => $recipients,
        ];
    }

    /**
     * Mark a broadcast notification as read for the given receiver.
     */
    public function markAsRead(int $notificationId, int $receiverId): bool
    {
        $updated = DB::table('notifications')
            ->where('id', $notificationId)
            ->where('receiver_id', $receiverId)
            ->where('notification_type', 'broadcast')
            ->update([
                'receiver_status' => 1,
                'seen' => 1,
                'updated_at' => Carbon::now(),
            ]);

        return (bool) $updated;
    }

    /**
     * Return unread broadcast notifications for a receiver.
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public function getUnreadBroadcasts(int $receiverId): Collection
    {
        $notifications = Notification::query()
            ->with('sender:id,first_name,last_name,email')
            ->where('notification_type', 'broadcast')
            ->where('receiver_id', $receiverId)
            ->where('receiver_status', 0)
            ->orderByDesc('created_at')
            ->get();

        return $notifications->map(function (Notification $notification) {
            $messageMeta = $this->extractMessageBody($notification->message);
            $senderName = $notification->sender
                ? $this->formatSenderName($notification->sender)
                : null;

            return [
                'notification_id' => $notification->id,
                'batch_uuid' => $this->extractBatchUuid($notification->url),
                'message' => $messageMeta['message'],
                'title' => $messageMeta['title'],
                'sender_id' => $notification->sender_id,
                 'sender_name' => $senderName,
                'sent_at' => Carbon::parse($notification->created_at),
            ];
        });
    }

    /**
     * Resolve broadcast recipients based on scope.
     */
    protected function resolveRecipients(string $scope, array $requestedIds, int $senderId): Collection
    {
        return match ($scope) {
            'specific' => $this->filterSpecificRecipients($requestedIds, $senderId),
            'team' => collect(), // Placeholder until team logic is implemented
            default => $this->allRecipients($senderId),
        };
    }

    /**
     * Format the canonical URL used to tag broadcasts.
     */
    protected function formatBroadcastUrl(string $batchUuid): string
    {
        return '/broadcasts/' . $batchUuid;
    }

    /**
     * Extract the batch UUID from a broadcast URL.
     */
    protected function extractBatchUuid(?string $url): string
    {
        if (!$url) {
            return '';
        }

        return Str::afterLast($url, '/');
    }

    /**
     * Extract title/message from the stored message payload.
     *
     * @return array{title:?string,message:string}
     */
    protected function extractMessageBody(?string $storedMessage): array
    {
        $storedMessage = $storedMessage ?? '';

        if (str_contains($storedMessage, "\n")) {
            [$title, $message] = explode("\n", $storedMessage, 2);

            return [
                'title' => trim($title) !== '' ? trim($title) : null,
                'message' => trim($message),
            ];
        }

        return [
            'title' => null,
            'message' => trim($storedMessage),
        ];
    }

    /**
     * Fetch every available recipient excluding the sender.
     * Only includes Admin users (staff), NOT clients (type='client'/'lead').
     */
    protected function allRecipients(int $senderId): Collection
    {
        $staffIds = Staff::query()
            ->where('status', 1)  // Only active staff
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $staffIds
            ->reject(fn (int $id) => $id === $senderId)
            ->values();
    }

    /**
     * Filter specific recipients, ensuring the sender is excluded.
     */
    protected function filterSpecificRecipients(array $requestedIds, int $senderId): Collection
    {
        return collect($requestedIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $senderId)
            ->unique()
            ->values();
    }

    protected function formatSenderName($sender): string
    {
        if (!$sender) {
            return 'System';
        }

        $name = trim(sprintf(
            '%s %s',
            $sender->first_name ?? '',
            $sender->last_name ?? ''
        ));

        if ($name !== '') {
            return $name;
        }

        if (property_exists($sender, 'name') && $sender->name) {
            return $sender->name;
        }

        return $sender->email ?? 'System';
    }

    /**
     * @return \Illuminate\Support\Collection<int,never>
     * @deprecated No longer used - clients are excluded from broadcasts. This method always returns an empty collection.
     */
    protected function portalUsers(): Collection
    {
        // This method is deprecated - clients (User model) should not receive broadcasts
        // Only staff (type != 'client'/'lead') should receive broadcasts
        return collect();
    }
}


