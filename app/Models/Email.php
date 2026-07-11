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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

	public $sortable = ['id', 'created_at', 'updated_at'];
} 
