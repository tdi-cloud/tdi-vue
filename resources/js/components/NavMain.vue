<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useMotionLevel } from '@/composables/useMotionLevel';
import { useRipple } from '@/composables/useRipple';
import { useSidebarScrollMemory } from '@/composables/useSidebarMemory';
import { useSlidingIndicator } from '@/composables/useSlidingIndicator';
import { type SharedData, type SidebarNavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        items: SidebarNavItem[];
        /** Play the load-in stagger (only the first sidebar mount after a full page load). */
        intro?: boolean;
        /** Soft glow that follows the mouse inside the hovered item. */
        spotlight?: boolean;
    }>(),
    {
        intro: false,
        spotlight: true,
    },
);

interface NavEntry {
    item: SidebarNavItem;
    introIndex: number;
}

interface NavGroup {
    label: string;
    introIndex: number;
    entries: NavEntry[];
}

/** Stagger slot 0 is the logo in AppSidebar; the nav's labels and items follow it. */
const FIRST_INTRO_INDEX = 1;
const MAX_STAGGER_MS = 35;
/** Caps the total stagger so the load-in stays short even for superadmins (19 items). */
const MAX_TOTAL_STAGGER_MS = 420;

const page = usePage<SharedData>();
const motionLevel = useMotionLevel();

// Compare the path only: list filters add query strings (e.g. /employees?search=…)
// and the item should stay active while they are applied.
const currentPath = computed(() => page.url.split(/[?#]/)[0]);

// Groups items by their `group` (e.g. Monitoring vs Management) in order of first
// appearance, then numbers labels and items in display order for the load-in stagger.
const groupedItems = computed<NavGroup[]>(() => {
    const groups: NavGroup[] = [];

    for (const item of props.items) {
        const label = item.group ?? 'General';
        let group = groups.find((existing) => existing.label === label);
        if (!group) {
            group = { label, introIndex: 0, entries: [] };
            groups.push(group);
        }
        group.entries.push({ item, introIndex: 0 });
    }

    let introIndex = FIRST_INTRO_INDEX;
    for (const group of groups) {
        group.introIndex = introIndex++;
        for (const entry of group.entries) {
            entry.introIndex = introIndex++;
        }
    }

    return groups;
});

const staggerMs = computed(() => {
    const slotCount = Math.max(props.items.length + groupedItems.value.length, 1);

    return Math.min(MAX_STAGGER_MS, Math.round(MAX_TOTAL_STAGGER_MS / slotCount));
});

const activeEntry = computed(() => {
    for (const group of groupedItems.value) {
        const entry = group.entries.find(({ item }) => item.url === currentPath.value);
        if (entry) {
            return entry;
        }
    }

    return null;
});

const navRoot = ref<HTMLElement | null>(null);

const {
    style: pillStyle,
    isVisible: isPillVisible,
    isAnimating: isPillAnimating,
} = useSlidingIndicator(
    navRoot,
    computed(() => activeEntry.value?.item.url ?? null),
    computed(() => motionLevel.value !== 'still'),
);

useSidebarScrollMemory(computed(() => navRoot.value?.closest<HTMLElement>('[data-sidebar="content"]') ?? null));

// Spotlight and ripple are skipped in lite mode, on phones/low-power devices and for reduced motion.
const isFullMotion = computed(() => motionLevel.value === 'full');
const isSpotlightOn = computed(() => props.spotlight && isFullMotion.value);
const { spawnRipple } = useRipple(isFullMotion);

const iconColor = (item: SidebarNavItem): string => item.color ?? 'text-muted-foreground';

// Titles whose label is cut off; those items also get a tooltip while the sidebar is expanded.
const truncatedTitles = reactive(new Set<string>());

function checkTruncation(event: PointerEvent, title: string): void {
    const label = (event.currentTarget as HTMLElement).querySelector<HTMLElement>('.sb-label');

    if (label && label.scrollWidth > label.clientWidth) {
        truncatedTitles.add(title);
    } else {
        truncatedTitles.delete(title);
    }
}

let spotlightFrame = 0;

function trackSpotlight(event: PointerEvent): void {
    if (!isSpotlightOn.value || event.pointerType !== 'mouse') {
        return;
    }

    const item = event.currentTarget as HTMLElement;
    const { clientX, clientY } = event;

    cancelAnimationFrame(spotlightFrame);
    spotlightFrame = requestAnimationFrame(() => {
        const layer = item.querySelector<HTMLElement>('.sb-fx');
        if (!layer) {
            return;
        }

        const bounds = item.getBoundingClientRect();
        layer.style.setProperty('--sb-x', `${clientX - bounds.left}px`);
        layer.style.setProperty('--sb-y', `${clientY - bounds.top}px`);
    });
}

onBeforeUnmount(() => cancelAnimationFrame(spotlightFrame));
</script>

<template>
    <div ref="navRoot" class="sb-nav relative flex flex-col gap-2" :data-sb-spotlight="isSpotlightOn" :style="{ '--sb-stagger': `${staggerMs}ms` }">
        <!-- Sliding highlight behind the active item; its accent bar takes the item's icon color. -->
        <div
            class="sb-pill"
            :class="[activeEntry?.item.color ?? 'text-sidebar-foreground', { 'sb-fade-in': intro }]"
            :data-animate="isPillAnimating"
            :data-visible="isPillVisible"
            :style="{ ...pillStyle, '--sb-i': activeEntry?.introIndex ?? 0 }"
            aria-hidden="true"
        >
            <span class="sb-pill-bar" />
        </div>

        <SidebarGroup v-for="(group, groupIndex) in groupedItems" :key="group.label" class="px-2 py-0">
            <SidebarGroupLabel
                :class="[
                    'sb-group-label group-data-[collapsible=icon]:mt-0 group-data-[collapsible=icon]:opacity-100',
                    groupIndex === 0 ? 'group-data-[collapsible=icon]:h-2' : 'group-data-[collapsible=icon]:h-4',
                    { 'sb-enter': intro },
                ]"
                :data-first="groupIndex === 0 ? '' : undefined"
                :style="{ '--sb-i': group.introIndex }"
            >
                <span class="sb-group-text">{{ group.label }}</span>
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem
                    v-for="entry in group.entries"
                    :key="entry.item.title"
                    :class="{ 'sb-enter': intro }"
                    :style="{ '--sb-i': entry.introIndex }"
                >
                    <SidebarMenuButton
                        as-child
                        :is-active="entry.item.url === currentPath"
                        :tooltip="entry.item.title"
                        :tooltip-when-expanded="truncatedTitles.has(entry.item.title)"
                        class="sb-item relative isolate data-[active=true]:bg-transparent data-[active=true]:hover:bg-transparent"
                    >
                        <Link
                            :href="entry.item.url"
                            @pointerenter="checkTruncation($event, entry.item.title)"
                            @pointermove="trackSpotlight"
                            @pointerdown="spawnRipple"
                        >
                            <span class="sb-fx" :class="iconColor(entry.item)" data-ripple-layer aria-hidden="true" />
                            <span class="sb-icon-wrap flex size-4 shrink-0 items-center justify-center">
                                <component
                                    :is="entry.item.icon"
                                    class="sb-icon size-4"
                                    :class="iconColor(entry.item)"
                                    :data-motion="entry.item.motion"
                                />
                            </span>
                            <span class="sb-label">{{ entry.item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
