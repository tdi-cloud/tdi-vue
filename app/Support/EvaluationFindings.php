<?php

namespace App\Support;

use App\Models\EvaluationSection;
use Illuminate\Support\Collection;

/**
 * Writes section "Program Evaluation Findings and Observations" of a
 * program's After Activity Report from the evaluation dashboard numbers.
 */
final class EvaluationFindings
{
    /**
     * Same bands as the legend on the evaluation form's overall rating question.
     *
     * @var array<int, array{label: string, min: int, max: int}>
     */
    private const OVERALL_RATING_BANDS = [
        ['label' => '10 = Very Exceptional', 'min' => 10, 'max' => 10],
        ['label' => '8–9 = Very Good', 'min' => 8, 'max' => 9],
        ['label' => '6–7 = Satisfactory', 'min' => 6, 'max' => 7],
        ['label' => '5 = Passing', 'min' => 5, 'max' => 5],
        ['label' => '3–4 = Fair', 'min' => 3, 'max' => 4],
        ['label' => '2 = Poor', 'min' => 2, 'max' => 2],
        ['label' => '1 = Completely Unacceptable', 'min' => 1, 'max' => 1],
    ];

    /**
     * @param  Collection<int, object{section_key: string, section_title: string, avg_rating: float|string}>  $avgBySection
     * @param  Collection<int, object{section_key: string, label: string, avg_rating: float|string}>  $avgByQuestion
     * @param  Collection<int, object{section_key: string, label: string, value_text: string, total: int|string}>  $radioAnswerCounts
     * @param  Collection<int, object{id: int, name: string, avg_rating: float|string}>  $avgByFacilitator
     * @param  Collection<int, object{rating: int|string, total: int|string}>  $overallDistribution
     * @return array{intro: string, items: array<int, array{label: string, text: string}>}
     */
    public static function build(
        int $totalResponses,
        int $participantCount,
        Collection $avgBySection,
        Collection $avgByQuestion,
        Collection $radioAnswerCounts,
        Collection $avgByFacilitator,
        Collection $overallDistribution,
    ): array {
        if ($totalResponses === 0) {
            return [
                'intro' => 'The training program will be evaluated using feedback forms completed by the participants. No responses have been received yet.',
                'items' => [],
            ];
        }

        $intro = 'The training program was evaluated using feedback forms completed by the participants. '
            .'A total of '.self::spellCount($totalResponses).' '.str('response')->plural($totalResponses).' '.($totalResponses === 1 ? 'was' : 'were').' received';

        if ($participantCount > 0) {
            $intro .= ' out of the '.self::spellCount($participantCount).' '.str('participant')->plural($participantCount)
                .', reflecting a '.self::percent($totalResponses, $participantCount).' response rate';
        }

        $intro .= '. The overall rating given by the participants is broken down as follows:';

        $items = [];

        foreach ($avgBySection as $section) {
            if ($section->section_key === EvaluationSection::KEY_OVERALL) {
                continue;
            }

            $text = self::format((float) $section->avg_rating).' (out of 5) — ';
            $text .= $section->section_key === EvaluationSection::KEY_FACILITATORS
                ? self::facilitatorFindings($avgByFacilitator, $avgByQuestion->where('section_key', $section->section_key))
                : self::areaFindings(
                    (float) $section->avg_rating,
                    $avgByQuestion->where('section_key', $section->section_key),
                    $radioAnswerCounts->where('section_key', $section->section_key),
                );

            $items[] = ['label' => self::sectionName($section->section_title), 'text' => trim($text)];
        }

        $overallFindings = self::overallFindings($overallDistribution);
        if ($overallFindings !== null) {
            $items[] = ['label' => 'Overall Program Rating', 'text' => $overallFindings];
        }

        return ['intro' => $intro, 'items' => $items];
    }

    /**
     * @param  Collection<int, object{section_key: string, label: string, avg_rating: float|string}>  $questions
     * @param  Collection<int, object{section_key: string, label: string, value_text: string, total: int|string}>  $radioAnswers
     */
    private static function areaFindings(float $sectionRating, Collection $questions, Collection $radioAnswers): string
    {
        $text = 'Participants '.self::describeAgreement($sectionRating).' with the statements in this area.';

        $ranked = $questions->sortByDesc(fn ($row) => (float) $row->avg_rating)->values();
        if ($ranked->count() > 1) {
            $highest = $ranked->first();
            $lowest = $ranked->last();
            $text .= ' “'.self::questionLabel($highest->label).'” received the highest rating at '.self::format((float) $highest->avg_rating)
                .', while “'.self::questionLabel($lowest->label).'” ('.self::format((float) $lowest->avg_rating).') was rated the lowest.';
        }

        $choices = $radioAnswers->groupBy('label')->map(function (Collection $answers, string $label) {
            $answered = $answers->sum(fn ($row) => (int) $row->total);
            $top = $answers->sortByDesc(fn ($row) => (int) $row->total)->first();

            return [
                'phrase' => str(self::questionLabel($label))->rtrim(':')->lcfirst().' '.trim($top->value_text).' ('.(int) $top->total.' of '.$answered.')',
                'isMajority' => (int) $top->total * 2 > $answered,
            ];
        })->values();

        if ($choices->isNotEmpty()) {
            $text .= ' '.($choices->every(fn ($choice) => $choice['isMajority']) ? 'Most respondents' : 'The most common responses')
                .' indicated that '.self::naturalList($choices->pluck('phrase')).'.';
        }

        return $text;
    }

    /**
     * @param  Collection<int, object{id: int, name: string, avg_rating: float|string}>  $facilitators
     * @param  Collection<int, object{section_key: string, label: string, avg_rating: float|string}>  $questions
     */
    private static function facilitatorFindings(Collection $facilitators, Collection $questions): string
    {
        if ($facilitators->isEmpty()) {
            return 'No facilitator ratings were recorded.';
        }

        $ranked = $facilitators->sortByDesc(fn ($row) => (float) $row->avg_rating)->values();
        $highest = $ranked->first();
        $lowest = $ranked->last();
        $itemCount = $questions->count();
        $items = $itemCount > 0 ? ' across '.($itemCount === 1 ? 'the rated item' : 'all '.self::spellCount($itemCount).' rated items') : '';

        if ($ranked->count() === 1) {
            $text = 'The facilitator, '.$highest->name.', was rated '.self::describeRating((float) $highest->avg_rating).$items
                .' with an average rating of '.self::format((float) $highest->avg_rating).'.';
        } else {
            $consistency = (float) $lowest->avg_rating >= 4.5 ? 'consistently rated highly' : 'rated';
            $text = 'The '.self::spellCount($ranked->count()).' facilitators were '.$consistency.$items
                .', with individual ratings ranging from '.self::format((float) $lowest->avg_rating).' ('.$lowest->name.') to '
                .self::format((float) $highest->avg_rating).' ('.$highest->name.').';
        }

        $rankedItems = $questions->sortByDesc(fn ($row) => (float) $row->avg_rating)->values();
        if ($rankedItems->count() > 1) {
            $text .= ' “'.self::questionLabel($rankedItems->first()->label).'” received the highest rating at '.self::format((float) $rankedItems->first()->avg_rating)
                .', while “'.self::questionLabel($rankedItems->last()->label).'” ('.self::format((float) $rankedItems->last()->avg_rating).') was rated the lowest.';
        }

        return $text;
    }

    /**
     * @param  Collection<int, object{rating: int|string, total: int|string}>  $distribution
     */
    private static function overallFindings(Collection $distribution): ?string
    {
        $answered = $distribution->sum(fn ($row) => (int) $row->total);
        if ($answered === 0) {
            return null;
        }

        $bands = collect(self::OVERALL_RATING_BANDS)
            ->map(fn (array $band) => [
                'label' => $band['label'],
                'total' => $distribution
                    ->filter(fn ($row) => (int) $row->rating >= $band['min'] && (int) $row->rating <= $band['max'])
                    ->sum(fn ($row) => (int) $row->total),
            ])
            ->filter(fn (array $band) => $band['total'] > 0)
            ->values();

        $first = $bands->first();
        $text = ucfirst(self::spellCount($first['total'])).' of '.$answered.' '.str('respondent')->plural($answered)
            .' ('.self::percent($first['total'], $answered).') rated the program “'.$first['label'].'”';

        if ($bands->count() > 1) {
            $second = $bands[1];
            $text .= ' and '.self::spellCount($second['total']).' '.str('respondent')->plural($second['total'])
                .' ('.self::percent($second['total'], $answered).') rated it “'.$second['label'].'”';
        }
        $text .= '.';

        $remaining = $bands->slice(2);
        if ($remaining->isNotEmpty()) {
            $remainingTotal = $remaining->sum('total');
            $text .= ' The remaining '.self::spellCount($remainingTotal).' '.str('respondent')->plural($remainingTotal).' rated it '
                .self::naturalList($remaining->map(fn (array $band) => '“'.$band['label'].'” ('.$band['total'].')')).'.';
        }

        return $text;
    }

    /**
     * Matches the form's Likert options (5 = Strongly Agree … 2 = Strongly Disagree).
     */
    private static function describeAgreement(float $rating): string
    {
        return match (true) {
            $rating >= 4.5 => 'strongly agreed',
            $rating >= 3.5 => 'agreed',
            $rating >= 2.5 => 'disagreed',
            default => 'strongly disagreed',
        };
    }

    private static function describeRating(float $rating): string
    {
        return match (true) {
            $rating >= 4.5 => 'very highly',
            $rating >= 3.5 => 'highly',
            $rating >= 2.5 => 'fairly',
            default => 'poorly',
        };
    }

    /**
     * Drops the form's roman-numeral prefix ("III. Environment" → "Environment").
     */
    private static function sectionName(string $title): string
    {
        return preg_replace('/^[IVXLC]+\.\s*/', '', $title);
    }

    /**
     * Drops the form's item numbering ("4. Content is relevant" / "b. The pacing" → "Content is relevant" / "The pacing").
     */
    private static function questionLabel(string $label): string
    {
        return trim(preg_replace('/^([0-9]+|[a-z])\.\s*/', '', $label));
    }

    private static function format(float $rating): string
    {
        return number_format($rating, 2);
    }

    private static function percent(int $part, int $whole): string
    {
        return number_format($part / $whole * 100, 1).'%';
    }

    /**
     * @param  Collection<int, string>  $items
     */
    private static function naturalList(Collection $items): string
    {
        $items = $items->values();

        return $items->count() > 2
            ? $items->slice(0, -1)->implode(', ').', and '.$items->last()
            : $items->implode(' and ');
    }

    /**
     * Government report style: "one hundred nineteen (119)".
     */
    private static function spellCount(int $number): string
    {
        return self::spell($number)." ({$number})";
    }

    private static function spell(int $number): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        return match (true) {
            $number < 20 => $ones[$number],
            $number < 100 => $tens[intdiv($number, 10)].($number % 10 ? '-'.$ones[$number % 10] : ''),
            $number < 1000 => $ones[intdiv($number, 100)].' hundred'.($number % 100 ? ' '.self::spell($number % 100) : ''),
            default => self::spell(intdiv($number, 1000)).' thousand'.($number % 1000 ? ' '.self::spell($number % 1000) : ''),
        };
    }
}
