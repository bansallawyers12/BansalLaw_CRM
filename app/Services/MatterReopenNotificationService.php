<?php

namespace App\Services;

use App\Models\ClientMatter;
use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Support\Collection;

class MatterReopenNotificationService
{
    public const TYPE_REQUEST = 'Matter Reopen Request';

    /**
     * Whether this notification must stay unread until reopen/cancel.
     */
    public function isStickyPending(Notification $notification): bool
    {
        if (($notification->notification_type ?? '') !== self::TYPE_REQUEST) {
            return false;
        }

        $matterId = (int) ($notification->module_id ?? 0);
        if ($matterId <= 0) {
            return false;
        }

        $matter = ClientMatter::query()->find($matterId);
        if (! $matter) {
            return false;
        }

        if ((int) ($matter->matter_status ?? 0) === 1) {
            return false;
        }

        return ! empty($matter->reopen_requested_by);
    }

    /**
     * Mark read only when the reopen request is no longer pending.
     *
     * @return bool True when marked read; false when sticky or unauthorized.
     */
    public function tryMarkAsRead(Notification $notification, ?int $receiverId = null): bool
    {
        if ($receiverId !== null && (int) $notification->receiver_id !== $receiverId) {
            return false;
        }

        if ($this->isStickyPending($notification)) {
            // Keep unread and bump timestamp so it resurfaces in lists.
            $notification->receiver_status = 0;
            $notification->seen = 0;
            $notification->touch();

            return false;
        }

        $notification->receiver_status = 1;
        $notification->seen = 1;
        $notification->save();

        return true;
    }

    /**
     * Force pending reopen-request notifications back to unread for a receiver.
     */
    public function reassertUnreadForReceiver(int $receiverId): void
    {
        if ($receiverId <= 0) {
            return;
        }

        $notifications = Notification::query()
            ->where('receiver_id', $receiverId)
            ->where('notification_type', self::TYPE_REQUEST)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($notifications as $notification) {
            if (! $this->isStickyPending($notification)) {
                continue;
            }

            if ((int) ($notification->receiver_status ?? 0) !== 0 || (int) ($notification->seen ?? 0) !== 0) {
                $notification->receiver_status = 0;
                $notification->seen = 0;
                $notification->save();
            }
        }
    }

    /**
     * Pending reopen-request alerts for staff who can approve reopen.
     *
     * @return Collection<int, Notification>
     */
    public function pendingAlertsForStaff(?Staff $staff): Collection
    {
        if (! $staff || ! $this->staffCanApproveReopen($staff)) {
            return collect();
        }

        $this->reassertUnreadForReceiver((int) $staff->id);

        return Notification::query()
            ->where('receiver_id', $staff->id)
            ->where('notification_type', self::TYPE_REQUEST)
            ->where('receiver_status', 0)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->filter(fn (Notification $n) => $this->isStickyPending($n))
            ->values();
    }

    /**
     * Mark all Matter Reopen Request notifications for a matter as resolved/read.
     */
    public function markResolvedForMatter(int $clientMatterId): void
    {
        if ($clientMatterId <= 0) {
            return;
        }

        Notification::query()
            ->where('module_id', $clientMatterId)
            ->where('notification_type', self::TYPE_REQUEST)
            ->update([
                'receiver_status' => 1,
                'seen' => 1,
            ]);
    }

    public function staffCanApproveReopen(Staff $staff): bool
    {
        if ((int) ($staff->role ?? 0) === 1) {
            return true;
        }

        if (method_exists($staff, 'hasEffectiveSuperAdminPrivileges') && $staff->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        if ((bool) ($staff->grant_super_admin_access ?? false)) {
            return true;
        }

        return method_exists($staff, 'hasCrmModule') && $staff->hasCrmModule('45');
    }

    /**
     * Order query so sticky reopen requests appear first.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Notification>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Notification>
     */
    public function orderWithStickyFirst($query)
    {
        $type = self::TYPE_REQUEST;

        return $query
            ->orderByRaw(
                'CASE WHEN notification_type = ? AND receiver_status = 0 THEN 0 ELSE 1 END',
                [$type]
            )
            ->orderByDesc('id');
    }
}
