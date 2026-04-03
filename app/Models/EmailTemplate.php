<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class EmailTemplate extends Model
{
    use Sortable;

    protected $table = 'email_templates';

    protected $fillable = ['type', 'alias', 'matter_id', 'name', 'subject', 'description'];

    public $sortable = ['id', 'name', 'subject', 'created_at', 'updated_at'];

    public const TYPE_CRM = 'crm';
    public const TYPE_MATTER_FIRST = 'matter_first';
    public const TYPE_MATTER_OTHER = 'matter_other';

    public function matter()
    {
        return $this->belongsTo(Matter::class, 'matter_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCrm($query)
    {
        return $query->where('type', self::TYPE_CRM);
    }

    public function scopeForMatter($query, ?int $matterId)
    {
        return $query->where('matter_id', $matterId ?? 0);
    }

    public function scopeByAlias($query, string $alias)
    {
        return $query->where('alias', $alias);
    }
}
