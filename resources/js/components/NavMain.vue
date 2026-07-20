<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Component } from 'vue';

interface NavItem {
    title: string;
    url: string;
    icon: Component;
    color?: string;
    group?: string;
}

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

// Pinagsasama-sama ang mga item ayon sa `group` nila (hal. Monitoring vs
// Management), habang pinapanatili ang pagkakasunod-sunod ng unang lumabas
// na group sa listahan.
const groupedItems = computed(() => {
    const groups: { label: string; items: NavItem[] }[] = [];

    for (const item of props.items) {
        const label = item.group ?? 'General';
        let group = groups.find((g) => g.label === label);
        if (!group) {
            group = { label, items: [] };
            groups.push(group);
        }
        group.items.push(item);
    }

    return groups;
});
</script>

<template>
    <SidebarGroup v-for="group in groupedItems" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton as-child :is-active="item.url === page.url">
                    <Link :href="item.url">
                        <component :is="item.icon" :class="['h-4 w-4', item.color ?? 'text-muted-foreground']" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
