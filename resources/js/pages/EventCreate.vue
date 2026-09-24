<script setup lang="ts">
import {
    STEPS,
    stepIndexForField,
    validateStep,
    wizardKey,
    type WizardFormData,
    type WizardLocation,
} from '@/components/event/wizard/context';
import StepAppearance from '@/components/event/wizard/StepAppearance.vue';
import StepBasics from '@/components/event/wizard/StepBasics.vue';
import StepContact from '@/components/event/wizard/StepContact.vue';
import StepReview from '@/components/event/wizard/StepReview.vue';
import StepSchedule from '@/components/event/wizard/StepSchedule.vue';
import StepTables from '@/components/event/wizard/StepTables.vue';
import StepTickets from '@/components/event/wizard/StepTickets.vue';
import StepVenue from '@/components/event/wizard/StepVenue.vue';
import WizardStepper from '@/components/event/wizard/WizardStepper.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    HistoryIcon,
} from 'lucide-vue-next';
import { computed, onMounted, provide, ref, watch } from 'vue';

const props = defineProps<{
    locations: WizardLocation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vytvoriť podujatie', href: '/event/create' },
];

const form = useForm<WizardFormData>({
    title: '',
    overline: '',
    url_slug: '',
    description: '',
    start_time: '',
    registration_start: '',
    registration_end: '',
    // Preselect the venue when there is only one to choose from.
    location_id: props.locations.length === 1 ? props.locations[0].id : '',
    seats_total: '',
    multiple_reservations_per_ticket: false,
    tables_enabled: false,
    tables: [],
    tickets: [{ title: 'Vstupenka', price: '', reservations: 1 }],
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    bank_account: '',
    background_image: null,
    logo: null,
});

const current = ref(0);
const completed = ref(new Set<number>());
const clientErrors = ref<Record<string, string>>({});
const slugTouched = ref(false);

const selectedLocation = computed(
    () =>
        props.locations.find(
            (location) => String(location.id) === String(form.location_id),
        ) ?? null,
);

const stepComponents = {
    basics: StepBasics,
    schedule: StepSchedule,
    venue: StepVenue,
    tables: StepTables,
    tickets: StepTickets,
    contact: StepContact,
    appearance: StepAppearance,
    review: StepReview,
};

const step = computed(() => STEPS[current.value]);
const isLast = computed(() => current.value === STEPS.length - 1);

const stepsWithErrors = computed(() => {
    const steps = new Set<number>();
    [...Object.keys(form.errors), ...Object.keys(clientErrors.value)].forEach(
        (key) => {
            const index = stepIndexForField(key);
            if (index >= 0) steps.add(index);
        },
    );
    return steps;
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/** Validates one step; shows its errors and returns false when it doesn't pass. */
function checkStep(index: number): boolean {
    const errors = validateStep(STEPS[index].key, form, props.locations);
    if (Object.keys(errors).length) {
        clientErrors.value = errors;
        return false;
    }
    completed.value = new Set(completed.value).add(index);
    return true;
}

function goTo(index: number) {
    if (index === current.value) return;
    clientErrors.value = {};

    // Moving forward requires every step in between to be valid.
    for (let i = current.value; i < index; i++) {
        if (!checkStep(i)) {
            current.value = i;
            scrollToTop();
            return;
        }
    }
    current.value = index;
    scrollToTop();
}

function next() {
    if (!checkStep(current.value)) return;
    clientErrors.value = {};
    current.value++;
    scrollToTop();
}

function back() {
    clientErrors.value = {};
    if (current.value > 0) current.value--;
    scrollToTop();
}

// Once a step has been checked, drop each error as soon as the user fixes it.
watch(
    () => form.data(),
    () => {
        if (!Object.keys(clientErrors.value).length) return;
        const fresh = validateStep(step.value.key, form, props.locations);
        clientErrors.value = Object.fromEntries(
            Object.entries(fresh).filter(([key]) => key in clientErrors.value),
        );
    },
    { deep: true },
);

provide(wizardKey, {
    form,
    locations: props.locations,
    selectedLocation,
    clientErrors,
    slugTouched,
    goTo,
});

// ---- Draft autosave (everything except the image files) ----
const DRAFT_KEY = 'event-wizard-draft';
const pendingDraft = ref<{ savedAt: string; data: any } | null>(null);

function readDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function clearDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY);
    } catch {
        // Storage unavailable (private mode etc.): nothing to clear.
    }
}

const saveDraft = useDebounceFn(() => {
    if (!form.isDirty) return;
    const { background_image: _, logo: __, ...data } = form.data();
    try {
        localStorage.setItem(
            DRAFT_KEY,
            JSON.stringify({
                savedAt: new Date().toISOString(),
                step: current.value,
                slugTouched: slugTouched.value,
                data,
            }),
        );
    } catch {
        // Storage full or blocked: autosave is a convenience, ignore.
    }
}, 500);

onMounted(() => {
    const draft = readDraft();
    if (draft?.data) pendingDraft.value = draft;

    // Start saving only after the user decides what to do with an older draft.
    watch(
        [() => form.data(), current],
        () => {
            if (!pendingDraft.value) saveDraft();
        },
        { deep: true },
    );
});

function restoreDraft() {
    const draft = readDraft();
    pendingDraft.value = null;
    if (!draft?.data) return;

    const known = Object.keys(form.data());
    Object.entries(draft.data).forEach(([key, value]) => {
        if (known.includes(key)) (form as any)[key] = value;
    });
    if (!selectedLocation.value) form.location_id = '';
    slugTouched.value = !!draft.slugTouched;

    const step = Math.min(Number(draft.step) || 0, STEPS.length - 1);
    completed.value = new Set(Array.from({ length: step }, (_, i) => i));
    current.value = step;
}

function discardDraft() {
    pendingDraft.value = null;
    clearDraft();
}

const draftSavedAt = computed(() =>
    pendingDraft.value
        ? new Date(pendingDraft.value.savedAt).toLocaleString('sk-SK', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '',
);

// ---- Submit ----
function submit() {
    // Re-check everything: steps may have been skipped via a restored draft.
    for (let i = 0; i < STEPS.length - 1; i++) {
        if (!checkStep(i)) {
            current.value = i;
            scrollToTop();
            return;
        }
    }

    form.transform((data) => ({
        ...data,
        tables: data.tables_enabled ? data.tables : [],
    })).post('/event', {
        forceFormData: true,
        onSuccess: () => clearDraft(),
        onError: (errors) => {
            const first = Math.min(
                ...Object.keys(errors)
                    .map(stepIndexForField)
                    .filter((index) => index >= 0),
            );
            if (Number.isFinite(first)) current.value = first;
            scrollToTop();
        },
    });
}

function onEnter() {
    if (isLast.value) submit();
    else next();
}
</script>

<template>
    <Head title="Vytvoriť podujatie" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 lg:flex-row lg:items-start"
        >
            <aside class="shrink-0 lg:sticky lg:top-4 lg:w-72">
                <h1 class="mb-4 text-2xl font-bold">Nové podujatie</h1>
                <WizardStepper
                    :current="current"
                    :completed="completed"
                    :with-errors="stepsWithErrors"
                    @select="goTo"
                />
            </aside>

            <form
                novalidate
                class="min-w-0 flex-1 rounded-xl border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border"
                @submit.prevent="onEnter"
            >
                <div
                    v-if="pendingDraft"
                    class="mb-6 flex flex-col gap-3 rounded-lg border border-blue-300 bg-blue-50 p-4 sm:flex-row sm:items-center dark:border-blue-800 dark:bg-blue-950/30"
                >
                    <HistoryIcon class="size-5 shrink-0 text-blue-600" />
                    <p class="flex-1 text-sm">
                        Máte rozpracované podujatie z {{ draftSavedAt }}. Chcete
                        v ňom pokračovať?
                    </p>
                    <div class="flex gap-2">
                        <Button type="button" size="sm" @click="restoreDraft">
                            Pokračovať
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="discardDraft"
                        >
                            Začať odznova
                        </Button>
                    </div>
                </div>

                <div
                    v-if="
                        stepsWithErrors.size && Object.keys(form.errors).length
                    "
                    class="mb-6 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"
                >
                    <AlertCircleIcon class="mt-0.5 size-4 shrink-0" />
                    <span>
                        Podujatie sa nepodarilo vytvoriť. Opravte údaje v
                        krokoch:
                        {{
                            [...stepsWithErrors]
                                .sort()
                                .map((i) => STEPS[i].title)
                                .join(', ')
                        }}.
                    </span>
                </div>

                <header class="mb-6">
                    <p class="text-sm text-muted-foreground">
                        Krok {{ current + 1 }} z {{ STEPS.length }}
                    </p>
                    <h2 class="text-xl font-semibold">{{ step.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ step.description }}
                    </p>
                </header>

                <component :is="stepComponents[step.key]" />

                <footer
                    class="mt-8 flex items-center justify-between gap-2 border-t pt-4"
                >
                    <Button
                        v-if="current > 0"
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="back"
                    >
                        <ArrowLeftIcon class="size-4" /> Späť
                    </Button>
                    <Button
                        v-else
                        type="button"
                        variant="ghost"
                        @click="router.visit('/dashboard')"
                    >
                        Zrušiť
                    </Button>

                    <Button
                        v-if="!isLast"
                        type="submit"
                        class="bg-blue-600 text-white hover:bg-blue-700"
                    >
                        Ďalej <ArrowRightIcon class="size-4" />
                    </Button>
                    <Button
                        v-else
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 text-white hover:bg-blue-700"
                    >
                        {{
                            form.processing
                                ? 'Vytvára sa…'
                                : 'Vytvoriť podujatie'
                        }}
                    </Button>
                </footer>
            </form>
        </div>
    </AppLayout>
</template>
