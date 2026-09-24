<script setup lang="ts" generic="K extends string">
import type { Component } from 'vue';
import { onMounted, watch } from 'vue';

export interface PageTab<Key extends string = string> {
    key: Key;
    title: string;
    icon?: Component;
    // Small number shown next to the title (e.g. items waiting for action).
    count?: number;
    // Red dot, e.g. the tab contains validation errors.
    alert?: boolean;
}

const props = defineProps<{
    tabs: PageTab<K>[];
    label: string;
}>();

const active = defineModel<K>({ required: true });

// The active tab lives in the URL hash so reloads and shared links keep it.
onMounted(() => {
    const fromHash = window.location.hash.slice(1);
    const match = props.tabs.find((tab) => tab.key === fromHash);
    if (match) active.value = match.key;
});

watch(active, (key) => {
    // Keep Inertia's history state; only the hash changes.
    window.history.replaceState(window.history.state, '', `#${key}`);
});
</script>

<template>
    <nav
        class="flex gap-1 overflow-x-auto border-b"
        role="tablist"
        :aria-label="label"
    >
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="active === tab.key"
            class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
            :class="
                active === tab.key
                    ? 'border-blue-600 text-foreground'
                    : 'border-transparent text-muted-foreground hover:border-muted-foreground/40 hover:text-foreground'
            "
            @click="active = tab.key"
        >
            <component :is="tab.icon" v-if="tab.icon" class="size-4" />
            {{ tab.title }}
            <span
                v-if="tab.count"
                class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground"
            >
                {{ tab.count }}
            </span>
            <span
                v-if="tab.alert"
                class="size-2 rounded-full bg-red-500"
                aria-label="obsahuje chyby"
            ></span>
        </button>
    </nav>
</template>
