<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentCancellation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $details
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name', config('app.name'))
            ),
            subject: 'Appointment Cancellation - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-cancellation',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'appointmentDate' => $this->formatAppointmentDate($this->details['appointment_datetime'] ?? null),
                'appointmentTime' => $this->details['timeslot_full'] ?? 'N/A',
                'location' => 'Melbourne',
                'locationAddress' => $this->getLocationAddress($this->details['location'] ?? 'melbourne'),
                'locationPhone' => $this->getLocationPhone($this->details['location'] ?? 'melbourne'),
                'consultant' => $this->details['consultant'] ?? 'Our Team',
                'serviceType' => $this->details['service_type'] ?? 'Legal Consultation',
                'cancellationReason' => $this->details['cancellation_reason'] ?? null,
            ],
        );
    }

    /**
     * Safely format appointment datetime whether Carbon or string.
     */
    protected function formatAppointmentDate(mixed $datetime): string
    {
        if ($datetime instanceof \DateTimeInterface) {
            return Carbon::parse($datetime)->format('l, d F Y');
        }

        if (is_string($datetime) && trim($datetime) !== '') {
            try {
                return Carbon::parse($datetime)->format('l, d F Y');
            } catch (\Throwable) {
                return $datetime;
            }
        }

        return 'N/A';
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get full address for location
     */
    protected function getLocationAddress(string $location): string
    {
        // Lawyers booking is Melbourne-only; historical adelaide rows still get Melbourne office details.
        return 'Level 8/278 Collins St, Melbourne VIC 3000';
    }

    /**
     * Get phone number for location (used in appointment emails)
     */
    protected function getLocationPhone(string $location): string
    {
        return '+61 3 9602 1330';
    }
}
