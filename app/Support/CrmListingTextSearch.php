<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Shared “keyword” search for CRM person / matter listings:
 * name parts, client_id, admins id (numeric token), matter ref, matter type nick/title.
 * Terms with “ / ” (e.g. DURB2600012 / CIV_6) are split; each piece must match (AND).
 */
final class CrmListingTextSearch
{
    /**
     * @return list<string>
     */
    public static function splitSearchTokens(string $rawTerm): array
    {
        $trimmed = trim($rawTerm);
        if ($trimmed === '') {
            return [];
        }
        if (!str_contains($trimmed, '/')) {
            return [$trimmed];
        }
        $parts = preg_split('#\s*/\s*#u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];
        foreach ($parts as $part) {
            $t = trim((string) $part);
            if ($t !== '') {
                $tokens[] = $t;
            }
        }

        return $tokens !== [] ? $tokens : [$trimmed];
    }

    private static function likePatternForTerm(string $term): string
    {
        $lower = mb_strtolower($term, 'UTF-8');
        $inner = addcslashes($lower, '%_\\');

        return '%' . $inner . '%';
    }

    public static function applyToAdminsTableQuery(Builder $query, string $rawTerm): void
    {
        foreach (self::splitSearchTokens($rawTerm) as $term) {
            $query->where(function (Builder $q) use ($term) {
                self::applySingleTokenAdminsTableQuery($q, $term);
            });
        }
    }

    private static function applySingleTokenAdminsTableQuery(Builder $q, string $term): void
    {
        $likeToken = self::likePatternForTerm($term);

        $q->whereRaw('LOWER(first_name) LIKE ?', [$likeToken])
            ->orWhereRaw('LOWER(last_name) LIKE ?', [$likeToken])
            ->orWhereRaw(
                "LOWER(TRIM(COALESCE(first_name, '')) || ' ' || TRIM(COALESCE(last_name, ''))) LIKE ?",
                [$likeToken]
            )
            ->orWhereRaw('LOWER(TRIM(COALESCE(client_id, \'\'))) LIKE ?', [$likeToken]);

        $trim = preg_replace('/\s+/', '', $term);
        if ($trim !== '' && preg_match('/^\d+$/', $trim)) {
            $q->orWhere('id', (int) $trim);
        }

        $q->orWhereExists(function ($sub) use ($likeToken) {
            $sub->select(DB::raw('1'))
                ->from('client_matters')
                ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
                ->whereColumn('client_matters.client_id', 'admins.id')
                ->where(function ($sq) use ($likeToken) {
                    $sq->whereRaw('LOWER(client_matters.client_unique_matter_no) LIKE ?', [$likeToken])
                        ->orWhereRaw('LOWER(COALESCE(matters.nick_name, \'\')) LIKE ?', [$likeToken])
                        ->orWhereRaw('LOWER(COALESCE(matters.title, \'\')) LIKE ?', [$likeToken]);
                });
        });
    }

    /**
     * Search on the matters join query (clientsmatterslist / closed matters): client name, client_id,
     * matter number, matter nick/title, admins.id / cm.id when the token is purely numeric.
     */
    public static function applyToClientMattersJoinQuery(
        QueryBuilder $query,
        string $rawTerm,
        string $adAlias = 'ad',
        string $cmAlias = 'cm',
        string $maAlias = 'ma'
    ): void {
        foreach (self::splitSearchTokens($rawTerm) as $term) {
            $query->where(function (QueryBuilder $q) use ($term, $adAlias, $cmAlias, $maAlias) {
                self::applySingleTokenClientMattersJoinQuery($q, $term, $adAlias, $cmAlias, $maAlias);
            });
        }
    }

    private static function applySingleTokenClientMattersJoinQuery(
        QueryBuilder $q,
        string $term,
        string $adAlias,
        string $cmAlias,
        string $maAlias
    ): void {
        $likeToken = self::likePatternForTerm($term);

        $q->whereRaw("LOWER({$adAlias}.first_name) LIKE ?", [$likeToken])
            ->orWhereRaw("LOWER({$adAlias}.last_name) LIKE ?", [$likeToken])
            ->orWhereRaw(
                "LOWER(TRIM(COALESCE({$adAlias}.first_name, '')) || ' ' || TRIM(COALESCE({$adAlias}.last_name, ''))) LIKE ?",
                [$likeToken]
            )
            ->orWhereRaw("LOWER(TRIM(COALESCE({$adAlias}.client_id, ''))) LIKE ?", [$likeToken])
            ->orWhereRaw("LOWER({$cmAlias}.client_unique_matter_no) LIKE ?", [$likeToken])
            ->orWhereRaw("LOWER(COALESCE({$maAlias}.nick_name, '')) LIKE ?", [$likeToken])
            ->orWhereRaw("LOWER(COALESCE({$maAlias}.title, '')) LIKE ?", [$likeToken]);

        $trim = preg_replace('/\s+/', '', $term);
        if ($trim !== '' && preg_match('/^\d+$/', $trim)) {
            $tid = (int) $trim;
            $q->orWhere("{$adAlias}.id", $tid)
                ->orWhere("{$cmAlias}.id", $tid);
        }
    }
}
