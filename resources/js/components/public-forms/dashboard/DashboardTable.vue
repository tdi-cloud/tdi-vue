<script setup lang="ts">
/** The table view of a dashboard chart (screen readers, lite mode and print). */
defineProps<{
    caption: string;
    columns: { key: string; label: string; numeric?: boolean }[];
    rows: Record<string, string | number | null>[];
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-xl border bg-background">
        <table class="w-full border-collapse text-left text-xs">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead class="bg-muted/70 text-[11px] uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-3 py-2 font-semibold"
                        :class="column.numeric ? 'whitespace-nowrap text-right' : ''"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="(row, index) in rows" :key="index" class="break-inside-avoid">
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-3 py-1.5 align-top"
                        :class="column.numeric ? 'whitespace-nowrap text-right tabular-nums' : 'break-words'"
                    >
                        {{ row[column.key] ?? '—' }}
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="columns.length" class="px-3 py-3 text-center text-muted-foreground">No answers yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
