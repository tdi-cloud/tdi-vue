<script setup lang="ts">
interface Question {
    id: number;
    label: string;
    is_required: boolean;
}

interface Option {
    value: number;
    label: string;
}

defineProps<{
    questions: Question[];
    values: Record<number, number | null>;
    options: Option[];
    namePrefix: string;
    errorFor: (questionId: number) => string | undefined;
    onSelect: (questionId: number, value: number) => void;
}>();
</script>

<template>
    <div class="-mx-1 overflow-x-auto px-1">
        <table class="w-full min-w-[420px] table-fixed border-collapse text-xs">
            <thead>
                <tr>
                    <th class="w-[30%] pb-2 pr-2 text-left align-bottom font-semibold text-gray-500"></th>
                    <th v-for="opt in options" :key="opt.value" class="px-1.5 pb-2 text-center align-bottom font-semibold text-gray-500">
                        {{ opt.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="question in questions" :key="question.id" class="border-t border-gray-100">
                    <td class="py-2.5 pr-3 font-medium text-gray-700">
                        {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                    </td>
                    <td v-for="opt in options" :key="opt.value" class="px-1.5 py-2.5 text-center">
                        <input
                            type="radio"
                            :name="`${namePrefix}-${question.id}`"
                            :checked="values[question.id] === opt.value"
                            @change="onSelect(question.id, opt.value)"
                            class="h-4 w-4 cursor-pointer accent-rose-600"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="questions.some((q) => errorFor(q.id))" class="mt-2 text-xs text-red-500">Please rate all required items above.</p>
    </div>
</template>
