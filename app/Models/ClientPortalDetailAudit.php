<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPortalDetailAudit extends Model
{
    protected $table = 'clientportal_details_audit';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'meta_key',
        'old_value',
        'new_value',
        'meta_order',
        'meta_type',
        'action',
        'updated_by',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'client_id' => 'integer',
        'meta_order' => 'integer',
        'updated_by' => 'integer',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the client this audit belongs to
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the admin who made the change
     */
    public function updater()
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Scope to filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by meta key
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('meta_key', $key);
    }
}
