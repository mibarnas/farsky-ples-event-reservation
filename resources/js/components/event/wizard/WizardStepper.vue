<script setup lang="ts">
import { CheckIcon } from 'lucide-vue-next';
import { STEPS } from './context';

defineProps<{
    current: number;
    completed: Set<number>;
    withErrors: Set<number>;
}>();

const emit = defineEmits<{ (e: 'select', index: number): void }>();
</script>

<template>
    <!-- Mobile: compact progress -->
    <div class="lg:hidden">
        <div class="mb-2 flex items-baseline justify-between text-sm">
            <span class="font-medium">{{ STEPS[current].title }}</span>
            <span class="text-muted-foreground">
                Krok {{ current + 1 }} z {{ STEPS.length }}
            </span>
        </div>
        <div class="flex gap-1">
            <button
                v-for="(step, index) in STEPS"
                :key="step.key"
                type="button"
                class="h-1.5 flex-1 rounded-full transition-colors"
                :class="
                    withErrors.has(index)
                        ? 'bg-red-500'
                        : index <= current || completed.has(index)
                          ? 'bg-blue-600'
                          : 'bg-muted'
                "
                :aria-label="`${index + 1}. ${step.title}`"
                @click="emit('select', index)"
            ></button>
        </div>
    </div>

    <!-- Desktop: vertical list -->
    <nav class="hidden lg:block" aria-label="Kroky vytvorenia podujatia">
        <ol class="space-y-1">
            <li v-for="(step, index) in STEPS" :key="step.key">
                <button
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition-colors hover:bg-muted/60"
                    :class="{ 'bg-muted': index === current }"
                    :aria-current="index === current ? 'step' : undefined"
                    @click="emit('select', index)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium"
                        :class="
                            withErrors.has(index)
                                ? 'border-red-500 bg-red-500 text-white'
                                : index === current
                                  ? 'border-blue-600 bg-blue-600 text-white'
                                  : completed.has(index)
                                    ? 'border-blue-600 text-blue-600'
                                    : 'text-muted-foreground'
                        "
                    >
                        <CheckIcon
                            v-if="
                                completed.has(index) &&
                                index !== current &&
                                !withErrors.has(index)
                            "
                            class="size-4"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block text-sm font-medium"
                            :class="{
                                'text-muted-foreground':
                                    index > current && !completed.has(index),
                            }"
                        >
                            {{ step.title }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            {{ step.description }}
                        </span>
                    </span>
                </button>
            </li>
        </ol>
    </nav>
</template>
