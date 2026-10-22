<?php

namespace App\Support;

class MatterStreamHelper
{
    public static function streamLabel(?string $stream): string
    {
        if ($stream === null || $stream === '') {
            return '';
        }
        $streams = config('matter_streams.streams', []);

        return $streams[$stream] ?? $stream;
    }

    /**
     * @return array<string, string> value => label
     */
    public static function partyRolesForStream(?string $stream): array
    {
        $byStream = config('matter_streams.party_roles_by_stream', []);
        if ($stream && isset($byStream[$stream])) {
            return $byStream[$stream];
        }

        return $byStream['general'] ?? [];
    }

    public static function isValidPartyRole(?string $stream, ?string $role): bool
    {
        $resolved = self::resolvePartyRoleKey($stream, $role);

        return $resolved !== null && $resolved !== '';
    }

    /**
     * Accept stored keys or display labels from the UI (e.g. "Respondent" → respondent).
     */
    public static function resolvePartyRoleKey(?string $stream, ?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $role = trim($role);
        if ($role === '') {
            return '';
        }

        $roles = self::partyRolesForStream($stream);
        if (isset($roles[$role])) {
            return $role;
        }

        $lower = strtolower($role);
        if (isset($roles[$lower])) {
            return $lower;
        }

        foreach ($roles as $key => $label) {
            if (strcasecmp((string) $label, $role) === 0) {
                return (string) $key;
            }
        }

        return $role;
    }
}
