<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Staff;
use App\Support\StaffClientVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Note-based client/lead task actions (assign, update, reassign).
 * Extracted from ClientsController to keep HTTP layer thin.
 */
class ClientTaskActionService
{
    public function taskStore(Request $request)
    {
        try {
            $requestData = $request->all();

            if (empty($requestData['client_id'])) {
                return $this->legacyJson(['success' => false, 'message' => 'Client ID is required']);
            }

            $clientId = $this->decodeClientId($requestData['client_id']);
            if ($clientId === false || empty($clientId)) {
                return $this->legacyJson(['success' => false, 'message' => 'Invalid client ID']);
            }

            $remCat = $requestData['rem_cat'] ?? [];
            if (! is_array($remCat)) {
                $remCat = ! empty($remCat) ? [$remCat] : [];
            }

            if (empty($remCat)) {
                return $this->legacyJson(['success' => false, 'message' => 'At least one assignee must be selected']);
            }

            $targetClient = $this->findClientOrLeadForAction((int) $clientId);
            if (! $targetClient) {
                return $this->legacyJson(['success' => false, 'message' => 'Client or lead not found']);
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                return $this->legacyJson(['success' => false, 'message' => config('constants.unauthorized')]);
            }

            $clientLabel = $this->taskClientDisplayName($targetClient);
            $actionUniqueId = 'group_' . uniqid('', true);
            $matterId = $this->resolveTaskMatterId($requestData, (int) $clientId);

            $mirroredToClientTask = false;
            $taskSync = app(ClientMatterTaskSyncService::class);
            foreach ($remCat as $assigneeId) {
                $action = new Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->matter_id = $matterId;
                $action->description = $requestData['description'] ?? '';
                $action->unique_group_id = $actionUniqueId;

                $assigneeName = $this->getAssigneeName($assigneeId);
                $defaultTitle = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;
                $action->title = ! empty($requestData['remindersubject']) ? $requestData['remindersubject'] : $defaultTitle;
                $action->is_action = 1;
                $action->pin = 0;
                $action->status = '0';
                $action->type = 'client';
                $action->task_group = $requestData['task_group'] ?? null;
                $action->assigned_to = $assigneeId;

                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = $requestData['followup_datetime'];
                }

                if (isset($requestData['note_deadline_checkbox']) && $requestData['note_deadline_checkbox'] != '') {
                    $action->note_deadline = $requestData['note_deadline_checkbox'] == 1
                        ? ($requestData['note_deadline'] ?? null)
                        : null;
                } else {
                    $action->note_deadline = null;
                }

                if ($action->save()) {
                    if (! $mirroredToClientTask) {
                        $taskSync->mirrorTaskNoteToClientTask($action);
                        $mirroredToClientTask = true;
                    }

                    if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                        $targetClient->followup_date = $requestData['followup_datetime'];
                        $targetClient->save();
                    }

                    $notification = new Notification;
                    $notification->sender_id = Auth::user()->id;
                    $notification->receiver_id = $assigneeId;
                    $notification->module_id = $clientId;
                    $notification->url = $this->taskClientDetailUrl((int) $clientId, $matterId, $requestData['client_id'] ?? null);
                    $notification->notification_type = 'client';
                    $notification->receiver_status = 0;
                    $notification->seen = 0;

                    $actionDateTime = $requestData['followup_datetime'] ?? now();
                    try {
                        if (is_numeric($actionDateTime)) {
                            $formattedDate = date('d/M/Y h:i A', $actionDateTime);
                        } else {
                            $timestamp = strtotime($actionDateTime);
                            $formattedDate = $timestamp !== false ? date('d/M/Y h:i A', $timestamp) : date('d/M/Y h:i A');
                        }
                    } catch (\Exception) {
                        $formattedDate = date('d/M/Y h:i A');
                    }

                    $notification->message = ($clientLabel !== '' ? 'Task for ' . $clientLabel . '. ' : '')
                        . 'Assigned by ' . Auth::user()->first_name . ' ' . Auth::user()->last_name . ' on ' . $formattedDate;
                    $notification->save();

                    app(TaskTimelineService::class)->logTaskNoteCreated($action, $clientLabel, $assigneeName);
                }
            }

            return $this->legacyJson(['success' => true, 'message' => 'successfully saved', 'clientID' => $requestData['client_id']]);
        } catch (\Exception $e) {
            Log::error('Error in taskStore: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->legacyJson(['success' => false, 'message' => 'Error saving task. Please try again.']);
        }
    }

    public function storePersonalTask(Request $request)
    {
        try {
            $requestData = $request->all();
            $clientId = null;
            $encodedClientId = null;
            $matterId = null;

            if (! empty($requestData['client_id'])) {
                $parsed = $this->parseTaskClientMatterPath((string) $requestData['client_id']);
                $encodedClientId = $parsed['encoded'];
                if (empty($parsed['client_id'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $parsed['client_id'];
                $matterId = $this->resolveTaskMatterId($requestData, $clientId);
            }

            $actionUniqueId = 'group_' . uniqid('', true);
            $clientLabel = '';
            if ($clientId !== null) {
                $targetClient = $this->findClientOrLeadForAction((int) $clientId);
                if (! $targetClient) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->taskClientDisplayName($targetClient);
            }

            $assignees = is_array($requestData['rem_cat']) ? $requestData['rem_cat'] : [$requestData['rem_cat']];
            $mirroredToClientTask = false;
            $taskSync = app(ClientMatterTaskSyncService::class);

            foreach ($assignees as $assigneeId) {
                $action = new Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->matter_id = $matterId;
                $action->description = $requestData['description'] ?? null;
                $action->unique_group_id = $actionUniqueId;
                $action->is_action = 1;
                $action->type = 'client';
                $action->task_group = $requestData['task_group'] ?? null;
                $action->assigned_to = $assigneeId;
                $action->status = '0';
                $action->pin = 0;
                $assigneeName = $this->getAssigneeName($assigneeId);
                $action->title = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;

                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = $requestData['followup_datetime'];
                }

                if ($action->save()) {
                    if ($clientId && ! $mirroredToClientTask) {
                        $taskSync->mirrorTaskNoteToClientTask($action);
                        $mirroredToClientTask = true;
                    }

                    $notification = new Notification;
                    $notification->sender_id = Auth::user()->id;
                    $notification->receiver_id = $assigneeId;
                    $notification->module_id = $clientId;
                    $notification->url = $this->taskClientDetailUrl($clientId, $matterId, $requestData['client_id'] ?? $encodedClientId);
                    $notification->message = ($clientLabel !== '' ? 'Task for ' . $clientLabel . '. ' : '') . 'Assigned to you';
                    $notification->seen = 0;
                    $notification->save();

                    if ($clientId) {
                        app(TaskTimelineService::class)->logTaskNoteCreated($action, $clientLabel, $assigneeName);
                    }
                }
            }

            return response()->json(['success' => true, 'message' => 'Task created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in storePersonalTask: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error creating task: ' . $e->getMessage()], 500);
        }
    }

    public function updateTask(Request $request)
    {
        $requestData = $request->all();

        try {
            $action = Note::findOrFail($requestData['note_id']);
            $clientId = null;
            $clientLabel = '';
            $matterId = null;

            if (! empty($requestData['client_id'])) {
                $parsed = $this->parseTaskClientMatterPath((string) $requestData['client_id']);
                if (empty($parsed['client_id'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $parsed['client_id'];
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->taskClientDisplayName($targetForAction);
                $matterId = $this->resolveTaskMatterId($requestData, $clientId);
            }

            $action->description = $requestData['description'] ?? null;
            $action->client_id = $clientId;
            if ($matterId) {
                $action->matter_id = $matterId;
            } elseif ($clientId === null) {
                $action->matter_id = null;
            }
            $action->task_group = $requestData['task_group'] ?? null;
            $action->assigned_to = $requestData['rem_cat'] ?? null;

            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = $requestData['followup_datetime'];
            }

            $action->save();

            if ($action->assigned_to != $action->getOriginal('assigned_to')) {
                $notification = new Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;
                $notification->url = $this->taskClientDetailUrl(
                    $clientId,
                    $action->matter_id ? (int) $action->matter_id : $matterId,
                    $requestData['client_id'] ?? null
                );
                $notification->message = ($clientLabel !== '' ? 'Task for ' . $clientLabel . '. ' : '') . 'Updated — reassigned to you';
                $notification->seen = 0;
                $notification->save();
            }

            if ($clientId !== null) {
                $assigneeName = $this->getAssigneeName($action->assigned_to);
                app(TaskTimelineService::class)->logTaskNoteUpdated($action, $clientLabel, $assigneeName);
            }

            return response()->json(['success' => true, 'message' => 'Task updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating task: ' . $e->getMessage()], 500);
        }
    }

    public function reassignTask(Request $request)
    {
        try {
            $requestData = $request->all();
            $clientId = null;
            $clientLabel = '';
            $matterId = null;

            if (! empty($requestData['client_id'])) {
                $parsed = $this->parseTaskClientMatterPath((string) $requestData['client_id']);
                if (empty($parsed['client_id'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $parsed['client_id'];
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->taskClientDisplayName($targetForAction);
                $matterId = $this->resolveTaskMatterId($requestData, $clientId);
            }

            $actionUniqueId = 'group_' . uniqid('', true);
            $action = new Note;
            $action->client_id = $clientId;
            $action->user_id = Auth::user()->id;
            $action->matter_id = $matterId;
            $action->description = $requestData['description'] ?? null;
            $action->unique_group_id = $actionUniqueId;
            $action->is_action = 1;
            $action->type = 'client';
            $action->task_group = $requestData['task_group'] ?? null;
            $action->assigned_to = $requestData['rem_cat'] ?? null;
            $action->status = '0';
            $action->pin = 0;
            $assigneeName = $this->getAssigneeName($action->assigned_to);
            $action->title = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;

            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = $requestData['followup_datetime'];
            }

            if ($action->save()) {
                if ($clientId) {
                    app(ClientMatterTaskSyncService::class)->mirrorTaskNoteToClientTask($action);
                }

                $notification = new Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;
                $notification->url = $this->taskClientDetailUrl($clientId, $matterId, $requestData['client_id'] ?? null);
                $notification->message = ($clientLabel !== '' ? 'Task for ' . $clientLabel . '. ' : '') . 'Assigned to you';
                $notification->seen = 0;
                $notification->save();

                if ($clientId) {
                    app(TaskTimelineService::class)->logTaskNoteCreated($action, $clientLabel, $assigneeName);
                }
            }

            return response()->json(['success' => true, 'message' => 'Task created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in reassignTask: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error creating task: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\Response|never
     */
    private function legacyJson(array $payload)
    {
        echo json_encode($payload);
        exit;
    }

    private function decodeClientId(mixed $encoded): int|string|false
    {
        if (empty($encoded)) {
            return false;
        }

        if (is_numeric($encoded)) {
            return (int) $encoded;
        }

        $string = (string) $encoded;
        if (base64_encode(base64_decode($string, true)) === $string) {
            try {
                $decoded = @convert_uudecode(base64_decode($string));

                return ($decoded !== false && $decoded !== '') ? $decoded : $string;
            } catch (\ValueError) {
                return $string;
            }
        }

        return $string;
    }

    private function getAssigneeName($assigneeId): string
    {
        $staff = Staff::find($assigneeId);

        return $staff ? $staff->first_name . ' ' . $staff->last_name : 'Unknown Assignee';
    }

    private function findClientOrLeadForAction(int $id): ?Admin
    {
        return Admin::with('company')->whereIn('type', ['client', 'lead'])->find($id);
    }

    private function taskClientDisplayName(Admin $client): string
    {
        $label = trim($client->company_name_or_personal_name);
        if ($label === '') {
            $label = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        }

        return $label;
    }

    /**
     * @return array{encoded: ?string, client_id: ?int, matter_ref: ?string, matter_id: ?int}
     */
    private function parseTaskClientMatterPath(?string $clientPath): array
    {
        $result = [
            'encoded' => null,
            'client_id' => null,
            'matter_ref' => null,
            'matter_id' => null,
        ];

        $clientPath = trim((string) $clientPath);
        if ($clientPath === '') {
            return $result;
        }

        $parts = explode('/', $clientPath);
        $encoded = $parts[0] ?? '';
        $result['encoded'] = $encoded !== '' ? $encoded : null;

        if ($encoded !== '') {
            $decoded = $this->decodeClientId($encoded);
            if ($decoded !== false && $decoded !== '' && $decoded !== null) {
                $result['client_id'] = (int) $decoded;
            }
        }

        if (isset($parts[1]) && strcasecmp((string) $parts[1], 'Matter') === 0 && ! empty($parts[2])) {
            $result['matter_ref'] = (string) $parts[2];
        }

        if ($result['client_id'] && $result['matter_ref']) {
            $matterId = ClientMatter::where('client_id', $result['client_id'])
                ->where('client_unique_matter_no', $result['matter_ref'])
                ->value('id');
            if ($matterId) {
                $result['matter_id'] = (int) $matterId;
            }
        }

        return $result;
    }

    private function resolveTaskMatterId(array $requestData, ?int $clientId): ?int
    {
        if (! empty($requestData['matter_id']) && is_numeric($requestData['matter_id'])) {
            $matterId = (int) $requestData['matter_id'];
            if ($matterId > 0 && $clientId) {
                if (ClientMatter::where('id', $matterId)->where('client_id', $clientId)->exists()) {
                    return $matterId;
                }
            } elseif ($matterId > 0 && ! $clientId) {
                return $matterId;
            }
        }

        if (! empty($requestData['client_matter_id']) && is_numeric($requestData['client_matter_id'])) {
            $matterId = (int) $requestData['client_matter_id'];
            if ($matterId > 0 && $clientId && ClientMatter::where('id', $matterId)->where('client_id', $clientId)->exists()) {
                return $matterId;
            }
        }

        if (! empty($requestData['client_id'])) {
            $parsed = $this->parseTaskClientMatterPath((string) $requestData['client_id']);
            if (! empty($parsed['matter_id'])) {
                return (int) $parsed['matter_id'];
            }
        }

        return null;
    }

    private function taskClientDetailUrl(?int $clientId, ?int $matterId = null, ?string $encodedOrPath = null): string
    {
        $encoded = null;
        if ($encodedOrPath) {
            $parsed = $this->parseTaskClientMatterPath($encodedOrPath);
            if (! empty($parsed['encoded']) && ! empty($parsed['matter_ref'])) {
                return URL::to('/clients/detail/' . $parsed['encoded'] . '/' . $parsed['matter_ref']);
            }
            if (! empty($parsed['encoded'])) {
                $encoded = $parsed['encoded'];
            }
        }

        if ($clientId) {
            $encoded = $encoded ?? base64_encode(convert_uuencode($clientId));
            if ($matterId) {
                $matterRef = ClientMatter::where('id', $matterId)->value('client_unique_matter_no');
                if ($matterRef) {
                    return URL::to('/clients/detail/' . $encoded . '/' . $matterRef);
                }
            }

            return URL::to('/clients/detail/' . $encoded);
        }

        return route('assignee.tasks');
    }
}
