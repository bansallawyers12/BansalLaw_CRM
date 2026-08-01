<?php

/**
 * Manual QA matrix for conflict-check Phase 0 scenarios.
 *
 * Usage:
 *   php scripts/dev/conflict-check-phase0-matrix.php          # run + rollback each scenario
 *   php scripts/dev/conflict-check-phase0-matrix.php --keep   # persist CCP0 rows for UI inspection
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ConflictCheckService;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConflictCheckPhase0Fixtures;

$keep = in_array('--keep', $argv ?? [], true);
$service = new ConflictCheckService();

$scenarios = [
    '1_linked_individual_same_matter' => fn () => (new ConflictCheckPhase0Fixtures())->withLinkedIndividualOnSubjectMatter(),
    '2_linked_company_same_matter' => fn () => (new ConflictCheckPhase0Fixtures())->withLinkedCompanyOnSubjectMatter(),
    '3_name_only_cross_client' => fn () => (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient(),
    '4_shared_linked_opposing_party_two_clients' => fn () => (new ConflictCheckPhase0Fixtures())->withSharedLinkedOpposingPartyOnBothClients(),
    '5_subject_only_no_parties' => fn () => (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties(),
];

$rows = [];

echo str_repeat('=', 72) . PHP_EOL;
echo 'Conflict Check Phase 0 — manual matrix' . PHP_EOL;
echo 'Note: on a populated dev DB, match_count may exceed PHPUnit baselines (ambient data).' . PHP_EOL;
echo '      fixture_scoped counts hard + informational matches on the fixture other_client (scenarios 3–4).' . PHP_EOL;
echo str_repeat('=', 72) . PHP_EOL;

foreach ($scenarios as $key => $builder) {
    DB::beginTransaction();

    try {
        /** @var ConflictCheckPhase0Fixtures $fixture */
        $fixture = $builder();
        $data = $fixture->get();

        $result = $service->run(
            $data['subject_client'],
            (int) $data['subject_matter']->id
        );

        $otherClientId = (int) $data['other_client']->id;
        $allResultMatches = array_merge(
            $result['matches'] ?? [],
            $result['informational_matches'] ?? []
        );
        $fixtureScoped = array_values(array_filter(
            $allResultMatches,
            fn (array $m) => (int) ($m['client_id'] ?? 0) === $otherClientId
        ));

        $rows[] = [
            'scenario' => $key,
            'subject_client_id' => $data['subject_client']->id,
            'subject_matter_id' => $data['subject_matter']->id,
            'match_count' => $result['match_count'],
            'informational_count' => $result['informational_count'] ?? 0,
            'fixture_scoped_match_count' => count($fixtureScoped),
            'suggested_outcome' => $result['suggested_outcome'],
            'party_count' => $result['party_count'],
            'warnings' => $result['warnings'],
            'match_sources' => array_values(array_unique(array_column($result['matches'], 'source'))),
            'fixture_match_sources' => array_values(array_unique(array_column($fixtureScoped, 'source'))),
        ];

        if ($keep) {
            DB::commit();
        } else {
            DB::rollBack();
        }
    } catch (Throwable $e) {
        DB::rollBack();
        fwrite(STDERR, "Matrix run failed on {$key}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

foreach ($rows as $row) {
    echo PHP_EOL . $row['scenario'] . PHP_EOL;
    echo '  subject_client_id: ' . $row['subject_client_id'] . PHP_EOL;
    echo '  subject_matter_id: ' . $row['subject_matter_id'] . PHP_EOL;
        echo '  match_count:       ' . $row['match_count'] . PHP_EOL;
        echo '  informational:     ' . ($row['informational_count'] ?? 0) . PHP_EOL;
        echo '  fixture_scoped:    ' . $row['fixture_scoped_match_count'] . PHP_EOL;
    echo '  suggested_outcome: ' . $row['suggested_outcome'] . PHP_EOL;
    echo '  party_count:       ' . $row['party_count'] . PHP_EOL;
    echo '  match_sources:     ' . (empty($row['match_sources']) ? '(none)' : implode(', ', $row['match_sources'])) . PHP_EOL;
    echo '  fixture_sources:   ' . (empty($row['fixture_match_sources']) ? '(none)' : implode(', ', $row['fixture_match_sources'])) . PHP_EOL;
    if (! empty($row['warnings'])) {
        echo '  warnings:' . PHP_EOL;
        foreach ($row['warnings'] as $warning) {
            echo '    - ' . $warning . PHP_EOL;
        }
    }
}

echo PHP_EOL . str_repeat('=', 72) . PHP_EOL;

if ($keep) {
    echo 'Committed CCP0 fixture rows per scenario (--keep). Inspect clients/matter parties in CRM.' . PHP_EOL;
} else {
    echo 'Each scenario was rolled back after run (default). Use --keep to persist for UI QA.' . PHP_EOL;
}
