<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill matter assignees only where not already stored (NULL or 0).
 * Only rows with created_at before today 00:00:00 (app timezone) or created_at NULL.
 * Resolves staff.id from email (case-insensitive). Does not overwrite non-empty values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters') || ! Schema::hasTable('staff')) {
            return;
        }

        $hasCreatedAt = Schema::hasColumn('client_matters', 'created_at');
        $startOfToday = today();

        $lpId = $this->resolveStaffIdByEmail('Ajay@bansallawyers.com.au');
        $prId = $this->resolveStaffIdByEmail('michael@bansallawyers.com.au');
        $paId = $this->resolveStaffIdByEmail('Admin@bansallawyers.com.au');

        if ($lpId !== null && Schema::hasColumn('client_matters', 'sel_legal_practitioner')) {
            $q = DB::table('client_matters')
                ->where(function ($q) {
                    $q->whereNull('sel_legal_practitioner')->orWhere('sel_legal_practitioner', 0);
                });
            $this->applyCreatedBeforeToday($q, $hasCreatedAt, $startOfToday);
            $q->update(['sel_legal_practitioner' => $lpId]);
        }

        if ($prId !== null && Schema::hasColumn('client_matters', 'sel_person_responsible')) {
            $q = DB::table('client_matters')
                ->where(function ($q) {
                    $q->whereNull('sel_person_responsible')->orWhere('sel_person_responsible', 0);
                });
            $this->applyCreatedBeforeToday($q, $hasCreatedAt, $startOfToday);
            $q->update(['sel_person_responsible' => $prId]);
        }

        if ($paId !== null && Schema::hasColumn('client_matters', 'sel_person_assisting')) {
            $q = DB::table('client_matters')
                ->where(function ($q) {
                    $q->whereNull('sel_person_assisting')->orWhere('sel_person_assisting', 0);
                });
            $this->applyCreatedBeforeToday($q, $hasCreatedAt, $startOfToday);
            $q->update(['sel_person_assisting' => $paId]);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     * @param  \Illuminate\Support\Carbon  $startOfToday
     */
    private function applyCreatedBeforeToday($q, bool $hasCreatedAt, $startOfToday): void
    {
        if (! $hasCreatedAt) {
            return;
        }
        $q->where(function ($sub) use ($startOfToday) {
            $sub->whereNull('created_at')->orWhere('created_at', '<', $startOfToday);
        });
    }

    /**
     * Prefer active staff (status = 1) when multiple rows share the same email.
     */
    private function resolveStaffIdByEmail(string $email): ?int
    {
        $norm = strtolower(trim($email));
        if ($norm === '') {
            return null;
        }

        $id = DB::table('staff')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->orderByDesc('status')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function down(): void
    {
        // Intentionally empty: cannot reliably undo without knowing prior NULL vs intentional 0.
    }
};
