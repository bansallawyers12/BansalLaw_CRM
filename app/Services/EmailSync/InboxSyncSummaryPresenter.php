<?php

namespace App\Services\EmailSync;

/**
 * User-facing labels for inbox sync run summaries (internal keys stay "skipped").
 */
class InboxSyncSummaryPresenter
{
    public const ALREADY_IN_CRM_LABEL = 'already_in_crm';

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public static function withPresentationAliases(array $summary): array
    {
        $summary['total_already_in_crm'] = self::totalAlreadyInCrm($summary);

        $mailboxes = $summary['mailboxes'] ?? [];
        if (is_array($mailboxes)) {
            foreach ($mailboxes as $mailboxEmail => $result) {
                if (! is_array($result)) {
                    continue;
                }
                $result['already_in_crm'] = self::mailboxAlreadyInCrm($result);
                $mailboxes[$mailboxEmail] = $result;
            }
            $summary['mailboxes'] = $mailboxes;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function totalAlreadyInCrm(array $summary): int
    {
        if (array_key_exists('total_already_in_crm', $summary)) {
            return (int) $summary['total_already_in_crm'];
        }

        return (int) ($summary['total_skipped'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $mailboxResult
     */
    public static function mailboxAlreadyInCrm(array $mailboxResult): int
    {
        if (array_key_exists('already_in_crm', $mailboxResult)) {
            return (int) $mailboxResult['already_in_crm'];
        }

        return (int) ($mailboxResult['skipped'] ?? 0);
    }

    public static function formatMailboxConsoleLine(string $mailboxEmail, array $mailboxResult): string
    {
        $imported = (int) ($mailboxResult['imported'] ?? 0);
        $alreadyInCrm = self::mailboxAlreadyInCrm($mailboxResult);
        $failed = (int) ($mailboxResult['failed'] ?? 0);

        return sprintf(
            '%s: imported=%d, %s=%d, failed=%d',
            $mailboxEmail,
            $imported,
            self::ALREADY_IN_CRM_LABEL,
            $alreadyInCrm,
            $failed
        );
    }

    public static function formatTotalsConsoleLine(int $imported, int $alreadyInCrm, int $failed): string
    {
        return sprintf(
            'Done. Imported: %d, Already in CRM: %d, Failed: %d',
            $imported,
            $alreadyInCrm,
            $failed
        );
    }

    public static function formatTotalsDetail(int $imported, int $alreadyInCrm, int $failed): string
    {
        return sprintf(
            'Imported: %d, Already in CRM: %d, Failed: %d',
            $imported,
            $alreadyInCrm,
            $failed
        );
    }

    public static function formatAdminFlashMessage(int $imported, int $alreadyInCrm, int $failed): string
    {
        return 'Sync complete. '.self::formatTotalsDetail($imported, $alreadyInCrm, $failed);
    }
}
