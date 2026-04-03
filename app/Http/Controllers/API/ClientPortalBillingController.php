<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientPortalBillingController extends Controller
{
    /**
     * List of Billing (Invoices)
     * GET /api/billing/list
     *
     * Query params (mandatory):
     * - client_matter_id: integer, required - Filter by client matter
     *
     * Query params (optional):
     * - page: integer, default 1
     * - per_page: integer, default 10
     *
     * Returns invoices where:
     * - client_portal_sent = 1 (invoice sent to client portal)
     * - invoice_status = 0 (Pending) OR invoice_status = 1 (Paid)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        try {
            $request->validate([
                'client_matter_id' => 'required|integer|min:1',
            ]);

            $admin = $request->user();
            $clientId = $admin->id;
            $clientMatterId = $request->get('client_matter_id');
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 10);
            $perPage = min(max($perPage, 1), 100); // Clamp between 1 and 100

            $baseQuery = DB::table('account_client_receipts')
                ->select(
                    'trans_no',
                    'receipt_id',
                    DB::raw('MAX(COALESCE(balance_amount, withdraw_amount, 0)) as balance_amount'),
                    DB::raw('MAX(COALESCE(invoice_status, 0)) as invoice_status'),
                    DB::raw('MAX(description) as description'),
                    DB::raw('MAX(trans_date) as latest_trans_date'),
                    DB::raw('MAX(client_portal_sent_at) as client_portal_sent_at'),
                    DB::raw('MAX(client_matter_id) as client_matter_id')
                )
                ->where('client_id', $clientId)
                ->where('client_matter_id', $clientMatterId)
                ->where('receipt_type', 3)
                ->where('client_portal_sent', 1)
                ->whereIn('invoice_status', [0, 1])
                ->where(function ($query) {
                    $query->whereNull('void_invoice')
                        ->orWhere('void_invoice', 0);
                })
                ->groupBy('trans_no', 'receipt_id')
                ->orderByDesc(DB::raw('MAX(client_portal_sent_at)'))
                ->orderByDesc(DB::raw('MAX(trans_date)'));

            $total = DB::table(DB::raw('(' . $baseQuery->toSql() . ') as sub'))
                ->mergeBindings($baseQuery)
                ->count();

            $offset = ($page - 1) * $perPage;
            $invoices = (clone $baseQuery)
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $statusMap = [0 => 'Pending', 1 => 'Paid'];

            $invoices = $invoices->map(function ($invoice) use ($statusMap) {
                return [
                    'trans_no' => $invoice->trans_no,
                    'receipt_id' => $invoice->receipt_id,
                    'balance_amount' => (float) $invoice->balance_amount,
                    'invoice_status' => (int) $invoice->invoice_status,
                    'status' => $statusMap[$invoice->invoice_status] ?? 'Unknown',
                    'description' => $invoice->description,
                    'trans_date' => $invoice->latest_trans_date,
                    'client_portal_sent_at' => $invoice->client_portal_sent_at,
                    'client_matter_id' => $invoice->client_matter_id,
                ];
            });

            $lastPage = (int) ceil($total / $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoices' => $invoices,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                        'from' => $total > 0 ? $offset + 1 : null,
                        'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Billing List API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch billing list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Billing invoice Update (Google Pay / Apple Pay / Stripe)
     * POST /api/billing/invoice-update
     *
     * Input (JSON body):
     * - billing_invoice_id: receipt_id from account_client_receipts
     * - client_matter_id: client matter ID (required)
     * - payment_type: "google_pay", "apple_pay", or "stripe"
     * - payment_token: unique token value (e.g. Stripe PaymentIntent id pi_...)
     * - payment_status: "completed" or "failed"
     *
     * Lookup by receipt_id and client_matter_id. When payment_status is "completed": updates invoice_status to 1 and saves payment_token (and payment_type).
     * When payment_status is "failed": no update.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInvoice(Request $request)
    {
        try {
            $validated = $request->validate([
                'billing_invoice_id' => 'required|integer|min:1',
                'client_matter_id' => 'required|integer|min:1',
                'payment_type' => 'required|string|in:google_pay,apple_pay,stripe',
                'payment_token' => 'required|string|max:500',
                'payment_status' => 'required|string|in:completed,failed',
            ]);

            $clientId = $request->user()->id;
            $receiptId = (int) $validated['billing_invoice_id'];
            $clientMatterId = (int) $validated['client_matter_id'];

            $exists = DB::table('account_client_receipts')
                ->where('receipt_id', $receiptId)
                ->where('client_matter_id', $clientMatterId)
                ->where('client_id', $clientId)
                ->where('receipt_type', 3)
                ->where('client_portal_sent', 1)
                ->limit(1)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or not accessible.',
                ], 404);
            }

            if ($validated['payment_status'] === 'completed') {
                $updated = DB::table('account_client_receipts')
                    ->where('receipt_id', $receiptId)
                    ->where('client_matter_id', $clientMatterId)
                    ->where('client_id', $clientId)
                    ->update([
                        'invoice_status' => 1,
                        'client_portal_payment_token' => $validated['payment_token'],
                        'client_portal_payment_type' => $validated['payment_type'],
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Invoice payment recorded successfully.',
                    'data' => [
                        'receipt_id' => $receiptId,
                        'invoice_status' => 1,
                        'updated' => (bool) $updated,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment status is failed; no update performed.',
                'data' => [
                    'receipt_id' => $receiptId,
                    'payment_status' => 'failed',
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Billing invoice Update API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update billing invoice.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
