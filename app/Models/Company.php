<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'admin_id',
        'company_name',
        'trading_name',
        'has_trading_name',
        'ABN_number',
        'ACN',
        'company_type',
        'company_website',
        'contact_person_id',
        'contact_person_position',
        'solicitor_id',
        'solicitor_position',
        'trust_name',
        'trust_abn',
        'trustee_name',
        'trustee_details',
    ];

    protected $casts = [
        'has_trading_name' => 'boolean',
    ];

    /** Stored value for business type “Trustee” (legacy DB may still have "Trust"). */
    public const BUSINESS_TYPE_TRUSTEE = 'Trustee';

    public static function normalizeBusinessType(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        if (trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return $value === 'Trust' ? self::BUSINESS_TYPE_TRUSTEE : $value;
    }

    public static function isTrusteeBusinessType(mixed $value): bool
    {
        return is_string($value) && in_array($value, [self::BUSINESS_TYPE_TRUSTEE, 'Trust'], true);
    }

    /**
     * Human-readable business type (maps legacy "Trust" to "Trustee").
     */
    public static function businessTypeLabel(mixed $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        return $stored === 'Trust' ? self::BUSINESS_TYPE_TRUSTEE : $stored;
    }

    public function isTrusteeBusiness(): bool
    {
        return self::isTrusteeBusinessType($this->company_type);
    }

    /**
     * Get the admin (lead/client) record this company belongs to
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    /**
     * Get the primary contact person for this company
     */
    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'contact_person_id', 'id');
    }

    /**
     * Get the solicitor linked to this company lead/client.
     */
    public function solicitor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'solicitor_id', 'id');
    }

    /**
     * Get trading names (multiple per company).
     * Display logic: if tradingNames has records use those; else fall back to trading_name.
     */
    public function tradingNames(): HasMany
    {
        return $this->hasMany(CompanyTradingName::class)->orderBy('sort_order');
    }

    /**
     * Get directors
     */
    public function directors(): HasMany
    {
        return $this->hasMany(CompanyDirector::class)->orderBy('sort_order');
    }
}
