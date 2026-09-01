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
     * @return Collection<int, Workflow>
     */
    public function workflowOptions(): Collection
    {
        return Cache::remember('admin_console_workflows_v1', $this->formCacheTtl(), function () {
            return Workflow::query()
                ->select(['id', 'name', 'matter_id'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * @return Collection<int, Matter>
     */
    public function matterOptions(): Collection
    {
        return Cache::remember('admin_console_matters_v1', $this->formCacheTtl(), function () {
            return Matter::query()
                ->select(['id', 'title', 'nick_name'])
                ->orderBy('title')
                ->get();
        });
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
        return Cache::remember('admin_console_activity_staff_v1', $this->formCacheTtl(), function () {
            return Staff::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where('status', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (Staff $staff) => [
                    'id' => (int) $staff->id,
                    'name' => trim($staff->first_name . ' ' . $staff->last_name),
                    'email' => $staff->email,
                ]);
        });
    }
}
