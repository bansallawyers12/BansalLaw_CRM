<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use App\Helpers\SortableHelper;
use Spatie\QueryBuilder\QueryBuilder;

trait SortableTrait
{
    /**
     * Columns allowed for request sorting (?sort=col / ?sort=-col).
     *
     * @return list<string>
     */
    public function getSortableColumns(): array
    {
        if (! property_exists($this, 'sortable') || ! is_array($this->sortable)) {
            return [];
        }

        return array_values(array_filter($this->sortable, 'is_string'));
    }

    /**
     * Apply Spatie Query Builder sorting onto the Eloquent builder.
     *
     * Drop-in for kyslik/column-sortable: $query->sortable(['id' => 'desc']).
     * Request format matches SortableHelper: sort=column or sort=-column.
     *
     * @param  array<string, string>  $defaultParameters  e.g. ['id' => 'desc']
     */
    public function scopeSortable(Builder $query, array $defaultParameters = []): Builder
    {
        $allowedSorts = $this->getSortableColumns();

        foreach (array_keys($defaultParameters) as $column) {
            if (is_string($column) && ! in_array($column, $allowedSorts, true)) {
                $allowedSorts[] = $column;
            }
        }

        if ($allowedSorts === []) {
            $allowedSorts = ['id'];
        }

        $builder = QueryBuilder::for($query, SortableHelper::requestWithAllowedSortsOnly($allowedSorts))
            ->allowedSorts(...$allowedSorts);

        if ($defaultParameters !== []) {
            $defaults = [];
            foreach ($defaultParameters as $column => $direction) {
                if (! is_string($column) || $column === '') {
                    continue;
                }
                $defaults[] = strtolower((string) $direction) === 'desc'
                    ? '-'.$column
                    : $column;
            }
            if ($defaults !== []) {
                $builder->defaultSort(...$defaults);
            }
        }

        return $builder->getEloquentBuilder();
    }

    /**
     * Create a query builder with sorting capabilities.
     *
     * @param  list<string>  $allowedSorts
     * @param  list<string>  $allowedFilters
     */
    public static function sortableQuery(array $allowedSorts = [], array $allowedFilters = []): QueryBuilder
    {
        $instance = new static();
        $sortableColumns = $instance->getSortableColumns();

        if ($allowedSorts === []) {
            $allowedSorts = $sortableColumns !== [] ? $sortableColumns : ['id'];
        }

        $builder = QueryBuilder::for(static::class)->allowedSorts(...$allowedSorts);

        if ($allowedFilters !== []) {
            $builder->allowedFilters(...$allowedFilters);
        }

        return $builder;
    }

    /**
     * Get paginated results with sorting.
     *
     * @param  list<string>  $allowedSorts
     * @param  list<string>  $allowedFilters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function sortablePaginate(int $perPage = 15, array $allowedSorts = [], array $allowedFilters = [])
    {
        return static::sortableQuery($allowedSorts, $allowedFilters)->paginate($perPage);
    }
}
