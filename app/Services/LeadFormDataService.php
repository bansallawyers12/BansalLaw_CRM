<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\Country;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Shared create/edit form data for leads (lean queries + cache + contact caps).
 */
class LeadFormDataService
{
    /**
     * @return array<string, string>
     */
    public function stageLabels(): array
    {
        return [
            'new' => 'New Enquiry',
            'initial_consultation' => 'Initial Consultation',
            'conflict_check' => 'Conflict Check',
            'engaged' => 'Engaged',
            'retained' => 'Retained',
            'follow_up' => 'Follow Up',
            'not_proceeding' => 'Not Proceeding',
            'declined' => 'Declined',
        ];
    }

    public function formCacheTtl(): int
    {
        return max(60, (int) config('crm.leads.form_cache_seconds', 300));
    }

    public function contactRowLimit(): int
    {
        return max(5, min(200, (int) config('crm.leads.contact_row_limit', 50)));
    }

    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return Cache::remember('lead_form_countries_v1', $this->formCacheTtl(), function () {
            return Country::query()
                ->select(['id', 'name', 'sortname', 'phonecode'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Active staff for assignee dropdowns (lean columns).
     *
     * @return Collection<int, Staff>
     */
    public function assignableStaff(bool $includeEmail = false): Collection
    {
        $cacheKey = $includeEmail ? 'lead_form_assignable_staff_email_v1' : 'lead_form_assignable_staff_v1';

        return Cache::remember($cacheKey, $this->formCacheTtl(), function () use ($includeEmail) {
            $columns = $includeEmail
                ? ['id', 'first_name', 'last_name', 'email']
                : ['id', 'first_name', 'last_name'];

            return Staff::query()
                ->select($columns)
                ->where('status', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        });
    }

    /**
     * @return array{
     *   countries: Collection<int, Country>,
     *   assignableStaff: Collection<int, Staff>,
     *   leadStageLabels: array<string, string>
     * }
     */
    public function createFormData(): array
    {
        return [
            'countries' => $this->countries(),
            'assignableStaff' => $this->assignableStaff(),
            'leadStageLabels' => $this->stageLabels(),
        ];
    }

    /**
     * @return array{
     *   clientContacts: Collection<int, ClientContact>,
     *   emails: Collection<int, ClientEmail>,
     *   contacts_total: int,
     *   emails_total: int,
     *   contacts_has_more: bool,
     *   emails_has_more: bool,
     *   contacts_next_offset: int,
     *   emails_next_offset: int,
     *   contact_row_limit: int
     * }
     */
    public function relatedContactBundle(int $clientId, int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->contactRowLimit();
        $limit = max(5, min(200, $limit));
        $offset = max(0, $offset);

        $contactsQuery = ClientContact::query()
            ->select([
                'id',
                'client_id',
                'contact_type',
                'phone',
                'country_code',
                'is_verified',
                'verified_at',
            ])
            ->where('client_id', $clientId)
            ->orderBy('id');

        $emailsQuery = ClientEmail::query()
            ->select([
                'id',
                'client_id',
                'email_type',
                'email',
                'is_verified',
                'verified_at',
            ])
            ->where('client_id', $clientId)
            ->orderBy('id');

        $contactsTotal = (clone $contactsQuery)->count();
        $emailsTotal = (clone $emailsQuery)->count();

        $contacts = (clone $contactsQuery)->skip($offset)->take($limit)->get();
        $emails = (clone $emailsQuery)->skip($offset)->take($limit)->get();

        $contactsNext = $offset + $contacts->count();
        $emailsNext = $offset + $emails->count();

        return [
            'clientContacts' => $contacts,
            'emails' => $emails,
            'contacts_total' => $contactsTotal,
            'emails_total' => $emailsTotal,
            'contacts_has_more' => $contactsNext < $contactsTotal,
            'emails_has_more' => $emailsNext < $emailsTotal,
            'contacts_next_offset' => $contactsNext,
            'emails_next_offset' => $emailsNext,
            'contact_row_limit' => $limit,
        ];
    }

    /**
     * @return array{rows: Collection<int, ClientContact>, total: int, has_more: bool, next_offset: int}
     */
    public function contactRowsPage(int $clientId, int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->contactRowLimit();
        $limit = max(5, min(200, $limit));
        $offset = max(0, $offset);

        $query = ClientContact::query()
            ->select([
                'id',
                'client_id',
                'contact_type',
                'phone',
                'country_code',
                'is_verified',
                'verified_at',
            ])
            ->where('client_id', $clientId)
            ->orderBy('id');

        $total = (clone $query)->count();
        $rows = $query->skip($offset)->take($limit)->get();
        $next = $offset + $rows->count();

        return [
            'rows' => $rows,
            'total' => $total,
            'has_more' => $next < $total,
            'next_offset' => $next,
        ];
    }

    /**
     * @return array{rows: Collection<int, ClientEmail>, total: int, has_more: bool, next_offset: int}
     */
    public function emailRowsPage(int $clientId, int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->contactRowLimit();
        $limit = max(5, min(200, $limit));
        $offset = max(0, $offset);

        $query = ClientEmail::query()
            ->select([
                'id',
                'client_id',
                'email_type',
                'email',
                'is_verified',
                'verified_at',
            ])
            ->where('client_id', $clientId)
            ->orderBy('id');

        $total = (clone $query)->count();
        $rows = $query->skip($offset)->take($limit)->get();
        $next = $offset + $rows->count();

        return [
            'rows' => $rows,
            'total' => $total,
            'has_more' => $next < $total,
            'next_offset' => $next,
        ];
    }
}
