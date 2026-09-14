<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair invoices that appear on the Timeline but not on Billing:
 * - parent row missing while line items exist
 * - parent/line items saved with null client_matter_id on a single-matter client
 */
class RepairMissingInvoices extends Command
{
    protected $signature = 'invoices:repair-missing
                            {invoice_no? : Invoice number e.g. INV-007}
                            {--client= : Client reference (client_id) or numeric admins.id}
                            {--dry-run : Report only, do not write}
                            {--force : Skip confirmation}';

    protected $description = 'Repair orphaned / null-matter invoices so they appear on the Billing tab';

    public function handle(): int
    {
        $invoiceNo = $this->argument('invoice_no');
        $clientOpt = $this->option('client');
        $dryRun = (bool) $this->option('dry-run');

        $clientId = null;
        if ($clientOpt !== null && $clientOpt !== '') {
            if (ctype_digit((string) $clientOpt)) {
                $clientId = (int) $clientOpt;
            } else {
                $clientId = DB::table('admins')
                    ->where('client_id', $clientOpt)
                    ->whereIn('type', ['client', 'lead'])
                    ->value('id');
                if (! $clientId) {
                    $this->error("Client reference [{$clientOpt}] not found.");

                    return self::FAILURE;
                }
            }
        }

        if (! $invoiceNo && ! $clientId) {
            // Default to recent "added invoice" activities with no matching receipt.
            $invoiceNo = null;
        }

        $targets = $this->resolveTargets($invoiceNo, $clientId);
        if ($targets->isEmpty()) {
            $this->warn('No repair targets found.');

            return self::SUCCESS;
        }

        $this->table(
            ['invoice_no', 'client_id', 'parent', 'lines', 'matter', 'activity', 'action'],
            $targets->map(fn ($t) => [
                $t['invoice_no'],
                $t['client_id'],
                $t['parent'] ? 'yes' : 'no',
                $t['line_count'],
                $t['matter_id'] ?? 'null',
                $t['has_activity'] ? 'yes' : 'no',
                $t['action'],
            ])->all()
        );

        if ($dryRun) {
            $this->info('Dry run only — no changes made.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these repairs?', true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($targets as $target) {
            if ($target['action'] === 'none') {
                continue;
            }
            DB::transaction(function () use ($target, &$fixed) {
                if ($target['action'] === 'create_parent') {
                    $this->createParentFromLines($target);
                    $fixed++;
                } elseif ($target['action'] === 'attach_matter') {
                    $this->attachMatter($target);
                    $fixed++;
                } elseif ($target['action'] === 'create_parent_and_attach') {
                    $this->createParentFromLines($target);
                    $this->attachMatter(array_merge($target, [
                        'matter_id' => $target['suggested_matter_id'],
                    ]));
                    $fixed++;
                }
            });
            $this->info("Repaired {$target['invoice_no']} for client {$target['client_id']} ({$target['action']}).");
        }

        $this->info("Done. Repaired {$fixed} invoice(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolveTargets(?string $invoiceNo, ?int $clientId)
    {
        $query = DB::table('activities_logs')
            ->select('id', 'client_id', 'subject', 'created_at')
            ->where('subject', 'ILIKE', '%added invoice%Reference no-%')
            ->orderByDesc('id');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($invoiceNo) {
            $query->where('subject', 'ILIKE', '%'.$invoiceNo.'%');
        } else {
            $query->limit(50);
        }

        $activities = $query->get();
        $targets = collect();

        foreach ($activities as $activity) {
            if (! preg_match('/Reference no-\s*(INV-\d+)/i', (string) $activity->subject, $m)) {
                continue;
            }
            $no = strtoupper($m[1]);
            if ($invoiceNo && strtoupper($invoiceNo) !== $no) {
                continue;
            }

            $key = $activity->client_id.'|'.$no;
            if ($targets->has($key)) {
                continue;
            }

            $parent = DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where('client_id', $activity->client_id)
                ->where(function ($q) use ($no) {
                    $q->where('invoice_no', $no)->orWhere('trans_no', $no);
                })
                ->orderByDesc('id')
                ->first();

            $lines = DB::table('account_all_invoice_receipts')
                ->where('receipt_type', 3)
                ->where('client_id', $activity->client_id)
                ->where(function ($q) use ($no) {
                    $q->where('invoice_no', $no)->orWhere('trans_no', $no);
                })
                ->orderBy('id')
                ->get();

            $matterIds = DB::table('client_matters')
                ->where('client_id', $activity->client_id)
                ->orderBy('id')
                ->pluck('id');
            $suggestedMatter = $matterIds->count() === 1 ? (int) $matterIds->first() : null;

            $parentMatter = $parent->client_matter_id ?? null;
            $lineMatter = $lines->first()->client_matter_id ?? null;
            $effectiveMatter = $parentMatter ?? $lineMatter;

            $action = 'none';
            if (! $parent && $lines->isNotEmpty()) {
                $action = $suggestedMatter && $effectiveMatter === null
                    ? 'create_parent_and_attach'
                    : 'create_parent';
            } elseif ($parent && $effectiveMatter === null && $suggestedMatter) {
                $action = 'attach_matter';
            } elseif (! $parent && $lines->isEmpty()) {
                $action = 'none'; // activity-only; cannot recreate amounts
            }

            $targets->put($key, [
                'invoice_no' => $no,
                'client_id' => (int) $activity->client_id,
                'parent' => $parent,
                'lines' => $lines,
                'line_count' => $lines->count(),
                'matter_id' => $effectiveMatter,
                'suggested_matter_id' => $suggestedMatter,
                'has_activity' => true,
                'action' => $action,
            ]);
        }

        // Also scan null-matter parents for the client even without activity match.
        if ($clientId) {
            $nullParents = DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where('client_id', $clientId)
                ->whereNull('client_matter_id')
                ->when($invoiceNo, fn ($q) => $q->where(function ($inner) use ($invoiceNo) {
                    $inner->where('invoice_no', $invoiceNo)->orWhere('trans_no', $invoiceNo);
                }))
                ->get();

            $matterIds = DB::table('client_matters')->where('client_id', $clientId)->orderBy('id')->pluck('id');
            $suggestedMatter = $matterIds->count() === 1 ? (int) $matterIds->first() : null;

            foreach ($nullParents as $parent) {
                $no = $parent->invoice_no ?: $parent->trans_no;
                if (! $no) {
                    continue;
                }
                $key = $clientId.'|'.$no;
                if ($targets->has($key) && $targets[$key]['action'] !== 'none') {
                    continue;
                }
                if (! $suggestedMatter) {
                    continue;
                }
                $targets->put($key, [
                    'invoice_no' => $no,
                    'client_id' => $clientId,
                    'parent' => $parent,
                    'lines' => collect(),
                    'line_count' => 0,
                    'matter_id' => null,
                    'suggested_matter_id' => $suggestedMatter,
                    'has_activity' => $targets->has($key) ? $targets[$key]['has_activity'] : false,
                    'action' => 'attach_matter',
                ]);
            }
        }

        return $targets->values()->filter(fn ($t) => $invoiceNo ? true : $t['action'] !== 'none');
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function createParentFromLines(array $target): void
    {
        $lines = $target['lines'];
        $first = $lines->first();
        $receiptId = (int) $first->receipt_id;
        $matterId = $first->client_matter_id ?? $target['suggested_matter_id'] ?? null;

        $total = 0.0;
        foreach ($lines as $line) {
            $amount = (float) $line->withdraw_amount;
            if (($line->payment_type ?? '') === 'Discount') {
                $total -= $amount;
            } else {
                $total += $amount;
            }
        }

        $exists = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('receipt_id', $receiptId)
            ->where('client_id', $target['client_id'])
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('account_client_receipts')->insert([
            'user_id' => $first->user_id ?? 0,
            'client_id' => $target['client_id'],
            'client_matter_id' => $matterId,
            'receipt_id' => $receiptId,
            'receipt_type' => 3,
            'trans_date' => $first->trans_date,
            'entry_date' => $first->entry_date,
            'gst_included' => $first->gst_included,
            'payment_type' => $first->payment_type,
            'trans_no' => $target['invoice_no'],
            'description' => $first->description,
            'withdraw_amount' => $total,
            'balance_amount' => $total,
            'invoice_no' => $target['invoice_no'],
            'save_type' => $first->save_type ?? 'final',
            'invoice_status' => $first->invoice_status ?? 0,
            'validate_receipt' => 0,
            'void_invoice' => 0,
            'hubdoc_sent' => 0,
            'created_at' => $first->created_at ?? now(),
            'updated_at' => now(),
        ]);

        if ($matterId) {
            DB::table('account_all_invoice_receipts')
                ->where('receipt_type', 3)
                ->where('receipt_id', $receiptId)
                ->where('client_id', $target['client_id'])
                ->whereNull('client_matter_id')
                ->update(['client_matter_id' => $matterId, 'updated_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function attachMatter(array $target): void
    {
        $matterId = $target['suggested_matter_id'] ?? $target['matter_id'] ?? null;
        if (! $matterId) {
            return;
        }

        DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('client_id', $target['client_id'])
            ->where(function ($q) use ($target) {
                $q->where('invoice_no', $target['invoice_no'])
                    ->orWhere('trans_no', $target['invoice_no']);
            })
            ->whereNull('client_matter_id')
            ->update(['client_matter_id' => $matterId, 'updated_at' => now()]);

        DB::table('account_all_invoice_receipts')
            ->where('receipt_type', 3)
            ->where('client_id', $target['client_id'])
            ->where(function ($q) use ($target) {
                $q->where('invoice_no', $target['invoice_no'])
                    ->orWhere('trans_no', $target['invoice_no']);
            })
            ->whereNull('client_matter_id')
            ->update(['client_matter_id' => $matterId, 'updated_at' => now()]);
    }
}
