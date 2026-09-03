<?php

namespace App\Support\Concerns;

trait FiltersEmployees
{
    /**
     * @var array<int, string>
     */
    public static array $regions = [
        'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5',
        'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12',
        'CAR', 'CARAGA',
    ];

    private function applyEmployeeFilters($query, $region, $statuses, $officeFilter, $office = null, $prefix = '')
    {
        return $query
            ->when($region && $region !== 'ALL', function ($q) use ($region, $prefix) {
                $q->where($prefix.'REGION', $region);
            })
            ->when(! empty($statuses), function ($q) use ($statuses, $prefix) {
                $q->whereIn($prefix.'PLANTILLA STATUS', $statuses);
            })
            ->when($officeFilter === 'OPCR', function ($q) use ($prefix) {
                $q->where(function ($query) use ($prefix) {
                    $query->where($prefix.'OFFICE/DIVISION', 'LIKE', 'CO-%')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-ORD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-ROD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-FASD')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-PO-%')
                        ->orWhere($prefix.'OFFICE/DIVISION', 'LIKE', '%-DO-%');
                });
            })
            ->when($office && $office !== 'ALL', function ($q) use ($office, $prefix) {
                $q->where($prefix.'OFFICE', $office);
            });
    }
}
