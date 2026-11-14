<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Company;

/**
 * Display name for invoice / receipt "Bill To" (CRM ref, not primary contact person).
 */
final class ClientBillToDisplay
{
    public static function billingName(Admin $admin): string
    {
        $company = $admin->relationLoaded('company') ? $admin->company : null;
        if ($company === null) {
            $company = Company::query()->where('admin_id', $admin->id)->first();
        }

        if ($admin->isCompany() || $company !== null) {
            $companyName = trim((string) ($company?->company_name ?? ''));
            if ($companyName === '' && $company !== null) {
                $companyName = trim((string) ($company->trust_name ?? ''));
            }
            if ($companyName !== '') {
                return $companyName;
            }

            $ref = trim((string) ($admin->client_id ?? ''));

            return $ref !== '' ? $ref : 'Company';
        }

        $personal = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));
        if ($personal !== '') {
            return $personal;
        }

        $ref = trim((string) ($admin->client_id ?? ''));

        return $ref !== '' ? $ref : 'Client';
    }

    public static function billingNameForClientId(int $clientId): string
    {
        if ($clientId <= 0) {
            return '';
        }

        $admin = Admin::query()->with('company')->find($clientId);

        return $admin ? self::billingName($admin) : '';
    }

    /**
     * @param  object  $clientRow  stdClass from admins query used in PDF views
     */
    public static function applyToClientRow(object $clientRow): void
    {
        $clientId = (int) ($clientRow->id ?? 0);
        $name = self::billingNameForClientId($clientId);
        if ($name === '') {
            $name = trim(($clientRow->first_name ?? '') . ' ' . ($clientRow->last_name ?? ''));
        }

        $clientRow->bill_to_name = $name;
    }
}