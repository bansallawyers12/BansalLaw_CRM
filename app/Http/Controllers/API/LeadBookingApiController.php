<?php

namespace App\Http\Controllers\API;

use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Services\ClientReferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LeadBookingApiController extends BaseController
{
    /**
     * Create a CRM lead (row in admins with type lead, plus primary email and phone rows).
     */
    public function storeLead(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'country_code' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:255'],
            'refer_by' => ['nullable', 'string', 'max:255'],
            'lead_status' => ['nullable', 'string', 'max:100'],
        ]);

        $hasFullName = trim((string) ($validated['full_name'] ?? '')) !== '';
        $hasFirstName = trim((string) ($validated['first_name'] ?? '')) !== '';
        if (! $hasFullName && ! $hasFirstName) {
            return $this->sendError('Provide either full_name or first_name (and optionally last_name).', [
                'full_name' => ['One of full_name or first_name must be non-empty.'],
            ], 422);
        }

        $email = strtolower(trim($validated['email']));
        $existing = Admin::whereIn('type', ['client', 'lead'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing) {
            return $this->sendError('A client or lead with this email already exists.', [
                'existing_admin_id' => $existing->id,
                'client_reference' => $existing->client_id,
            ], 422);
        }

        if ($hasFullName) {
            $parts = preg_split('/\s+/', trim($validated['full_name']), 2);
            $firstName = $parts[0] ?: 'Unknown';
            $lastName = $parts[1] ?? ($validated['last_name'] ?? null);
        } else {
            $firstName = $validated['first_name'];
            $lastName = $validated['last_name'] ?? null;
        }

        $phoneRaw = trim($validated['phone']);
        $phoneForStorage = str_starts_with($phoneRaw, '+') ? $phoneRaw : '+' . ltrim($phoneRaw, '0');
        $countryCode = $this->resolveCountryCode($phoneForStorage, $validated['country_code'] ?? null);

        try {
            $lead = DB::transaction(function () use ($firstName, $lastName, $email, $phoneForStorage, $countryCode, $validated) {
                $referenceService = app(ClientReferenceService::class);
                $reference = $referenceService->generateClientReference($firstName);

                $admin = new Admin();
                $admin->first_name = $firstName;
                $admin->last_name = $lastName;
                $admin->email = $email;
                $admin->phone = $phoneForStorage;
                $admin->country_code = $countryCode;
                $admin->client_counter = $reference['client_counter'];
                $admin->client_id = $reference['client_id'];
                $admin->type = 'lead';
                $admin->password = Hash::make('LEAD_PLACEHOLDER');
                $admin->status = '1';
                $admin->australian_study = 0;
                $admin->specialist_education = 0;
                $admin->regional_study = 0;
                $admin->is_archived = 0;

                if (Schema::hasColumn('admins', 'source') && isset($validated['source'])) {
                    $admin->source = $validated['source'];
                } elseif (Schema::hasColumn('admins', 'source')) {
                    $admin->source = 'API';
                }

                if (! empty($validated['refer_by'])) {
                    $admin->refer_by = $validated['refer_by'];
                }

                if (Schema::hasColumn('admins', 'lead_status')) {
                    $admin->lead_status = $validated['lead_status'] ?? 'new';
                }

                $admin->save();

                $systemUserId = (int) config('app.system_user_id', 1);

                ClientEmail::create([
                    'admin_id' => $systemUserId,
                    'client_id' => $admin->id,
                    'email_type' => 'Personal',
                    'email' => $email,
                    'is_verified' => false,
                ]);

                ClientContact::create([
                    'admin_id' => $systemUserId,
                    'client_id' => $admin->id,
                    'contact_type' => 'Personal',
                    'country_code' => $countryCode,
                    'phone' => $phoneForStorage,
                    'is_verified' => false,
                ]);

                return $admin;
            });
        } catch (\Throwable $e) {
            Log::error('API storeLead failed', [
                'message' => $e->getMessage(),
                'email' => $email,
            ]);

            return $this->sendError('Could not create lead.', [], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'data' => [
                'id' => $lead->id,
                'client_reference' => $lead->client_id,
                'type' => $lead->type,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
            ],
        ], 201);
    }

    /**
     * Insert a row into booking_appointments (CRM calendar / booking table).
     */
    public function storeBookingAppointment(Request $request)
    {
        $validated = $request->validate([
            'bansal_appointment_id' => [
                'nullable',
                'integer',
                Rule::unique('booking_appointments', 'bansal_appointment_id'),
            ],
            'order_hash' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:admins,id'],
            'consultant_id' => ['nullable', 'integer', 'exists:appointment_consultants,id'],
            'assigned_by_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_timezone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'appointment_datetime' => ['required', 'date'],
            'timeslot_full' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:480'],
            'location' => ['required', Rule::in(['melbourne', 'adelaide'])],
            'inperson_address' => ['nullable', 'integer', 'in:1,2'],
            'meeting_type' => ['nullable', Rule::in(['in_person', 'phone', 'video'])],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'service_id' => ['nullable', 'integer', 'in:1,2,3'],
            'noe_id' => [
                'nullable',
                'integer',
                Rule::in(array_values(array_unique(array_merge(
                    array_column(config('booking_nature_of_enquiry.crm'), 'id'),
                    [8]
                )))),
            ],
            'enquiry_type' => ['nullable', 'string', 'max:100'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'enquiry_details' => ['nullable', 'string'],
            'status' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (is_int($value)) {
                        if ($value < 0 || $value > 11) {
                            $fail('The status must be a website code between 0 and 11, or a CRM status slug.');
                        }

                        return;
                    }
                    if (is_string($value) && $value !== '' && ctype_digit($value)) {
                        $i = (int) $value;
                        if ($i < 0 || $i > 11) {
                            $fail('The status must be a website code between 0 and 11, or a CRM status slug.');
                        }

                        return;
                    }
                    if (! is_string($value)) {
                        $fail('The status must be a website code between 0 and 11, or a CRM status slug.');

                        return;
                    }
                    $crm = ['pending', 'paid', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled'];
                    if (! in_array($value, $crm, true)) {
                        $fail('The status must be a website code between 0 and 11, or a CRM status slug.');
                    }
                },
            ],
            'confirmed_at' => ['nullable', 'date'],
            'is_paid' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', Rule::in(['pending', 'completed', 'failed', 'refunded'])],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'admin_notes' => ['nullable', 'string'],
            'sync_status' => ['nullable', Rule::in(['new', 'synced', 'error'])],
            'sync_error' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:admins,id'],
        ]);

        $bansalId = $validated['bansal_appointment_id'] ?? null;
        if ($bansalId === null) {
            $bansalId = $this->allocateTemporaryBansalAppointmentId();
        }

        $websiteStatusCode = $this->resolveWebsiteStatusCodeFromRequest($validated['status'] ?? null);
        $status = $validated['status'] ?? 'pending';
        if ($websiteStatusCode !== null) {
            $map = config('booking_website_status_map.map.' . $websiteStatusCode);
            if (is_array($map) && isset($map['status'])) {
                $status = $map['status'];
            }
        }

        $clientTimezone = $validated['client_timezone']
            ?? $validated['timezone']
            ?? 'Australia/Melbourne';

        $durationMinutes = $validated['duration_minutes']
            ?? $validated['duration']
            ?? 15;

        $payload = array_merge($validated, [
            'bansal_appointment_id' => $bansalId,
            'client_timezone' => $clientTimezone,
            'meeting_type' => $validated['meeting_type'] ?? 'in_person',
            'preferred_language' => $validated['preferred_language'] ?? 'English',
            'duration_minutes' => $durationMinutes,
            'status' => $status,
            'sync_status' => $validated['sync_status'] ?? 'new',
            'confirmation_email_sent' => false,
            'reminder_sms_sent' => false,
        ]);

        if ($websiteStatusCode !== null) {
            $map = config('booking_website_status_map.map.' . $websiteStatusCode, []);
            foreach (['status', 'payment_status', 'is_paid'] as $key) {
                if (array_key_exists($key, $map)) {
                    $payload[$key] = $map[$key];
                }
            }
            if (Schema::hasColumn('booking_appointments', 'website_status_code')) {
                $payload['website_status_code'] = $websiteStatusCode;
            }
            foreach (['payment_status', 'is_paid', 'paid_at', 'amount', 'discount_amount', 'final_amount', 'promo_code', 'payment_method'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $payload[$key] = $validated[$key];
                }
            }
            $this->applyWebsiteStatusLifecycleTimestamps($websiteStatusCode, $payload);
        }

        if (isset($payload['noe_id']) && $payload['noe_id'] !== null) {
            $noeId = (int) $payload['noe_id'];
            if (empty($payload['service_type'])) {
                $label = $this->serviceTypeLabelForNoeId($noeId);
                if ($label !== null) {
                    $payload['service_type'] = $label;
                }
            }
            if (empty($payload['enquiry_type'])) {
                $code = $this->enquiryTypeCodeForNoeId($noeId);
                if ($code !== null) {
                    $payload['enquiry_type'] = $code;
                }
            }
        }

        if (! isset($payload['user_id']) && ! empty($payload['client_id'])) {
            $payload['user_id'] = $payload['client_id'];
        }

        if ($websiteStatusCode === null && ! array_key_exists('is_paid', $validated)) {
            $payload['is_paid'] = false;
        }

        $payload['amount'] = $payload['amount'] ?? 0;
        $payload['discount_amount'] = $payload['discount_amount'] ?? 0;
        $payload['final_amount'] = $payload['final_amount'] ?? $payload['amount'];

        if ($payload['is_paid'] || ($payload['payment_status'] ?? null) === 'completed') {
            $payload['is_paid'] = true;
            if (empty($payload['paid_at'])) {
                $payload['paid_at'] = now();
            }
        }

        if ($status === 'confirmed' && empty($payload['confirmed_at'])) {
            $payload['confirmed_at'] = now();
        }

        $fillable = array_flip((new BookingAppointment())->getFillable());
        $payload = array_intersect_key($payload, $fillable);

        if (! Schema::hasColumn('booking_appointments', 'user_id')) {
            unset($payload['user_id']);
        }
        if (! Schema::hasColumn('booking_appointments', 'website_status_code')) {
            unset($payload['website_status_code']);
        }

        // Not a database column (Bansal slot API flag only); never send to INSERT.
        unset($payload['slot_overwrite_hidden']);

        try {
            $appointment = BookingAppointment::create($payload);
        } catch (\Throwable $e) {
            Log::error('API storeBookingAppointment failed', [
                'message' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);

            $debug = config('app.debug') ? ['error' => $e->getMessage()] : [];

            return $this->sendError('Could not create booking appointment.', $debug, 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking appointment created successfully.',
            'data' => [
                'id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'client_id' => $appointment->client_id,
                'consultant_id' => $appointment->consultant_id,
                'status' => $appointment->status,
                'website_status_code' => $appointment->website_status_code,
                'client_timezone' => $appointment->client_timezone,
                'timeslot_full' => $appointment->timeslot_full,
                'duration_minutes' => $appointment->duration_minutes,
                'duration' => $appointment->duration_minutes,
                'noe_id' => $appointment->noe_id,
                'service_id' => $appointment->service_id,
                'service_type' => $appointment->service_type,
                'appointment_datetime' => $appointment->appointment_datetime?->toIso8601String(),
                'is_paid' => $appointment->is_paid,
                'amount' => $appointment->amount,
                'discount_amount' => $appointment->discount_amount,
                'final_amount' => $appointment->final_amount,
                'promo_code' => $appointment->promo_code,
                'payment_status' => $appointment->payment_status,
                'payment_method' => $appointment->payment_method,
                'paid_at' => $appointment->paid_at?->toIso8601String(),
                'confirmed_at' => $appointment->confirmed_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function serviceTypeLabelForNoeId(int $noeId): ?string
    {
        $row = collect(config('booking_nature_of_enquiry.crm'))->firstWhere('id', $noeId);
        if ($row) {
            return $row['service_type'];
        }
        if ($noeId === 8) {
            return 'INDIA/UK/CANADA/EUROPE TO AUSTRALIA';
        }

        return null;
    }

    private function enquiryTypeCodeForNoeId(int $noeId): ?string
    {
        $row = collect(config('booking_nature_of_enquiry.crm'))->firstWhere('id', $noeId);
        if ($row) {
            return $row['enquiry_type'];
        }
        if ($noeId === 8) {
            return 'international';
        }

        return null;
    }

    private function resolveCountryCode(string $phone, ?string $requestCountryCode): ?string
    {
        if ($requestCountryCode !== null && $requestCountryCode !== '') {
            $cc = trim($requestCountryCode);

            return str_starts_with($cc, '+') ? $cc : '+' . ltrim($cc, '+');
        }
        if (preg_match('/^\+(\d{1,3})/', $phone, $m)) {
            return '+' . $m[1];
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 2 && substr($digits, 0, 2) === '61') {
            return '+61';
        }

        return '+61';
    }

    private function allocateTemporaryBansalAppointmentId(): int
    {
        do {
            $id = 2000000 + (time() % 900000) + random_int(1, 99999);
        } while (BookingAppointment::where('bansal_appointment_id', $id)->exists());

        return $id;
    }

    private function resolveWebsiteStatusCodeFromRequest(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return ($raw >= 0 && $raw <= 11) ? $raw : null;
        }
        if (is_string($raw) && $raw !== '' && ctype_digit($raw)) {
            $i = (int) $raw;

            return ($i >= 0 && $i <= 11) ? $i : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyWebsiteStatusLifecycleTimestamps(int $websiteStatusCode, array &$payload): void
    {
        $now = now();
        if ($websiteStatusCode === 1 && empty($payload['confirmed_at'])) {
            $payload['confirmed_at'] = $now;
        }
        if ($websiteStatusCode === 2 && empty($payload['completed_at'])) {
            $payload['completed_at'] = $now;
        }
        if (in_array($websiteStatusCode, [3, 7], true) && empty($payload['cancelled_at'])) {
            $payload['cancelled_at'] = $now;
        }
    }
}
