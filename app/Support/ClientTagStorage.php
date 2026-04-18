<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client/lead tags are stored on admins.tagname as JSON: {"n":["Normal"],"r":["Red"]}.
 */
final class ClientTagStorage
{
    public const KEY_NORMAL = 'n';

    public const KEY_RED = 'r';

    /**
     * @return array{0: string[], 1: string[]} [normal, red]
     */
    public static function decode(?string $tagname): array
    {
        $tagname = trim((string) ($tagname ?? ''));
        if ($tagname === '') {
            return [[], []];
        }

        if (str_starts_with($tagname, '{')) {
            $decoded = json_decode($tagname, true);
            if (is_array($decoded)) {
                $n = $decoded[self::KEY_NORMAL] ?? [];
                $r = $decoded[self::KEY_RED] ?? [];

                return [self::normalizeList(is_array($n) ? $n : []), self::normalizeList(is_array($r) ? $r : [])];
            }

            return [[], []];
        }

        // Legacy: comma-separated numeric IDs and/or names (pre–JSON storage).
        $parts = array_filter(array_map('trim', explode(',', $tagname)));
        $normal = [];
        $red = [];
        if (! Schema::hasTable('tags')) {
            foreach ($parts as $p) {
                if ($p !== '' && ! ctype_digit($p)) {
                    $normal[] = $p;
                }
            }

            return [self::normalizeList($normal), []];
        }

        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            if (ctype_digit($p)) {
                $row = DB::table('tags')->where('id', (int) $p)->first();
                if ($row) {
                    if (($row->tag_type ?? 'normal') === 'red') {
                        $red[] = (string) $row->name;
                    } else {
                        $normal[] = (string) $row->name;
                    }
                }

                continue;
            }
            $row = DB::table('tags')->where('name', $p)->first();
            if ($row) {
                if (($row->tag_type ?? 'normal') === 'red') {
                    $red[] = (string) $row->name;
                } else {
                    $normal[] = (string) $row->name;
                }

                continue;
            }
            $normal[] = $p;
        }

        return [self::normalizeList($normal), self::normalizeList($red)];
    }

    /**
     * @param  string[]  $normal
     * @param  string[]  $red
     */
    public static function encode(array $normal, array $red): ?string
    {
        $n = self::normalizeList($normal);
        $r = self::normalizeList($red);
        if ($n === [] && $r === []) {
            return null;
        }

        return json_encode(
            [self::KEY_NORMAL => $n, self::KEY_RED => $r],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @param  string[]  $list
     * @return string[]
     */
    public static function normalizeList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            $s = is_string($item) ? trim($item) : trim((string) $item);
            if ($s !== '' && ! in_array($s, $out, true)) {
                $out[] = $s;
            }
        }

        return $out;
    }
}
