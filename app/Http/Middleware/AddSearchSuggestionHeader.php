<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Support\SearchSuggester;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Did you mean …?" for type-ahead employee lookups (`?q=`).
 *
 * These endpoints return a plain JSON array that other code depends on, so
 * instead of changing the body, an empty result gets an X-Search-Suggestion
 * header with the closest employee name. The pickers read it to offer a
 * one-click correction.
 */
class AddSearchSuggestionHeader
{
    public const HEADER = 'X-Search-Suggestion';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $query = trim((string) $request->query('q', ''));

        if (! $response instanceof JsonResponse || $query === '' || ! $this->isEmptyResult($response->getData(true))) {
            return $response;
        }

        $suggestion = SearchSuggester::suggest($query, $this->employeeNames());

        if ($suggestion !== null) {
            $response->headers->set(self::HEADER, $suggestion);
        }

        return $response;
    }

    /**
     * Empty list, or an empty paginator/wrapped list ({ data: [] }).
     */
    private function isEmptyResult(mixed $data): bool
    {
        if (is_array($data) && array_is_list($data)) {
            return $data === [];
        }

        return is_array($data) && array_key_exists('data', $data) && $data['data'] === [];
    }

    /**
     * Cached briefly so a burst of empty searches doesn't rescan the employees table.
     *
     * @return list<string>
     */
    private function employeeNames(): array
    {
        return Cache::remember('search-suggester:employee-names', now()->addMinutes(10), fn () => Employee::query()
            ->get(['FIRSTNAME', 'LASTNAME'])
            ->flatMap(fn (Employee $employee) => [$employee->FIRSTNAME, $employee->LASTNAME])
            ->filter()
            ->unique()
            ->values()
            ->all());
    }
}
