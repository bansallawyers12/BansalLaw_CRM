<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BookingAppointment;
use App\Models\AppointmentConsultant;
use App\Models\Admin;
use App\Support\BookingCatalogue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Sample Lawyers bookings (practice areas 1–7, products 10/30/60, Melbourne, ajay/kunal).
 */
class SampleBookingAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $consultants = AppointmentConsultant::query()
            ->whereIn('calendar_type', ['ajay', 'kunal'])
            ->where('is_active', true)
            ->get();

        if ($consultants->isEmpty()) {
            $this->command->error('No ajay/kunal consultants found. Run AppointmentConsultantSeeder first.');

            return;
        }

        $clients = $this->createSampleClients();
        $this->command->info('Creating sample Lawyers booking appointments...');

        $samples = [
            [
                'client_index' => 0,
                'calendar_type' => 'ajay',
                'client_name' => 'John Smith',
                'client_email' => 'john.smith@example.com',
                'client_phone' => '+61412345678',
                'appointment_datetime' => Carbon::now()->addDays(2)->setTime(10, 0),
                'timeslot_full' => '10:00 AM - 10:30 AM',
                'form_service_id' => 2,
                'noe_id' => 1,
                'meeting_type' => 'in_person',
                'enquiry_details' => 'Need advice on a criminal law matter.',
                'status' => 'pending',
                'payment_status' => 'pending',
            ],
            [
                'client_index' => 1,
                'calendar_type' => 'kunal',
                'client_name' => 'Sarah Johnson',
                'client_email' => 'sarah.johnson@example.com',
                'client_phone' => '+61423456789',
                'appointment_datetime' => Carbon::now()->addDays(3)->setTime(14, 30),
                'timeslot_full' => '2:30 PM - 2:40 PM',
                'form_service_id' => 1,
                'noe_id' => 2,
                'meeting_type' => 'phone',
                'enquiry_details' => 'First free consult about a family law enquiry.',
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now()->subHours(2),
            ],
            [
                'client_index' => 2,
                'calendar_type' => 'ajay',
                'client_name' => 'David Lee',
                'client_email' => 'david.lee@example.com',
                'client_phone' => '+61434567890',
                'appointment_datetime' => Carbon::now()->addDays(1)->setTime(11, 0),
                'timeslot_full' => '11:00 AM - 12:00 PM',
                'form_service_id' => 3,
                'noe_id' => 3,
                'meeting_type' => 'video',
                'enquiry_details' => 'Extended consultation for a corporate dispute.',
                'status' => 'pending',
                'payment_status' => 'pending',
            ],
            [
                'client_index' => 3,
                'calendar_type' => 'kunal',
                'client_name' => 'Maria Garcia',
                'client_email' => 'maria.garcia@example.com',
                'client_phone' => '+61445678901',
                'appointment_datetime' => Carbon::now()->addDay()->setTime(9, 30),
                'timeslot_full' => '9:30 AM - 10:00 AM',
                'form_service_id' => 2,
                'noe_id' => 5,
                'meeting_type' => 'phone',
                'enquiry_details' => 'Immigration Law pathway discussion.',
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now()->subHours(2),
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subHours(1),
                'admin_notes' => '[' . Carbon::now()->subHours(2)->format('Y-m-d H:i') . " - Admin]\nConfirmed. Client will join by phone.",
            ],
            [
                'client_index' => 4,
                'calendar_type' => 'ajay',
                'client_name' => 'Robert Chen',
                'client_email' => 'robert.chen@example.com',
                'client_phone' => '+61456789012',
                'appointment_datetime' => Carbon::now()->addDay()->setTime(15, 0),
                'timeslot_full' => '3:00 PM - 4:00 PM',
                'form_service_id' => 3,
                'noe_id' => 6,
                'meeting_type' => 'in_person',
                'enquiry_details' => 'Property Law conveyancing dispute.',
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now()->subDay(),
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subDay(),
            ],
            [
                'client_index' => 5,
                'calendar_type' => 'kunal',
                'client_name' => 'Emma Wilson',
                'client_email' => 'emma.wilson@example.com',
                'client_phone' => '+61467890123',
                'appointment_datetime' => Carbon::now()->addDays(4)->setTime(10, 30),
                'timeslot_full' => '10:30 AM - 11:00 AM',
                'form_service_id' => 2,
                'noe_id' => 7,
                'meeting_type' => 'video',
                'enquiry_details' => 'Commercial Law contract review.',
                'status' => 'pending',
                'payment_status' => 'pending',
            ],
            [
                'client_index' => 6,
                'calendar_type' => 'ajay',
                'client_name' => 'Michael Brown',
                'client_email' => 'michael.brown@example.com',
                'client_phone' => '+61478901234',
                'appointment_datetime' => Carbon::now()->subDays(2)->setTime(14, 0),
                'timeslot_full' => '2:00 PM - 2:30 PM',
                'form_service_id' => 2,
                'noe_id' => 4,
                'meeting_type' => 'in_person',
                'enquiry_details' => 'Personal Law will and estate query.',
                'status' => 'completed',
                'confirmed_at' => Carbon::now()->subDays(3),
                'completed_at' => Carbon::now()->subDays(2)->setTime(14, 35),
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subDays(3),
            ],
            [
                'client_index' => 7,
                'calendar_type' => 'kunal',
                'client_name' => 'Lisa Anderson',
                'client_email' => 'lisa.anderson@example.com',
                'client_phone' => '+61489012345',
                'appointment_datetime' => Carbon::now()->subDays(5)->setTime(10, 0),
                'timeslot_full' => '10:00 AM - 10:10 AM',
                'form_service_id' => 1,
                'noe_id' => 1,
                'meeting_type' => 'phone',
                'enquiry_details' => 'Free consult — criminal law overview.',
                'status' => 'completed',
                'confirmed_at' => Carbon::now()->subDays(6),
                'completed_at' => Carbon::now()->subDays(5)->setTime(10, 12),
            ],
            [
                'client_index' => 8,
                'calendar_type' => 'ajay',
                'client_name' => 'James Taylor',
                'client_email' => 'james.taylor@example.com',
                'client_phone' => '+61490123456',
                'appointment_datetime' => Carbon::now()->addDays(5)->setTime(16, 0),
                'timeslot_full' => '4:00 PM - 4:30 PM',
                'form_service_id' => 2,
                'noe_id' => 2,
                'meeting_type' => 'phone',
                'enquiry_details' => 'Family Law parenting matter — cancelled.',
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now()->subHours(6),
                'cancellation_reason' => 'Client requested to reschedule due to work commitment.',
            ],
            [
                'client_index' => 9,
                'calendar_type' => 'kunal',
                'client_name' => 'Patricia Martinez',
                'client_email' => 'patricia.martinez@example.com',
                'client_phone' => '+61401234567',
                'appointment_datetime' => Carbon::now()->subDays(1)->setTime(13, 0),
                'timeslot_full' => '1:00 PM - 1:30 PM',
                'form_service_id' => 2,
                'noe_id' => 3,
                'meeting_type' => 'in_person',
                'enquiry_details' => 'Corporate Law consult — no show.',
                'status' => 'no_show',
                'confirmed_at' => Carbon::now()->subDays(3),
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subDays(3),
                'admin_notes' => '[' . Carbon::now()->subDays(1)->format('Y-m-d H:i') . " - Admin]\nClient did not show up.",
            ],
        ];

        foreach ($samples as $index => $row) {
            $product = BookingCatalogue::productByFormId((int) $row['form_service_id']);
            if (! $product) {
                $this->command->warn("Unknown form_service_id {$row['form_service_id']}");
                continue;
            }

            $noe = BookingCatalogue::serviceTypeMappingForNoe((int) $row['noe_id'], 'crm');
            $consultant = $consultants->firstWhere('calendar_type', $row['calendar_type'])
                ?? $consultants->first();
            $client = $clients[$row['client_index']] ?? null;
            $dbServiceId = (int) $product['db_service_id'];
            $amount = (float) $product['price'];
            $isFree = $dbServiceId === 2;
            $isPaidCompleted = ($row['payment_status'] ?? null) === 'completed';

            BookingAppointment::create([
                'bansal_appointment_id' => 1000 + $index,
                'order_hash' => $isPaidCompleted ? 'ord_' . md5($row['client_email'] . $index) : null,
                'client_id' => $client?->id,
                'consultant_id' => $consultant->id,
                'assigned_by_admin_id' => null,
                'client_name' => $row['client_name'],
                'client_email' => $row['client_email'],
                'client_phone' => $row['client_phone'],
                'client_timezone' => 'Australia/Melbourne',
                'appointment_datetime' => $row['appointment_datetime'],
                'timeslot_full' => $row['timeslot_full'],
                'duration_minutes' => (int) $product['duration_minutes'],
                'location' => 'melbourne',
                'inperson_address' => BookingCatalogue::inpersonAddressMelbourne(),
                'meeting_type' => $row['meeting_type'],
                'preferred_language' => 'English',
                'service_id' => $dbServiceId,
                'noe_id' => $row['noe_id'],
                'noe_scheme' => 'crm',
                'enquiry_type' => $noe['enquiry_type'],
                'service_type' => $noe['service_type'],
                'enquiry_details' => $row['enquiry_details'],
                'status' => $row['status'],
                'confirmed_at' => $row['confirmed_at'] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
                'cancelled_at' => $row['cancelled_at'] ?? null,
                'cancellation_reason' => $row['cancellation_reason'] ?? null,
                'is_paid' => $isPaidCompleted,
                'amount' => $amount,
                'discount_amount' => 0,
                'final_amount' => $amount,
                'payment_status' => $isFree ? null : ($row['payment_status'] ?? 'pending'),
                'payment_method' => $row['payment_method'] ?? null,
                'paid_at' => $row['paid_at'] ?? null,
                'admin_notes' => $row['admin_notes'] ?? null,
                'confirmation_email_sent' => false,
                'reminder_sms_sent' => false,
                'sync_status' => 'new',
            ]);
        }

        $this->command->info('✓ Created ' . count($samples) . ' sample Lawyers appointments');
        $this->displaySummary();
    }

    private function createSampleClients(): array
    {
        $defs = [
            ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@example.com', 'phone' => '+61412345678'],
            ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.johnson@example.com', 'phone' => '+61423456789'],
            ['first_name' => 'David', 'last_name' => 'Lee', 'email' => 'david.lee@example.com', 'phone' => '+61434567890'],
            ['first_name' => 'Maria', 'last_name' => 'Garcia', 'email' => 'maria.garcia@example.com', 'phone' => '+61445678901'],
            ['first_name' => 'Robert', 'last_name' => 'Chen', 'email' => 'robert.chen@example.com', 'phone' => '+61456789012'],
            ['first_name' => 'Emma', 'last_name' => 'Wilson', 'email' => 'emma.wilson@example.com', 'phone' => '+61467890123'],
            ['first_name' => 'Michael', 'last_name' => 'Brown', 'email' => 'michael.brown@example.com', 'phone' => '+61478901234'],
            ['first_name' => 'Lisa', 'last_name' => 'Anderson', 'email' => 'lisa.anderson@example.com', 'phone' => '+61489012345'],
            ['first_name' => 'James', 'last_name' => 'Taylor', 'email' => 'james.taylor@example.com', 'phone' => '+61490123456'],
            ['first_name' => 'Patricia', 'last_name' => 'Martinez', 'email' => 'patricia.martinez@example.com', 'phone' => '+61401234567'],
        ];

        $clients = [];
        foreach ($defs as $data) {
            $client = Admin::whereIn('type', ['client', 'lead'])
                ->where('email', $data['email'])
                ->first();

            if (! $client) {
                $latest = Admin::whereIn('type', ['client', 'lead'])->latest()->first();
                $clientLatestCounter = $latest?->client_counter ?: '00000';
                $clientCurrentCounter = str_pad((string) ((int) $clientLatestCounter + 1), 5, '0', STR_PAD_LEFT);
                $firstFourLetters = strtoupper(strlen($data['first_name']) >= 4
                    ? substr($data['first_name'], 0, 4)
                    : $data['first_name']);
                $clientId = $firstFourLetters . date('y') . $clientCurrentCounter;

                $client = Admin::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'country_code' => '+61',
                    'client_counter' => $clientCurrentCounter,
                    'client_id' => $clientId,
                    'type' => 'lead',
                    'specialist_education' => 0,
                    'password' => Hash::make('password'),
                ]);
                $this->command->info("  → Created test client: {$data['first_name']} {$data['last_name']} ({$clientId})");
            }
            $clients[] = $client;
        }

        return $clients;
    }

    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->table(
            ['Status', 'Count'],
            [
                ['Pending', BookingAppointment::where('status', 'pending')->count()],
                ['Confirmed', BookingAppointment::where('status', 'confirmed')->count()],
                ['Completed', BookingAppointment::where('status', 'completed')->count()],
                ['Cancelled', BookingAppointment::where('status', 'cancelled')->count()],
                ['No Show', BookingAppointment::where('status', 'no_show')->count()],
                ['TOTAL', BookingAppointment::count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('Consultant distribution:');
        $this->command->table(
            ['Calendar', 'Count'],
            [
                ['Ajay', BookingAppointment::whereHas('consultant', fn ($q) => $q->where('calendar_type', 'ajay'))->count()],
                ['Kunal / Michael', BookingAppointment::whereHas('consultant', fn ($q) => $q->where('calendar_type', 'kunal'))->count()],
            ]
        );
    }
}
