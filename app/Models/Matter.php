<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class Matter extends Model
{
	use Sortable;
	
	protected $table = 'matters';
	
	protected $fillable = [
		'id', 'title', 'nick_name', 'workflow_id', 'is_for_company', 'created_at', 'updated_at'
	];

	/**
	 * Get the default workflow for this matter type.
	 */
	public function workflow()
	{
		return $this->belongsTo(Workflow::class, 'workflow_id');
	}
	
	public $sortable = ['id', 'title', 'nick_name', 'created_at', 'updated_at'];
	
	// Relationship with EmailTemplate (matter_other type)
	public function otherEmailTemplates()
	{
		return $this->hasMany(EmailTemplate::class, 'matter_id')->where('type', EmailTemplate::TYPE_MATTER_OTHER);
	}

	// Relationship with EmailTemplate (matter_first type)
	public function firstEmailTemplate()
	{
		return $this->hasOne(EmailTemplate::class, 'matter_id')->where('type', EmailTemplate::TYPE_MATTER_FIRST);
	}

	/**
	 * Check if this matter is for companies only
	 */
	public function isForCompany(): bool
	{
		return (bool) $this->is_for_company;
	}

	/**
	 * Scope to filter matters by client type
	 */
	public function scopeForClientType($query, bool $isCompany)
	{
		if ($isCompany) {
			return $query->forCompanySubjectSelection();
		}

		return $query->forPersonalSubjectSelection();
	}

	/**
	 * Matter types selectable when the CRM subject is a company (admins.is_company).
	 * Includes is_for_company types and General matter (id 1).
	 */
	public function scopeForCompanySubjectSelection($query)
	{
		return $query->where(function ($q) {
			$q->where('is_for_company', true)->orWhere('id', 1);
		});
	}

	/**
	 * Matter types selectable for a personal (non-company) client.
	 * Includes General matter (id 1) and types not flagged for company-only.
	 */
	public function scopeForPersonalSubjectSelection($query)
	{
		return $query->where(function ($q) {
			$q->where('id', 1)
				->orWhere(function ($q2) {
					$q2->where('is_for_company', false)->orWhereNull('is_for_company');
				});
		});
	}

	/**
	 * Whether a matter type may be assigned to a client record (company vs personal).
	 * General matter (id 1) is allowed for both.
	 */
	public static function allowedForClientIsCompany(int $matterId, bool $clientIsCompany): bool
	{
		if ($matterId < 1) {
			return false;
		}
		if ($matterId === 1) {
			return true;
		}
		$matter = static::query()->find($matterId);
		if (!$matter) {
			return false;
		}
		$forCompany = $matter->is_for_company;
		if ($clientIsCompany) {
			return (bool) $forCompany;
		}

		return ! $forCompany;
	}
}
