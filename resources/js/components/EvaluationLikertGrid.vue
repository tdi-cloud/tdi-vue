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

const props = defineProps<{
    questions: Question[];
    values: Record<number, number | null>;
    options: Option[];
    namePrefix: string;
    errorFor: (questionId: number) => string | undefined;
}>();
</script>

<template>
    <div class="overflow-x-auto -mx-1 px-1">
        <table class="w-full min-w-[420px] table-fixed text-xs border-collapse">
            <thead>
                <tr>
                    <th class="w-[30%] text-left font-semibold text-gray-500 pb-2 pr-2 align-bottom"></th>
                    <th
                        v-for="opt in options" :key="opt.value"
                        class="text-center font-semibold text-gray-500 pb-2 px-1.5 align-bottom"
                    >{{ opt.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="question in questions" :key="question.id" class="border-t border-gray-100">
                    <td class="py-2.5 pr-3 text-gray-700 font-medium">
                        {{ question.label }} <span v-if="question.is_required" class="text-red-500">*</span>
                    </td>
                    <td v-for="opt in options" :key="opt.value" class="text-center py-2.5 px-1.5">
                        <input
                            type="radio"
                            :name="`${namePrefix}-${question.id}`"
                            :checked="values[question.id] === opt.value"
                            @change="values[question.id] = opt.value"
                            class="h-4 w-4 accent-rose-600 cursor-pointer"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="questions.some((q) => errorFor(q.id))" class="mt-2 text-xs text-red-500">
            Please rate all required items above.
        </p>
    </div>
</template>
