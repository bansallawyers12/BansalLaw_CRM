<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Database\Eloquent\Model;

class StaffLoginLog extends Model
{
    use Notifiable;
    use SortableTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff_login_logs';

    protected $fillable = [
        'level', 'user_id', 'ip_address', 'user_agent', 'message', 'created_at', 'updated_at'
    ];

    public $sortable = ['id'];
}
