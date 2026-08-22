<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\EmailVerification;
use App\Models\PhoneVerification;

class ContactVerificationService
{
    public function phoneIdentityChanged(ClientContact $contact, mixed $newPhone, mixed $newCountryCode): bool
    {
        return $this->normalizePhone($contact->phone) !== $this->normalizePhone($newPhone)
            || trim((string) $contact->country_code) !== trim((string) $newCountryCode);
    }

    public function emailIdentityChanged(ClientEmail $email, mixed $newEmail): bool
    {
        return strtolower(trim((string) $email->email)) !== strtolower(trim((string) $newEmail));
    }

    public function invalidatePhoneVerification(ClientContact $contact): void
    {
        $contact->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $this->supersedePendingPhoneVerifications($contact->id);
    }

    public function invalidateEmailVerification(ClientEmail $email): void
    {
        $email->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
            'verification_token' => null,
            'token_expires_at' => null,
            'verification_sent_at' => null,
        ]);

        $this->supersedePendingEmailVerifications($email->id);
    }

    public function supersedePendingPhoneVerifications(int $contactId): void
    {
        PhoneVerification::where('client_contact_id', $contactId)
            ->where('status', PhoneVerification::STATUS_PENDING)
            ->update(['status' => PhoneVerification::STATUS_SUPERSEDED]);
    }

    public function supersedePendingEmailVerifications(int $emailId): void
    {
        EmailVerification::where('client_email_id', $emailId)
            ->where('status', EmailVerification::STATUS_PENDING)
            ->update(['status' => EmailVerification::STATUS_SUPERSEDED]);
    }

    private function normalizePhone(mixed $phone): string
    {
        return preg_replace('/[^\d]/', '', (string) $phone) ?? '';
    }
}
