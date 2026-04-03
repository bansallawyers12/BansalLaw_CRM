<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BookingAppointment;
use App\Models\AppointmentConsultant;
use App\Models\Admin;
use Carbon\Carbon;

class SampleBookingAppointmentsSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Ensure we have consultants
        $consultants = AppointmentConsultant::all();
        
        if ($consultants->isEmpty()) {
            $this->command->error('No consultants found! Please run AppointmentConsultantSeeder first.');
            return;
        }

        // Get or create sample clients
        $clients = $this->createSampleClients();

        $this->command->info('Creating sample booking appointments...');

        $sampleAppointments = [
            // Pending Appointments (Future)
            [
                'client_index' => 0,
                'consultant_type' => 'paid',
                'client_name' => 'John Smith',
                'client_email' => 'john.smith@example.com',
                'client_phone' => '+61412345678',
                'appointment_datetime' => Carbon::now()->addDays(2)->setTime(10, 0),
                'timeslot_full' => '10:00 AM - 10:15 AM',
                'location' => 'melbourne',
                'service_id' => 1,
                'noe_id' => 1,
                'enquiry_type' => 'tr',
                'service_type' => 'Temporary Residency (TR)',
                'enquiry_details' => 'Need help with 485 visa. Currently on student visa expiring in 2 months.',
                'status' => 'pending',
                'is_paid' => true,
                'amount' => 150.00,
                'final_amount' => 150.00,
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subHours(1),
            ],
            [
                'client_index' => 1,
                'consultant_type' => 'jrp',
                'client_name' => 'Sarah Johnson',
                'client_email' => 'sarah.johnson@example.com',
                'client_phone' => '+61423456789',
                'appointment_datetime' => Carbon::now()->addDays(3)->setTime(14, 30),
                'timeslot_full' => '2:30 PM - 2:45 PM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 2,
                'enquiry_type' => 'jrp',
                'service_type' => 'Job Ready Program (JRP)',
                'enquiry_details' => 'Interested in JRP pathway for permanent residency.',
                'status' => 'pending',
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
            ],
            [
                'client_index' => 2,
                'consultant_type' => 'education',
                'client_name' => 'David Lee',
                'client_email' => 'david.lee@example.com',
                'client_phone' => '+61434567890',
                'appointment_datetime' => Carbon::now()->addDays(1)->setTime(11, 0),
                'timeslot_full' => '11:00 AM - 11:15 AM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 5,
                'enquiry_type' => 'education',
                'service_type' => 'Student Visa / Education',
                'enquiry_details' => 'Want to study MBA in Australia. Need guidance on visa process.',
                'status' => 'pending',
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
            ],

            // Confirmed Appointments (Tomorrow)
            [
                'client_index' => 3,
                'consultant_type' => 'tourist',
                'client_name' => 'Maria Garcia',
                'client_email' => 'maria.garcia@example.com',
                'client_phone' => '+61445678901',
                'appointment_datetime' => Carbon::now()->addDay()->setTime(9, 30),
                'timeslot_full' => '9:30 AM - 9:45 AM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 4,
                'enquiry_type' => 'tourist',
                'service_type' => 'Tourist Visa',
                'enquiry_details' => 'Parents visiting from Spain. Need tourist visa assistance.',
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now()->subHours(2),
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
                'admin_notes' => '[' . Carbon::now()->subHours(2)->format('Y-m-d H:i') . ' - Admin]' . "\n" . 'Confirmed via phone. Client will bring passport copies.',
            ],
            [
                'client_index' => 4,
                'consultant_type' => 'paid',
                'client_name' => 'Robert Chen',
                'client_email' => 'robert.chen@example.com',
                'client_phone' => '+61456789012',
                'appointment_datetime' => Carbon::now()->addDay()->setTime(15, 0),
                'timeslot_full' => '3:00 PM - 3:15 PM',
                'location' => 'melbourne',
                'service_id' => 1,
                'noe_id' => 6,
                'enquiry_type' => 'pr_complex',
                'service_type' => 'PR - Complex Case',
                'enquiry_details' => 'Complex PR case with employment gap issues.',
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now()->subDay(),
                'is_paid' => true,
                'amount' => 250.00,
                'discount_amount' => 25.00,
                'final_amount' => 225.00,
                'promo_code' => 'WELCOME10',
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subDay(),
                'reminder_sms_sent' => false,
            ],

            // Adelaide Appointments
            [
                'client_index' => 5,
                'consultant_type' => 'adelaide',
                'client_name' => 'Emma Wilson',
                'client_email' => 'emma.wilson@example.com',
                'client_phone' => '+61467890123',
                'appointment_datetime' => Carbon::now()->addDays(4)->setTime(10, 30),
                'timeslot_full' => '10:30 AM - 10:45 AM',
                'location' => 'adelaide',
                'service_id' => 1,
                'noe_id' => 1,
                'enquiry_type' => 'tr',
                'service_type' => 'Temporary Residency (TR)',
                'enquiry_details' => 'Adelaide state sponsorship inquiry.',
                'status' => 'pending',
                'is_paid' => true,
                'amount' => 150.00,
                'final_amount' => 150.00,
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subHours(3),
            ],

            // Completed Appointments (Past)
            [
                'client_index' => 6,
                'consultant_type' => 'paid',
                'client_name' => 'Michael Brown',
                'client_email' => 'michael.brown@example.com',
                'client_phone' => '+61478901234',
                'appointment_datetime' => Carbon::now()->subDays(2)->setTime(14, 0),
                'timeslot_full' => '2:00 PM - 2:15 PM',
                'location' => 'melbourne',
                'service_id' => 1,
                'noe_id' => 1,
                'enquiry_type' => 'tr',
                'service_type' => 'Temporary Residency (TR)',
                'enquiry_details' => '482 visa consultation.',
                'status' => 'completed',
                'confirmed_at' => Carbon::now()->subDays(3),
                'completed_at' => Carbon::now()->subDays(2)->setTime(14, 20),
                'is_paid' => true,
                'amount' => 150.00,
                'final_amount' => 150.00,
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => Carbon::now()->subDays(3),
                'admin_notes' => '[' . Carbon::now()->subDays(2)->format('Y-m-d H:i') . ' - Admin]' . "\n" . 'Meeting completed successfully. Client satisfied with consultation.',
            ],
            [
                'client_index' => 7,
                'consultant_type' => 'education',
                'client_name' => 'Lisa Anderson',
                'client_email' => 'lisa.anderson@example.com',
                'client_phone' => '+61489012345',
                'appointment_datetime' => Carbon::now()->subDays(5)->setTime(10, 0),
                'timeslot_full' => '10:00 AM - 10:15 AM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 5,
                'enquiry_type' => 'education',
                'service_type' => 'Student Visa / Education',
                'enquiry_details' => 'Student visa extension consultation.',
                'status' => 'completed',
                'confirmed_at' => Carbon::now()->subDays(6),
                'completed_at' => Carbon::now()->subDays(5)->setTime(10, 15),
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
                'reminder_sms_sent' => true,
                'reminder_sms_sent_at' => Carbon::now()->subDays(6)->setTime(9, 0),
            ],

            // Cancelled Appointment
            [
                'client_index' => 8,
                'consultant_type' => 'jrp',
                'client_name' => 'James Taylor',
                'client_email' => 'james.taylor@example.com',
                'client_phone' => '+61490123456',
                'appointment_datetime' => Carbon::now()->addDays(5)->setTime(16, 0),
                'timeslot_full' => '4:00 PM - 4:15 PM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 3,
                'enquiry_type' => 'skill_assessment',
                'service_type' => 'Skill Assessment',
                'enquiry_details' => 'Skill assessment for IT profession.',
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now()->subHours(6),
                'cancellation_reason' => 'Client requested to reschedule due to work commitment.',
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
            ],

            // No Show Appointment
            [
                'client_index' => 9,
                'consultant_type' => 'tourist',
                'client_name' => 'Patricia Martinez',
                'client_email' => 'patricia.martinez@example.com',
                'client_phone' => '+61401234567',
                'appointment_datetime' => Carbon::now()->subDays(1)->setTime(13, 0),
                'timeslot_full' => '1:00 PM - 1:15 PM',
                'location' => 'melbourne',
                'service_id' => 2,
                'noe_id' => 4,
                'enquiry_type' => 'tourist',
                'service_type' => 'Tourist Visa',
                'enquiry_details' => 'Tourist visa for family visit.',
                'status' => 'no_show',
                'confirmed_at' => Carbon::now()->subDays(3),
                'is_paid' => false,
                'amount' => 0,
                'final_amount' => 0,
                'reminder_sms_sent' => true,
                'reminder_sms_sent_at' => Carbon::now()->subDays(2)->setTime(9, 0),
                'admin_notes' => '[' . Carbon::now()->subDays(1)->format('Y-m-d H:i') . ' - Admin]' . "\n" . 'Client did not show up. Attempted to call - no answer.',
            ],
        ];

        foreach ($sampleAppointments as $index => $appointmentData) {
            $consultant = AppointmentConsultant::where('calendar_type', $appointmentData['consultant_type'])
                ->where('is_active', true)
                ->first();

            if (!$consultant) {
                $this->command->warn("No consultant found for type: {$appointmentData['consultant_type']}");
                continue;
            }

            $client = $clients[$appointmentData['client_index']] ?? null;

            BookingAppointment::create([
                'bansal_appointment_id' => 1000 + $index,
                'order_hash' => $appointmentData['is_paid'] ? 'ord_' . md5($appointmentData['client_email'] . time() . $index) : null,
                
                'client_id' => $client?->id,
                'consultant_id' => $consultant->id,
                'assigned_by_admin_id' => 1,
                
                'client_name' => $appointmentData['client_name'],
                'client_email' => $appointmentData['client_email'],
                'client_phone' => $appointmentData['client_phone'],
                'client_timezone' => 'Australia/Melbourne',
                
                'appointment_datetime' => $appointmentData['appointment_datetime'],
                'timeslot_full' => $appointmentData['timeslot_full'],
                'duration_minutes' => 15,
                'location' => $appointmentData['location'],
                'inperson_address' => $appointmentData['location'] === 'adelaide' ? 1 : 2,
                'meeting_type' => 'in_person',
                'preferred_language' => 'English',
                
                'service_id' => $appointmentData['service_id'],
                'noe_id' => $appointmentData['noe_id'],
                'enquiry_type' => $appointmentData['enquiry_type'],
                'service_type' => $appointmentData['service_type'],
                'enquiry_details' => $appointmentData['enquiry_details'],
                
                'status' => $appointmentData['status'],
                'confirmed_at' => $appointmentData['confirmed_at'] ?? null,
                'completed_at' => $appointmentData['completed_at'] ?? null,
                'cancelled_at' => $appointmentData['cancelled_at'] ?? null,
                'cancellation_reason' => $appointmentData['cancellation_reason'] ?? null,
                
                'is_paid' => $appointmentData['is_paid'],
                'amount' => $appointmentData['amount'] ?? 0,
                'discount_amount' => $appointmentData['discount_amount'] ?? 0,
                'final_amount' => $appointmentData['final_amount'] ?? 0,
                'promo_code' => $appointmentData['promo_code'] ?? null,
                'payment_status' => $appointmentData['payment_status'] ?? null,
                'payment_method' => $appointmentData['payment_method'] ?? null,
                'paid_at' => $appointmentData['paid_at'] ?? null,
                
                'admin_notes' => $appointmentData['admin_notes'] ?? null,
                
                'confirmation_email_sent' => $appointmentData['status'] === 'confirmed',
                'confirmation_email_sent_at' => $appointmentData['confirmed_at'] ?? null,
                'reminder_sms_sent' => $appointmentData['reminder_sms_sent'] ?? false,
                'reminder_sms_sent_at' => $appointmentData['reminder_sms_sent_at'] ?? null,
                
                'synced_from_bansal_at' => Carbon::now()->subMinutes(rand(10, 60)),
                'last_synced_at' => Carbon::now()->subMinutes(rand(1, 10)),
                'sync_status' => 'synced',
                
                'created_at' => $appointmentData['appointment_datetime']->copy()->subDays(2),
                'updated_at' => Carbon::now()->subMinutes(rand(1, 30)),
            ]);

            $this->command->info("✓ Created appointment for {$appointmentData['client_name']} ({$appointmentData['status']})");
        }

        $this->command->info("\n✅ Successfully created " . count($sampleAppointments) . " sample appointments!");
        $this->displaySummary();
    }

    /**
     * Create sample clients
     */
    private function createSampleClients(): array
    {
        $clients = [];
        
        $sampleClientData = [
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

        foreach ($sampleClientData as $index => $clientData) {
            // Check if client already exists
            $client = Admin::whereIn('type', ['client', 'lead'])
                ->where('email', $clientData['email'])
                ->first();

            if (!$client) {
                // Get next client counter
                $clientCntExist = Admin::whereIn('type', ['client', 'lead'])->count();
                if ($clientCntExist > 0) {
                    $clientLatestArr = Admin::whereIn('type', ['client', 'lead'])
                        ->latest()
                        ->first();
                    $client_latest_counter = $clientLatestArr ? $clientLatestArr->client_counter : "00000";
                } else {
                    $client_latest_counter = "00000";
                }

                $client_current_counter = str_pad((int)$client_latest_counter + 1, 5, '0', STR_PAD_LEFT);
                
                $firstFourLetters = strtoupper(strlen($clientData['first_name']) >= 4
                    ? substr($clientData['first_name'], 0, 4)
                    : $clientData['first_name']);
                $client_id = $firstFourLetters . date('y') . $client_current_counter;

                $client = Admin::create([
                    'first_name' => $clientData['first_name'],
                    'last_name' => $clientData['last_name'],
                    'email' => $clientData['email'],
                    'phone' => $clientData['phone'],
                    'country_code' => '+61',
                    'client_counter' => $client_current_counter,
                    'client_id' => $client_id,
                    'type' => 'lead',
                    'source' => 'Bansal Website (Test Data)',
                    'created_at' => Carbon::now()->subMonths(rand(1, 6)),
                    'updated_at' => Carbon::now()->subDays(rand(1, 30)),
                ]);

                $this->command->info("  → Created test client: {$clientData['first_name']} {$clientData['last_name']} ({$client_id})");
            }

            $clients[] = $client;
        }

        return $clients;
    }

    /**
     * Display summary of created appointments
     */
    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 Summary:');
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
        $this->command->info('📅 Appointment Distribution:');
        $this->command->table(
            ['Consultant Type', 'Count'],
            [
                ['Paid Services', BookingAppointment::whereHas('consultant', fn($q) => $q->where('calendar_type', 'paid'))->count()],
                ['JRP', BookingAppointment::whereHas('consultant', fn($q) => $q->where('calendar_type', 'jrp'))->count()],
                ['Education', BookingAppointment::whereHas('consultant', fn($q) => $q->where('calendar_type', 'education'))->count()],
                ['Tourist', BookingAppointment::whereHas('consultant', fn($q) => $q->where('calendar_type', 'tourist'))->count()],
                ['Adelaide', BookingAppointment::whereHas('consultant', fn($q) => $q->where('calendar_type', 'adelaide'))->count()],
            ]
        );
    }
}

