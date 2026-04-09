<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ensures a few placeholder staff rows exist for Person Responsible (role 12)
 * and Person Assisting (role 13) dropdowns when the real lists are empty or for demos.
 * firstOrCreate is idempotent (matched by email).
 */
final class EnsureDummyMatterStaff
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        $password = Hash::make(Str::random(40));

        $rows = [
            ['Demo', 'Person Responsible A', 'dummy.matter.pr-a@bansallaw-crm.internal', 12],
            ['Demo', 'Person Responsible B', 'dummy.matter.pr-b@bansallaw-crm.internal', 12],
            ['Demo', 'Person Assisting A', 'dummy.matter.pa-a@bansallaw-crm.internal', 13],
            ['Demo', 'Person Assisting B', 'dummy.matter.pa-b@bansallaw-crm.internal', 13],
        ];

        foreach ($rows as [$first, $last, $email, $role]) {
            try {
                Staff::firstOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => $first,
                        'last_name' => $last,
                        'password' => $password,
                        'role' => $role,
                        'status' => 1,
                    ]
                );
            } catch (Throwable) {
                // e.g. FK on role if user_roles row missing — skip silently
            }
        }
    }
}
