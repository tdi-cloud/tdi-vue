<?php

namespace App\Support;

/**
 * "Did you mean …?" for searches that return nothing.
 *
 * Each word of the query is compared with the words found in the known terms
 * (program titles, employee names, regions, …). A word that isn't a known
 * word is swapped for its closest known word when they are similar enough,
 * so "leadrship progarm" becomes "leadership program". When nothing needs
 * correcting, or nothing is close enough, there is no suggestion.
 *
 * The same rules run in the browser for client-side filtered lists
 * (resources/js/lib/searchSuggestion.ts); keep the two in step.
 */
final class SearchSuggester
{
    /** Minimum similarity (0–1) for a known word to replace a query word. */
    public const THRESHOLD = 0.6;

    /** Words shorter than this are kept as typed (too short to correct reliably). */
    private const MIN_WORD_LENGTH = 3;

    /**
     * Suggestion for a search that found nothing. The known terms are only
     * loaded (via the callback) when there is a search and it came back empty.
     *
     * @param  callable(): iterable<int, string|null>  $terms
     */
    public static function forEmptyResults(?string $query, int $resultCount, callable $terms): ?string
    {
        if ($resultCount > 0 || trim((string) $query) === '') {
            return null;
        }

        return self::suggest($query, $terms());
    }

    /**
     * @param  iterable<int, string|null>  $terms
     */
    public static function suggest(?string $query, iterable $terms): ?string
    {
        $queryWords = self::words((string) $query);

        if ($queryWords === []) {
            return null;
        }

        $vocabulary = [];
        foreach ($terms as $term) {
            foreach (self::words((string) $term) as $word) {
                // Short words would make poor corrections ("abc" is 67% similar to "ab").
                if (mb_strlen($word) >= self::MIN_WORD_LENGTH) {
                    $vocabulary[$word] = true;
                }
            }
        }

        if ($vocabulary === []) {
            return null;
        }

        $corrected = array_map(
            fn (string $word): string => self::closestWord($word, $vocabulary) ?? $word,
            $queryWords,
        );

        return $corrected === $queryWords ? null : implode(' ', $corrected);
    }

    /**
     * @param  array<string, true>  $vocabulary
     */
    private static function closestWord(string $word, array $vocabulary): ?string
    {
        if (isset($vocabulary[$word]) || mb_strlen($word) < self::MIN_WORD_LENGTH || ctype_digit($word)) {
            return null;
        }

        $bestWord = null;
        $bestScore = self::THRESHOLD - PHP_FLOAT_EPSILON;

        foreach (array_keys($vocabulary) as $candidate) {
            $score = self::similarity($word, $candidate);

            // Strictly greater, so on a tie the first known word wins (stable results).
            if ($score > $bestScore) {
                $bestWord = $candidate;
                $bestScore = $score;
            }
        }

        return $bestWord;
    }

    private static function similarity(string $a, string $b): float
    {
        $longest = max(mb_strlen($a), mb_strlen($b));

        return $longest === 0 ? 1.0 : 1 - self::distance($a, $b) / $longest;
    }

    /**
     * Edit distance where swapping two neighbouring letters counts as one edit
     * (optimal string alignment), since "curz" for "cruz" is the most common typo.
     * Plain levenshtein() would count that swap as two edits.
     */
    private static function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $rows = count($a);
        $columns = count($b);
        $d = [];

        for ($i = 0; $i <= $rows; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $columns; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $rows; $i++) {
            for ($j = 1; $j <= $columns; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$rows][$columns];
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);

        return $parts ?: [];
    }
}
