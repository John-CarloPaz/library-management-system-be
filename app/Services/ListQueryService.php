<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ListQueryService
{
    /**
     * Build a list result from a base query using common query params.
     *
    * Supported query params:
    * - status=VALUE|if null = all paginated         -> where(status_field, VALUE)
    * - archived=true|false|all
    * - search=TEXT | q=TEXT      -> fulltext-like search across configured fields
    * - active=true|all      -> maps to archived filters when archived_field is provided
    * - per_page=int         -> page size (default 10)
    * - all=true             -> ignore pagination and return all records
    * - count=int|all|paginated
    *      - count=NUMBER    -> sets per_page=NUMBER
    *      - count=all       -> same as all=true (no pagination)
    *      - count=paginated -> forces paginated mode using default page size
     */
    public function build(Request $request, Builder $baseQuery, array $options = []): LengthAwarePaginator|Collection
    {
        $query = clone $baseQuery;

        $query = $this->applyStatusFilter($request, $query, $options);

        // Apply optional branch filter (only when `branch_field` option is provided
        // and a `branch_id` query param is present). If `branch_id` is not supplied
        // we intentionally return all branches.
        $query = $this->applyBranchFilter($request, $query, $options);

        $query = $this->applyArchivedFilters($request, $query, $options);
        $query = $this->applyBooleanFilters($request, $query, $options);

        // Apply optional search across fields when provided via `search` or `q`
        $query = $this->applySearchFilter($request, $query, $options);

        // Base pagination flags from legacy params
        $all = filter_var($request->query('all', 'false'), FILTER_VALIDATE_BOOLEAN);
        $perPage = (int) $request->query('per_page', 10);

        // If no explicit status filter was provided, force paginated responses
        // (i.e. do not honor implicit "all" listing). This ensures endpoints
        // without a status query return paginated results by default.
        $statusValue = $request->query('status');

        // New "count" param overrides per_page/all behavior when present
        $count = $request->query('count');
        if ($count !== null && $count !== '') {
            if ($count === 'all') {
                $all = true;
            } elseif ($count === 'paginated') {
                $all = false;
                if ($perPage <= 0) {
                    $perPage = 10;
                }
            } elseif (is_numeric($count)) {
                $all = false;
                $perPage = max((int) $count, 1);
            }
        }

        // If caller did not provide a status, force pagination regardless of
        // `all` or `count=all` parameters. Respect explicit numeric counts.
        if ($statusValue === null || $statusValue === '') {
            $all = false;
            if ($perPage <= 0) {
                $perPage = 10;
            }
        }

        if ($all || $perPage === 0) {
            return $query->get();
        }

        return $query->paginate(max($perPage, 1));
    }

    /**
     * Apply simple boolean filters based on query params.
     *
     * Option: 'boolean_fields' => [
     *     'query_param_name' => 'column_name',
     * ]
     *
     * Example: boolean_fields => ['is_active' => 'is_active']
     *   -> /endpoint?is_active=true | false
     */
    private function applyBooleanFilters(Request $request, Builder $query, array $options): Builder
    {
        $booleanFields = $options['boolean_fields'] ?? [];

        if (empty($booleanFields)) {
            return $query;
        }

        foreach ($booleanFields as $param => $column) {
            $raw = $request->query($param);

            if ($raw === null || $raw === '') {
                continue;
            }

            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($bool === null) {
                continue; // ignore invalid values
            }

            $query->where($column, $bool);
        }

        return $query;
    }

    /**
     * Apply a branch filter when requested.
     * Option: 'branch_field' => 'branch_id'
     * Query param: ?branch_id=NUMBER
     * If `branch_id` is absent or empty, no filtering is applied (all branches).
     */
    private function applyBranchFilter(Request $request, Builder $query, array $options): Builder
    {
        $branchField = $options['branch_field'] ?? null;
        if (! $branchField) {
            return $query;
        }

        // Support either `branch_id` or `branch` query param for backward-compatibility
        $branchId = $request->query('branch_id');
        if ($branchId === null || $branchId === '') {
            $branchId = $request->query('branch');
        }

        if ($branchId === null || $branchId === '') {
            return $query; // no filter -> return all branches
        }

        // treat literal 'null' as no filter
        if (is_string($branchId) && strtolower($branchId) === 'null') {
            return $query;
        }

        // Support dot notation for relation fields, e.g. `book.branch_id`
        if (str_contains($branchField, '.')) {
            [$relation, $col] = explode('.', $branchField, 2);
            return $query->whereHas($relation, function ($q) use ($col, $branchId) {
                $q->where($col, $branchId);
            });
        }

        return $query->where($branchField, $branchId);
    }

    /**
     * Apply a status filter if configured.
     */
    private function applyStatusFilter(Request $request, Builder $query, array $options): Builder
    {
        $statusField = $options['status_field'] ?? null;
        $statusValue = $request->query('status');

        if ($statusField && $statusValue !== null && $statusValue !== '') {
            $query->where($statusField, $statusValue);
        }

        return $query;
    }

    /**
     * Apply archived/active filters.
     *
     * Options:
     * - archived_field: column name (e.g. 'is_archived' or 'cataloging_status')
     * - archived_flag:  when set, treat archived_field as enum where this value means "archived"
     */
    private function applyArchivedFilters(Request $request, Builder $query, array $options): Builder
    {
        $archivedField = $options['archived_field'] ?? null;

        if (!$archivedField) {
            return $query;
        }

        $archivedParam = $request->query('archived');
        $activeParam = $request->query('active');

        // archived param has precedence over active
        if ($archivedParam !== null && $archivedParam !== '') {
            return $this->applyArchivedParam($query, $archivedField, $archivedParam, $options);
        }

        if ($activeParam !== null && $activeParam !== '') {
            return $this->applyActiveParam($query, $archivedField, $activeParam, $options);
        }

        return $query;
    }

    /**
     * Apply a simple search across configured fields.
     * Option: 'search_fields' => ['col', 'relation.col']
     * Query param: ?search=term or ?q=term
     */
    private function applySearchFilter(Request $request, Builder $query, array $options): Builder
    {
        $term = $request->query('search');
        if ($term === null || $term === '') {
            $term = $request->query('q');
        }

        $fields = $options['search_fields'] ?? [];
        if (empty($fields) || $term === null || $term === '') {
            return $query;
        }

        $term = trim($term);

        $query->where(function ($q) use ($fields, $term) {
            foreach ($fields as $field) {
                if (str_contains($field, '.')) {
                    [$rel, $col] = explode('.', $field, 2);
                    $q->orWhereHas($rel, function ($sub) use ($col, $term) {
                        $sub->where($col, 'like', "%{$term}%");
                    });
                } else {
                    $q->orWhere($field, 'like', "%{$term}%");
                }
            }
        });

        return $query;
    }

    private function applyArchivedParam(Builder $query, string $field, string $value, array $options): Builder
    {
        $archivedFlag = $options['archived_flag'] ?? null;

        if ($value === 'all') {
            return $query; // no filter
        }

        if ($archivedFlag !== null) {
            // Enum-like status column (e.g. cataloging_status = 'archived')
            if ($value === 'true' || $value === '1') {
                return $query->where($field, $archivedFlag);
            }

            if ($value === 'false' || $value === '0') {
                return $query->where($field, '!=', $archivedFlag);
            }

            return $query;
        }

        // Boolean column (e.g. is_archived)
        if ($value === 'true' || $value === '1') {
            return $query->where($field, true);
        }

        if ($value === 'false' || $value === '0') {
            return $query->where($field, false);
        }

        return $query;
    }

    private function applyActiveParam(Builder $query, string $field, string $value, array $options): Builder
    {
        $archivedFlag = $options['archived_flag'] ?? null;

        if ($value === 'all') {
            return $query; // no filter
        }

        if ($archivedFlag !== null) {
            // active=true => not archived
            if ($value === 'true' || $value === '1') {
                return $query->where($field, '!=', $archivedFlag);
            }

            return $query;
        }

        // Boolean column: active=true => is_archived = false
        if ($value === 'true' || $value === '1') {
            return $query->where($field, false);
        }

        return $query;
    }
}
