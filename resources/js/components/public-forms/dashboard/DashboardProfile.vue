<script setup lang="ts">
import type { DashboardCountRow, FormDashboardData } from '@/lib/publicForms';
import { computed } from 'vue';
import { formatPercent } from './charts';
import DashboardBarList from './DashboardBarList.vue';
import DashboardCard from './DashboardCard.vue';
import DashboardDonutChart from './DashboardDonutChart.vue';
import DashboardTable from './DashboardTable.vue';

/** Who responded: by region, office/division, sex, plantilla status and salary grade. */
const props = defineProps<{ profile: FormDashboardData['profile'] }>();

const COLUMNS = [
    { key: 'label', label: 'Group' },
    { key: 'count', label: 'People', numeric: true },
    { key: 'percent', label: 'Share of responses', numeric: true },
];

function tableRows(rows: DashboardCountRow[]) {
    return rows.map((row) => ({ label: row.label, count: row.count.toLocaleString(), percent: formatPercent(row.percent) }));
}

const regionNote = computed(() => {
    const top = props.profile.regions[0];
    return top && top.label !== 'Not stated' ? `Most responses came from ${top.label} (${formatPercent(top.percent)}).` : undefined;
});

const officeNote = computed(() => {
    const top = props.profile.offices[0];
    return top && top.label !== 'Not stated' && top.label !== 'Others' ? `${top.label} sent the most (${formatPercent(top.percent)}).` : undefined;
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <DashboardCard title="By region" :subtitle="regionNote" print-chart class="md:row-span-2">
            <template #chart>
                <DashboardBarList :rows="profile.regions" percent-of="of all responses" label="Responses by region" />
            </template>
            <template #table>
                <DashboardTable caption="Responses by region" :columns="COLUMNS" :rows="tableRows(profile.regions)" />
            </template>
        </DashboardCard>

        <DashboardCard title="By office or division" :subtitle="officeNote ?? 'The 10 offices with the most responses.'" print-chart>
            <template #chart>
                <DashboardBarList :rows="profile.offices" percent-of="of all responses" label="Responses by office or division" />
            </template>
            <template #table>
                <DashboardTable caption="Responses by office or division" :columns="COLUMNS" :rows="tableRows(profile.offices)" />
            </template>
        </DashboardCard>

        <DashboardCard title="By salary grade" subtitle="Grouped in salary grade bands." print-chart>
            <template #chart>
                <DashboardBarList :rows="profile.salary_grade" percent-of="of all responses" label="Responses by salary grade" />
            </template>
            <template #table>
                <DashboardTable caption="Responses by salary grade" :columns="COLUMNS" :rows="tableRows(profile.salary_grade)" />
            </template>
        </DashboardCard>

        <DashboardCard title="By sex">
            <template #chart>
                <DashboardDonutChart :rows="profile.sex" center-label="Respondents" label="Responses by sex" />
            </template>
            <template #table>
                <DashboardTable caption="Responses by sex" :columns="COLUMNS" :rows="tableRows(profile.sex)" />
            </template>
        </DashboardCard>

        <DashboardCard title="By plantilla status">
            <template #chart>
                <DashboardDonutChart :rows="profile.plantilla_status" center-label="Respondents" label="Responses by plantilla status" />
            </template>
            <template #table>
                <DashboardTable caption="Responses by plantilla status" :columns="COLUMNS" :rows="tableRows(profile.plantilla_status)" />
            </template>
        </DashboardCard>
    </div>
</template>
