<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Tag extends Authenticatable
{
    use Notifiable;
	use Sortable;
	
	// Tag type constants
	const TYPE_NORMAL = 'normal';
	const TYPE_RED = 'red';
	
	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = [
		'name',
		'tag_type',
		'is_hidden',
		'created_by',
		'updated_by'
	];

	public function createddetail()
    {
        return $this->belongsTo(Staff::class, 'created_by', 'id');
    }	
	
	public function updateddetail()
    {
        return $this->belongsTo(Staff::class, 'updated_by', 'id');
    }
	
	/**
     * Scope a query to only include normal tags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
	public function scopeNormal($query)
	{
		return $query->where('tag_type', self::TYPE_NORMAL);
	}
	
	/**
     * Scope a query to only include red tags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
	public function scopeRed($query)
	{
		return $query->where('tag_type', self::TYPE_RED);
	}
	
	/**
     * Scope a query to only include visible tags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
	public function scopeVisible($query)
	{
		return $query->where('is_hidden', false);
	}
	
	/**
     * Scope a query to only include hidden tags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
	public function scopeHidden($query)
	{
		return $query->where('is_hidden', true);
	}
	
	/**
     * Check if this is a red tag.
     *
     * @return bool
     */
	public function isRedTag()
	{
		return $this->tag_type === self::TYPE_RED;
	}
	
	/**
     * Check if this tag is hidden.
     *
     * @return bool
     */
	public function isHidden()
	{
		return $this->is_hidden === true;
	}
}
