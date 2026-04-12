<?php

namespace App\Support;

/**
 * S3 keys for matter documents use {clientUniqueId}/{doc_type}/{myfile_key}.
 * Legacy rows used doc_type "visa"; after migration to "matter", objects may still live under visa/.
 */
class DocumentMatterStoragePath
{
    /**
     * Candidate S3 key paths to try in order (first existing wins).
     *
     * @return list<string>
     */
    public static function s3KeyCandidates(string $clientUniqueId, string $docType, string $myfileKey): array
    {
        $base = $clientUniqueId . '/' . $docType . '/' . $myfileKey;
        $keys = [$base];
        if ($docType === 'matter') {
            $keys[] = $clientUniqueId . '/visa/' . $myfileKey;
        }

        return $keys;
    }
}
