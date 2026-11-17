<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const REPO_URL = 'https://github.com/mrgopes/farsky-ples-event-reservation';

const page = usePage();
const version = computed(() => page.props.appVersion);

const shortCommit = computed(() => version.value?.commit?.slice(0, 7) ?? null);

const deployedAt = computed(() => {
    if (!version.value?.deployed_at) return null;
    return new Date(version.value.deployed_at).toLocaleString('sk-SK', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <span v-if="version && (version.build || shortCommit)" class="text-xs opacity-60">
        Verzia
        <span v-if="version.build">#{{ version.build }}</span>
        <span v-else>dev</span>
        <template v-if="shortCommit">
            ·
            <a :href="`${REPO_URL}/commit/${version.commit}`" target="_blank" class="underline font-mono">{{ shortCommit }}</a>
        </template>
        <template v-if="deployedAt"> · nasadená {{ deployedAt }}</template>
    </span>
</template>
