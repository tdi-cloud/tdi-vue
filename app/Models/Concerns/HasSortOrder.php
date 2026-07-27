<?php

namespace App\Models\Concerns;

/**
 * Admin-orderable list items (evaluation sections/questions/facilitators).
 * Mirrors NhrdcMember's moveUp()/moveDown()/reorder() roster pattern, scoped
 * to whatever sibling group the including model defines via siblingsQuery().
 */
trait HasSortOrder
{
    public function moveUp(): void
    {
        $this->reorderAmongSiblings(-1);
    }

    public function moveDown(): void
    {
        $this->reorderAmongSiblings(1);
    }

    /**
     * Swaps this item's rank with the sibling `$delta` positions away, then
     * renumbers the whole sibling group sequentially (0, 1, 2, …) — robust
     * and self-healing even if sort_order values were ever left tied.
     */
    private function reorderAmongSiblings(int $delta): void
    {
        $ordered = $this->siblingsQuery()->orderBy('sort_order')->orderBy('id')->get()->values();
        $position = $ordered->search(fn ($item) => $item->id === $this->id);
        $target = $position === false ? null : $position + $delta;

        if ($target === null || $target < 0 || $target >= $ordered->count()) {
            return;
        }

        $items = $ordered->all();
        [$items[$position], $items[$target]] = [$items[$target], $items[$position]];

        foreach ($items as $index => $item) {
            if ((int) $item->sort_order !== $index) {
                $item->update(['sort_order' => $index]);
            }
        }
    }
}
