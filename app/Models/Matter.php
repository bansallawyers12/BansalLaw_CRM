<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class Matter extends Model
{
	use Sortable;
	
	protected $table = 'matters';
	
	protected $fillable = [
		'id', 'title', 'nick_name', 'stream', 'workflow_id', 'is_for_company', 'created_at', 'updated_at'
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

	/**
	 * Prefix for client_matters.client_unique_matter_no (before underscore + sequence).
	 * Prefers matters.nick_name; if empty, derives from title (so Civil Law → CIV without relying on nick_name).
	 * GN only when the matter row is missing or both nick_name and title are unusable.
	 */
	public static function clientUniqueMatterNoPrefix(?int $matterTypeId): string
	{
		if ($matterTypeId === null || $matterTypeId < 1) {
			return 'GN';
		}
		$matter = static::query()->where('id', $matterTypeId)->first(['nick_name', 'title']);
		if (! $matter) {
			return 'GN';
		}
		$nick = trim((string) ($matter->nick_name ?? ''));
		if ($nick !== '') {
			return $nick;
		}
		$derived = static::derivePrefixFromMatterTitle((string) ($matter->title ?? ''));

		return $derived !== '' ? $derived : 'GN';
	}

	/**
	 * Build a short ASCII prefix from a matter type title (e.g. "Civil Law" → "CIV").
	 */
	protected static function derivePrefixFromMatterTitle(string $title): string
	{
		$title = trim($title);
		if ($title === '') {
			return '';
		}
		$compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $title));
		if (strlen($compact) >= 3) {
			return substr($compact, 0, 3);
		}
		if (strlen($compact) >= 2) {
			return $compact;
		}

		return '';
	}

	/**
	 * Display label from joined matters.title (client_matters + matters).
	 * Legacy UI forced id 1 to "General Matter"; id 1 is Civil Law in DB — use title when set.
	 */
	public static function displayTitleFromJoinedRow(?string $matterTitle): string
	{
		$t = $matterTitle !== null ? trim((string) $matterTitle) : '';

		return $t !== '' ? $t : 'General Matter';
	}
}
