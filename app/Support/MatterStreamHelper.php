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
        if ($role === null || $role === '') {
            return true;
        }
        $allowed = array_keys(self::partyRolesForStream($stream));

        return in_array($role, $allowed, true);
    }
}
