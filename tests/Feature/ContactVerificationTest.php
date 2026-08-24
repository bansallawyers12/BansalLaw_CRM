<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\EmailVerification;
use App\Models\PhoneVerification;
use App\Models\Staff;
use App\Services\ContactVerificationService;
use App\Services\EmailVerificationService;
use App\Services\Sms\CellcastProvider;
use App\Services\Sms\PhoneVerificationService;
use App\Services\Sms\UnifiedSmsManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function changing_phone_number_invalidates_verification(): void
    {
        $contact = $this->createVerifiedContact();

        app(ContactVerificationService::class)->invalidatePhoneVerification($contact);

        $contact->refresh();
        $this->assertFalse($contact->is_verified);
        $this->assertNull($contact->verified_at);
        $this->assertNull($contact->verified_by);
    }

    #[Test]
    public function changing_email_invalidates_verification(): void
    {
        $email = $this->createVerifiedEmail();

        app(ContactVerificationService::class)->invalidateEmailVerification($email);

        $email->refresh();
        $this->assertFalse($email->is_verified);
        $this->assertNull($email->verified_at);
        $this->assertNull($email->verified_by);
        $this->assertNull($email->verification_token);
    }

    #[Test]
    public function phone_identity_change_detects_number_and_country_code_only(): void
    {
        $service = app(ContactVerificationService::class);
        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
        ]);

        $this->assertFalse($service->phoneIdentityChanged($contact, '412345678', '+61'));
        $this->assertTrue($service->phoneIdentityChanged($contact, '498765432', '+61'));
        $this->assertTrue($service->phoneIdentityChanged($contact, '412345678', '+64'));
    }

    #[Test]
    public function email_identity_change_is_case_insensitive(): void
    {
        $service = app(ContactVerificationService::class);
        $email = ClientEmail::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'email_type' => 'Personal',
            'email' => 'Client@Example.com',
        ]);

        $this->assertFalse($service->emailIdentityChanged($email, 'client@example.com'));
        $this->assertTrue($service->emailIdentityChanged($email, 'other@example.com'));
    }

    #[Test]
    public function resend_supersedes_previous_pending_phone_verification(): void
    {
        $staff = $this->createStaff();
        Auth::guard('admin')->login($staff);

        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $staff->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
        ]);

        $oldVerification = PhoneVerification::create([
            'client_contact_id' => $contact->id,
            'client_id' => $contact->client_id,
            'phone' => $contact->phone,
            'country_code' => $contact->country_code,
            'otp_code' => '111111',
            'status' => PhoneVerification::STATUS_PENDING,
            'otp_sent_at' => now()->subMinutes(2),
            'otp_expires_at' => now()->addMinutes(3),
            'is_verified' => false,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $smsManager = $this->mockSuccessfulSmsManager();
        $service = new PhoneVerificationService($smsManager, app(ContactVerificationService::class));

        Carbon::setTestNow(now()->addMinutes(1));
        $result = $service->sendOTP($contact->id);
        Carbon::setTestNow();

        $this->assertTrue($result['success']);
        $this->assertEquals(
            PhoneVerification::STATUS_SUPERSEDED,
            $oldVerification->fresh()->status
        );
        $this->assertEquals(2, PhoneVerification::where('client_contact_id', $contact->id)->count());
    }

    #[Test]
    public function rate_limit_counts_superseded_phone_sends(): void
    {
        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
        ]);

        foreach (['111111', '222222', '333333'] as $index => $otp) {
            PhoneVerification::create([
                'client_contact_id' => $contact->id,
                'client_id' => $contact->client_id,
                'phone' => $contact->phone,
                'country_code' => $contact->country_code,
                'otp_code' => $otp,
                'status' => PhoneVerification::STATUS_SUPERSEDED,
                'otp_sent_at' => now()->subMinutes(30 - $index),
                'otp_expires_at' => now()->subMinutes(25 - $index),
                'is_verified' => false,
                'attempts' => 0,
                'max_attempts' => 3,
            ]);
        }

        $smsManager = $this->mockSuccessfulSmsManager();
        $service = new PhoneVerificationService($smsManager, app(ContactVerificationService::class));
        $result = $service->sendOTP($contact->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Too many OTP requests', $result['message']);
    }

    #[Test]
    public function verify_otp_marks_record_verified_and_updates_contact(): void
    {
        $staff = $this->createStaff();
        Auth::guard('admin')->login($staff);

        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $staff->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
            'is_verified' => false,
        ]);

        PhoneVerification::create([
            'client_contact_id' => $contact->id,
            'client_id' => $contact->client_id,
            'phone' => $contact->phone,
            'country_code' => $contact->country_code,
            'otp_code' => '654321',
            'status' => PhoneVerification::STATUS_PENDING,
            'otp_sent_at' => now(),
            'otp_expires_at' => now()->addMinutes(5),
            'is_verified' => false,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $smsManager = $this->mockSuccessfulSmsManager();
        $service = new PhoneVerificationService($smsManager, app(ContactVerificationService::class));
        $result = $service->verifyOTP($contact->id, '654321');

        $this->assertTrue($result['success']);
        $contact->refresh();
        $this->assertTrue($contact->is_verified);
        $this->assertEquals($staff->id, $contact->verified_by);
        $this->assertEquals(
            PhoneVerification::STATUS_VERIFIED,
            PhoneVerification::where('client_contact_id', $contact->id)->value('status')
        );
    }

    #[Test]
    public function resend_supersedes_previous_pending_email_verification(): void
    {
        $email = ClientEmail::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'email_type' => 'Personal',
            'email' => 'verify@example.com',
        ]);

        $oldVerification = EmailVerification::create([
            'client_email_id' => $email->id,
            'client_id' => $email->client_id,
            'email' => $email->email,
            'verification_token' => 'old-token',
            'status' => EmailVerification::STATUS_PENDING,
            'token_sent_at' => now()->subMinutes(5),
            'token_expires_at' => now()->addHours(1),
            'is_verified' => false,
        ]);

        $mailer = $this->createMock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->method('to')->willReturnSelf();
        $mailer->method('send')->willReturn(null);

        $mailRouting = $this->createMock(\App\Services\MailRoutingService::class);
        $mailRouting->method('mailer')->willReturn($mailer);

        $service = new EmailVerificationService($mailRouting, app(ContactVerificationService::class));
        Carbon::setTestNow(now()->addMinutes(3));
        $result = $service->sendVerificationEmail($email->id);
        Carbon::setTestNow();

        $this->assertTrue($result['success']);
        $this->assertEquals(
            EmailVerification::STATUS_SUPERSEDED,
            $oldVerification->fresh()->status
        );
        $this->assertEquals(2, EmailVerification::where('client_email_id', $email->id)->count());
    }

    #[Test]
    public function verify_email_token_marks_record_verified(): void
    {
        $email = ClientEmail::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'email_type' => 'Personal',
            'email' => 'token@example.com',
            'is_verified' => false,
        ]);

        EmailVerification::create([
            'client_email_id' => $email->id,
            'client_id' => $email->client_id,
            'email' => $email->email,
            'verification_token' => 'valid-token',
            'status' => EmailVerification::STATUS_PENDING,
            'token_sent_at' => now(),
            'token_expires_at' => now()->addDay(),
            'is_verified' => false,
        ]);

        $mailRouting = $this->createMock(\App\Services\MailRoutingService::class);
        $service = new EmailVerificationService($mailRouting, app(ContactVerificationService::class));
        $result = $service->verifyToken('valid-token', '127.0.0.1', 'PHPUnit');

        $this->assertTrue($result['success']);
        $email->refresh();
        $this->assertTrue($email->is_verified);
        $this->assertEquals(
            EmailVerification::STATUS_VERIFIED,
            EmailVerification::where('client_email_id', $email->id)->value('status')
        );
    }

    #[Test]
    public function expired_otp_is_marked_expired_and_cannot_be_used(): void
    {
        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
        ]);

        PhoneVerification::create([
            'client_contact_id' => $contact->id,
            'client_id' => $contact->client_id,
            'phone' => $contact->phone,
            'country_code' => $contact->country_code,
            'otp_code' => '654321',
            'status' => PhoneVerification::STATUS_PENDING,
            'otp_sent_at' => now()->subMinutes(10),
            'otp_expires_at' => now()->subMinute(),
            'is_verified' => false,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $smsManager = $this->mockSuccessfulSmsManager();
        $service = new PhoneVerificationService($smsManager, app(ContactVerificationService::class));
        $result = $service->verifyOTP($contact->id, '654321');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expired', strtolower($result['message']));
        $this->assertEquals(
            PhoneVerification::STATUS_EXPIRED,
            PhoneVerification::where('client_contact_id', $contact->id)->value('status')
        );
    }

    #[Test]
    public function failed_sms_does_not_count_toward_hourly_rate_limit(): void
    {
        $contact = ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $this->createStaff()->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
        ]);

        $failPayload = [
            'success' => false,
            'message' => 'SMS provider error',
        ];
        $successPayload = [
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => ['messages' => [['message_id' => '12345']]],
            'results' => [['sid' => '12345']],
        ];

        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('sendSms')->willReturnOnConsecutiveCalls($failPayload, $successPayload);

        $smsManager = new UnifiedSmsManager($mockCellcast);
        $service = new PhoneVerificationService($smsManager, app(ContactVerificationService::class));

        $failed = $service->sendOTP($contact->id);
        $this->assertFalse($failed['success']);

        $retried = $service->sendOTP($contact->id);
        $this->assertTrue($retried['success']);
        $this->assertEquals(2, PhoneVerification::where('client_contact_id', $contact->id)->count());
        $this->assertEquals(1, PhoneVerification::where('client_contact_id', $contact->id)->whereNotNull('otp_sent_at')->count());
    }

    private function createStaff(): Staff
    {
        return Staff::create([
            'first_name' => 'Verify',
            'last_name' => 'Staff',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 1,
        ]);
    }

    private function createClient(): Admin
    {
        return Admin::create([
            'first_name' => 'Verify',
            'last_name' => 'Client',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);
    }

    private function createVerifiedContact(): ClientContact
    {
        $staff = $this->createStaff();

        return ClientContact::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $staff->id,
            'contact_type' => 'Personal',
            'phone' => '412345678',
            'country_code' => '+61',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);
    }

    private function createVerifiedEmail(): ClientEmail
    {
        $staff = $this->createStaff();

        return ClientEmail::create([
            'client_id' => $this->createClient()->id,
            'admin_id' => $staff->id,
            'email_type' => 'Personal',
            'email' => 'verified@example.com',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
            'verification_token' => 'token',
            'token_expires_at' => now()->addDay(),
            'verification_sent_at' => now(),
        ]);
    }

    private function mockSuccessfulSmsManager(): UnifiedSmsManager
    {
        $successPayload = [
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => ['messages' => [['message_id' => '12345']]],
            'results' => [['sid' => '12345']],
        ];

        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('sendSms')->willReturn($successPayload);

        return new UnifiedSmsManager($mockCellcast);
    }
}
