<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhrdcMember extends Model
{
    /**
     * The committee "position" is not stored directly — it is derived from
     * roster order (admin-orderable via moveUp()/moveDown()), so it
     * re-adjusts automatically whenever a member is added, removed, or
     * reordered: the first member in the roster is Chairperson, the next is
     * Vice Chairperson, and everyone else is a plain Member.
     */
    public const ROLE_CHAIRPERSON = 'Chairperson, HRDC';

    public const ROLE_VICE_CHAIRPERSON = 'Vice Chairperson, HRDC';

    public const ROLE_MEMBER = 'Member';

    protected $fillable = ['empcode', 'sort_order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'empcode', 'EMPCODE');
    }

    public static function roleForIndex(int $index): string
    {
        return match ($index) {
            0 => self::ROLE_CHAIRPERSON,
            1 => self::ROLE_VICE_CHAIRPERSON,
            default => self::ROLE_MEMBER,
        };
    }

    /**
     * Full roster ordered by seniority (roster order first, earliest-added
     * as a tiebreak), with each member's committee role appended.
     */
    public static function rosterWithRoles(): Collection
    {
        return static::with('employee')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (self $member, int $index) => tap($member, fn (self $m) => $m->role = self::roleForIndex($index)));
    }

    public static function roleFor(string $empcode): string
    {
        $index = static::orderBy('sort_order')->orderBy('id')->pluck('empcode')->search($empcode);

        return $index === false ? self::ROLE_MEMBER : self::roleForIndex($index);
    }

    public static function nextSortOrder(): int
    {
        return static::count();
    }

    public function moveUp(): void
    {
        $this->reorder(-1);
    }

    public function moveDown(): void
    {
        $this->reorder(1);
    }

    /**
     * Swaps this member's rank with the member `$delta` positions away, then
     * renumbers the whole roster sequentially (0, 1, 2, …).
     *
     * Ranks are resolved from the same (sort_order, id) ordering used for
     * display rather than comparing raw sort_order values directly — this
     * keeps moving a member robust (and self-healing) even if sort_order
     * values were ever left tied or out of sequence.
     */
    private function reorder(int $delta): void
    {
        $ordered = static::orderBy('sort_order')->orderBy('id')->get()->values();
        $position = $ordered->search(fn (self $m) => $m->id === $this->id);
        $target = $position === false ? null : $position + $delta;

        if ($target === null || $target < 0 || $target >= $ordered->count()) {
            return;
        }

        $items = $ordered->all();
        [$items[$position], $items[$target]] = [$items[$target], $items[$position]];

        foreach ($items as $index => $member) {
            if ((int) $member->sort_order !== $index) {
                $member->update(['sort_order' => $index]);
            }
        }
    }
}
