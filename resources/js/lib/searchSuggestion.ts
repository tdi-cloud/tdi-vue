/**
 * "Did you mean …?" for lists filtered in the browser. Same rules as the
 * server-side App\Support\SearchSuggester; keep the two in step.
 */

const THRESHOLD = 0.6;
const MIN_WORD_LENGTH = 3;

function words(text: string): string[] {
    return text
        .toLowerCase()
        .trim()
        .split(/[^\p{L}\p{N}]+/u)
        .filter(Boolean);
}

/** Edit distance where swapping two neighbouring letters counts as one edit ("curz" → "cruz"). */
function distance(a: string, b: string): number {
    const d: number[][] = [];
    for (let i = 0; i <= a.length; i++) d[i] = [i];
    for (let j = 0; j <= b.length; j++) d[0][j] = j;

    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);

            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
            }
        }
    }

    return d[a.length][b.length];
}

function similarity(a: string, b: string): number {
    const longest = Math.max(a.length, b.length);
    return longest === 0 ? 1 : 1 - distance(a, b) / longest;
}

/** Returns the corrected query, or null when nothing needs correcting or nothing is close enough. */
export function suggestSearch(query: string | null | undefined, terms: Iterable<string | null | undefined>): string | null {
    const queryWords = words(query ?? '');
    if (queryWords.length === 0) return null;

    const vocabulary = new Set<string>();
    for (const term of terms) {
        words(term ?? '').forEach((word) => {
            // Short words would make poor corrections ("abc" is 67% similar to "ab").
            if (word.length >= MIN_WORD_LENGTH) vocabulary.add(word);
        });
    }
    if (vocabulary.size === 0) return null;

    let changed = false;
    const corrected = queryWords.map((word) => {
        if (vocabulary.has(word) || word.length < MIN_WORD_LENGTH || /^\d+$/.test(word)) return word;

        let best: string | null = null;
        let bestScore = THRESHOLD;
        for (const candidate of vocabulary) {
            const score = similarity(word, candidate);
            // Strictly greater, so on a tie the first known word wins (stable results).
            if (score > bestScore) {
                best = candidate;
                bestScore = score;
            }
        }

        if (best) changed = true;
        return best ?? word;
    });

    return changed ? corrected.join(' ') : null;
}
