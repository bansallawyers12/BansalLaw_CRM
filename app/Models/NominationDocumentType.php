<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;

class NominationDocumentType extends Authenticatable
{
    use Notifiable;
    use Sortable;

    protected $table = 'nomination_document_types';

    protected $fillable = ['id', 'title', 'status', 'client_id', 'client_matter_id', 'created_at', 'updated_at'];

    public $sortable = ['id', 'created_at', 'updated_at'];
}
