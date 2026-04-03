<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AccountClientReceipt extends Authenticatable
{
    use Notifiable;
	use Sortable;

}
