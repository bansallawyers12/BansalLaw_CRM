<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CpDocChecklist extends Authenticatable
{
    use Notifiable;
    use Sortable;

    protected $table = 'cp_doc_checklists';
}
