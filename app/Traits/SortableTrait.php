<?php

namespace App\Traits;

use Spatie\QueryBuilder\QueryBuilder;

trait SortableTrait
{
    /**
     * Get the sortable columns for this model
     *
     * @return array
     */
    public function getSortableColumns(): array
    {
        return $this->sortable ?? [];
    }

    /**
     * Create a query builder with sorting capabilities
     *
     * @param array $allowedSorts
     * @param array $allowedFilters
     * @return QueryBuilder
     */
    public static function sortableQuery(array $allowedSorts = [], array $allowedFilters = [])
    {
        $instance = new static();
        $sortableColumns = $instance->getSortableColumns();
        
        // If no allowed sorts specified, use all sortable columns
        if (empty($allowedSorts)) {
            $allowedSorts = $sortableColumns;
        }

        return QueryBuilder::for(static::class)
            ->allowedSorts($allowedSorts)
            ->allowedFilters($allowedFilters);
    }

    /**
     * Get paginated results with sorting
     *
     * @param int $perPage
     * @param array $allowedSorts
     * @param array $allowedFilters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function sortablePaginate(int $perPage = 15, array $allowedSorts = [], array $allowedFilters = [])
    {
        return static::sortableQuery($allowedSorts, $allowedFilters)->paginate($perPage);
    }
} 