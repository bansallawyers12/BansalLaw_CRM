<?php

namespace App\Services;

use App\Models\Matter;
use App\Models\SmsTemplate;
use App\Models\Staff;
use App\Models\Workflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lean dropdown data for Admin Console forms (workflows, matters, SMS templates, staff).
 */
class AdminConsoleFormDataService
{
    public function formCacheTtl(): int
    {
        return max(60, (int) config('crm.admin_console.form_cache_seconds', 300));
    }

    /**
     * Cached as plain arrays so file/redis cache cannot return __PHP_Incomplete_Class.
     *
     * @return Collection<int, object{id: int, name: string, matter_id: int|null, matter: object{title: string}|null}>
     */
    public function workflowOptions(): Collection
    {
        $cacheKey = 'admin_console_workflows_v2';

        $rows = Cache::remember($cacheKey, $this->formCacheTtl(), function () {
            return Workflow::query()
                ->with(['matter:id,title'])
                ->select(['id', 'name', 'matter_id'])
                ->orderBy('name')
                ->get()
                ->map(static fn (Workflow $workflow) => [
                    'id' => (int) $workflow->id,
                    'name' => (string) $workflow->name,
                    'matter_id' => $workflow->matter_id !== null ? (int) $workflow->matter_id : null,
                    'matter_title' => $workflow->matter?->title,
                ])
                ->all();
        });

        if (! is_array($rows)) {
            Cache::forget($cacheKey);
            Cache::forget('admin_console_workflows_v1');
            $rows = Workflow::query()
                ->with(['matter:id,title'])
                ->select(['id', 'name', 'matter_id'])
                ->orderBy('name')
                ->get()
                ->map(static fn (Workflow $workflow) => [
                    'id' => (int) $workflow->id,
                    'name' => (string) $workflow->name,
                    'matter_id' => $workflow->matter_id !== null ? (int) $workflow->matter_id : null,
                    'matter_title' => $workflow->matter?->title,
                ])
                ->all();
            Cache::put($cacheKey, $rows, $this->formCacheTtl());
        }

        return collect($rows)->map(static function (array $row) {
            $matterTitle = $row['matter_title'] ?? null;

            return (object) [
                'id' => $row['id'],
                'name' => $row['name'],
                'matter_id' => $row['matter_id'],
                'matter' => is_string($matterTitle) && $matterTitle !== ''
                    ? (object) ['title' => $matterTitle]
                    : null,
            ];
        });
    }

    /**
     * Cached as plain arrays so file/redis cache cannot return __PHP_Incomplete_Class.
     *
     * @return Collection<int, object{id: int, title: string, nick_name: string}>
     */
    public function matterOptions(): Collection
    {
        $cacheKey = 'admin_console_matters_v2';

        $rows = Cache::remember($cacheKey, $this->formCacheTtl(), function () {
            return Matter::query()
                ->select(['id', 'title', 'nick_name'])
                ->orderBy('title')
                ->get()
                ->map(static fn (Matter $matter) => [
                    'id' => (int) $matter->id,
                    'title' => (string) $matter->title,
                    'nick_name' => (string) $matter->nick_name,
                ])
                ->all();
        });

        if (! is_array($rows)) {
            Cache::forget($cacheKey);
            Cache::forget('admin_console_matters_v1');
            $rows = Matter::query()
                ->select(['id', 'title', 'nick_name'])
                ->orderBy('title')
                ->get()
                ->map(static fn (Matter $matter) => [
                    'id' => (int) $matter->id,
                    'title' => (string) $matter->title,
                    'nick_name' => (string) $matter->nick_name,
                ])
                ->all();
            Cache::put($cacheKey, $rows, $this->formCacheTtl());
        }

        return collect($rows)->map(static fn (array $row) => (object) $row);
    }

    /**
     * Active SMS templates for dropdowns (client Send SMS + Admin Console).
     * Cached as plain arrays so file/redis cache cannot return __PHP_Incomplete_Class.
     *
     * @return Collection<int, object{id: int, title: string, message: string, variables: mixed, category: string|null}>
     */
    public function activeSmsTemplates(): Collection
    {
        $cacheKey = 'admin_console_sms_templates_active_v2';

        $rows = Cache::remember($cacheKey, $this->formCacheTtl(), function () {
            return SmsTemplate::query()
                ->select(['id', 'title', 'message', 'variables', 'category'])
                ->where('is_active', true)
                ->orderBy('title')
                ->get()
                ->map(static fn (SmsTemplate $template) => [
                    'id' => (int) $template->id,
                    'title' => (string) $template->title,
                    'message' => (string) $template->message,
                    'variables' => $template->variables,
                    'category' => $template->category,
                ])
                ->all();
        });

        if (! is_array($rows)) {
            Cache::forget($cacheKey);
            Cache::forget('admin_console_sms_templates_active_v1');
            $rows = SmsTemplate::query()
                ->select(['id', 'title', 'message', 'variables', 'category'])
                ->where('is_active', true)
                ->orderBy('title')
                ->get()
                ->map(static fn (SmsTemplate $template) => [
                    'id' => (int) $template->id,
                    'title' => (string) $template->title,
                    'message' => (string) $template->message,
                    'variables' => $template->variables,
                    'category' => $template->category,
                ])
                ->all();
            Cache::put($cacheKey, $rows, $this->formCacheTtl());
        }

        return collect($rows)->map(static fn (array $row) => (object) $row);
    }

    /**
     * Staff list for Activity Search filter dropdowns.
     *
     * @return Collection<int, array{id: int, name: string, email: string|null}>
     */
    public function activitySearchStaffList(): Collection
    {
        $cacheKey = 'admin_console_activity_staff_v2';

        $rows = Cache::remember($cacheKey, $this->formCacheTtl(), function () {
            return Staff::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where('status', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(static fn (Staff $staff) => [
                    'id' => (int) $staff->id,
                    'name' => trim($staff->first_name . ' ' . $staff->last_name),
                    'email' => $staff->email,
                ])
                ->all();
        });

        if (! is_array($rows)) {
            Cache::forget($cacheKey);
            Cache::forget('admin_console_activity_staff_v1');
            $rows = Staff::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where('status', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(static fn (Staff $staff) => [
                    'id' => (int) $staff->id,
                    'name' => trim($staff->first_name . ' ' . $staff->last_name),
                    'email' => $staff->email,
                ])
                ->all();
            Cache::put($cacheKey, $rows, $this->formCacheTtl());
        }

        return collect($rows);
    }
}
