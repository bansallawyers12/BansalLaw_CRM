<?php

namespace App\Mail;

use App\Models\ClientEoiReference;
use App\Models\Admin;
use App\Services\PointsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EoiConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $eoiReference;
    public $client;
    public $confirmUrl;
    public $amendUrl;
    public $pointsData;

    /** @var array<int, array{data: string, name: string, mime: string}> Raw attachment data (not Laravel's $attachments which expects file paths) */
    public $eoiAttachmentData = [];

    /** @var array<int, string> Category names attached (e.g. EOI Summary, Points Summary, ROI Draft) */
    public $attachmentLabels;

    /** @var string|null Custom subject (optional) */
    public $customSubject;

    /** @var string|null Custom body (optional) */
    public $customBody;

    /**
     * Create a new message instance.
     *
     * @param array<int, array{data: string, name: string, mime: string}> $attachments
     * @param array<int, string> $attachmentLabels
     * @param string|null $customSubject Optional custom subject
     * @param string|null $customBody Optional custom body
     */
    public function __construct(
        ClientEoiReference $eoiReference,
        Admin $client,
        string $token,
        array $attachments = [],
        array $attachmentLabels = [],
        ?string $customSubject = null,
        ?string $customBody = null
    ) {
        $this->eoiReference = $eoiReference;
        $this->client = $client;
        $this->confirmUrl = route('client.eoi.confirm', ['token' => $token]);
        $this->amendUrl = route('client.eoi.amend', ['token' => $token]);
        $this->eoiAttachmentData = $attachments;
        $this->attachmentLabels = $attachmentLabels;
        $this->customSubject = $customSubject;
        $this->customBody = $customBody;

        // Get points calculation with warnings
        $pointsService = app(PointsService::class);
        $subclass = is_array($eoiReference->eoi_subclasses) && count($eoiReference->eoi_subclasses) > 0
            ? $eoiReference->eoi_subclasses[0]
            : null;
        $this->pointsData = $pointsService->compute($client, $subclass);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Use custom subject if provided, otherwise use default
        $subject = $this->customSubject ?? 'Please Confirm Your EOI Details - ' . $this->eoiReference->EOI_number;
        
        $mail = $this->subject($subject)
            ->view('emails.eoi_confirmation')
            ->with([
                'customBody' => $this->customBody, // Pass custom body to view (for Phase 2 if needed)
            ]);

        foreach ($this->eoiAttachmentData as $att) {
            $mail->attachData(
                $att['data'],
                $att['name'],
                ['mime' => $att['mime'] ?? 'application/octet-stream']
            );
        }

        return $mail;
    }
}
