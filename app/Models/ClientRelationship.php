<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRelationship extends Model
{
    protected $table = 'client_relationships';

    protected $fillable = [
        'admin_id',
        'client_id',
        'related_client_id',
        'details',
        'relationship_type',
        'company_type',
        'email',
        'first_name',
        'last_name',
        'phone',
        'gender',
        'dob',
    ];

    /**
     * Get the related client (partner/child/etc)
     * Enables eager loading to prevent N+1 queries
     */
    public function relatedClient()
    {
        return $this->belongsTo(Admin::class, 'related_client_id', 'id');
    }

    /**
     * Get the main client
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id', 'id');
    }
}


