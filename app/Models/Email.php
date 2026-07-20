<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Email extends Authenticatable
{
    use HasFactory, Notifiable;
	use Sortable; 

    /** @var list<string> Mail providers routed through AWS SES. */
    public const SYSTEM_MAIL_PROVIDERS = ['ses', 'sendgrid'];

    public function usesSystemMailer(): bool
    {
        return in_array((string) $this->mail_provider, self::SYSTEM_MAIL_PROVIDERS, true);
    }
	
    /** 
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'display_name',
        'mail_provider',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'password',
        'status',
        'sync_enabled',
        'sync_sent_enabled',
        'last_synced_at',
        'last_imap_uid',
        'last_imap_uid_sent',
        'last_sync_error',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'email_signature',
        'user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
        'sync_enabled' => 'boolean',
        'sync_sent_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_imap_uid' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return list<int>
     */
    public function sharedStaffIds(): array
    {
        $raw = $this->user_id ?? '[]';
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return array_values(array_filter(array_map('intval', (array) $decoded)));
    }

    public function isSharedWithStaff(int $staffId): bool
    {
        return in_array($staffId, $this->sharedStaffIds(), true);
    }

    public function resolveOwnerStaffId(): ?int
    {
        $ids = $this->sharedStaffIds();
        if ($ids !== []) {
            $matched = Staff::query()
                ->whereIn('id', $ids)
                ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))])
                ->value('id');

            return $matched ? (int) $matched : (int) $ids[0];
        }

        $byEmail = Staff::query()
            ->where('status', 1)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($this->email))])
            ->value('id');

        return $byEmail ? (int) $byEmail : null;
    }

	public $sortable = ['id', 'created_at', 'updated_at'];
} 
