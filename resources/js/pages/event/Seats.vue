<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SeatMap from '@/components/SeatMap.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftRightIcon, ArrowRightIcon, SearchIcon, XIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SeatReservation {
    id: number;
    seat_number: number;
    guest_name: string;
    order_name: string;
    order_status: 'paid' | 'pending';
}

const props = defineProps<{
    event: { id: number; title: string; url_slug: string; seats_total: number };
    svgMap: string | null;
    reservations: SeatReservation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.event.title, href: `/event/${props.event.url_slug}/manage` },
    { title: 'Zmena miest', href: `/event/${props.event.url_slug}/seats` },
];

const page = usePage();
const flash = computed(() => (page.props as any).flash as { success?: string; error?: string } | undefined);

const COLORS = {
    free: '#57ff75',
    paid: '#fc2403',
    pending: '#f59e0b',
    selected: '#2563eb',
    target: '#9333ea',
};

const bySeat = computed(() => {
    const map = new Map<number, SeatReservation>();
    props.reservations.forEach((reservation) => map.set(reservation.seat_number, reservation));
    return map;
});

const selectedId = ref<number | null>(null);
const targetSeat = ref<number | null>(null);
const hoveredSeat = ref<number | null>(null);
const search = ref('');

const selected = computed(() => props.reservations.find((r) => r.id === selectedId.value) ?? null);
const occupant = computed(() => {
    if (targetSeat.value === null || !selected.value) return null;
    const res = bySeat.value.get(targetSeat.value);
    return res && res.id !== selected.value.id ? res : null;
});

const seatColors = computed(() => {
    const colors: Record<number, string> = {};
    props.reservations.forEach((r) => {
        colors[r.seat_number] = r.order_status === 'paid' ? COLORS.paid : COLORS.pending;
    });
    if (selected.value) colors[selected.value.seat_number] = COLORS.selected;
    if (targetSeat.value !== null) colors[targetSeat.value] = COLORS.target;
    return colors;
});

const filteredReservations = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.reservations;
    return props.reservations.filter(
        (r) =>
            r.guest_name.toLowerCase().includes(q) ||
            r.order_name.toLowerCase().includes(q) ||
            String(r.seat_number) === q,
    );
});

const hoverLabel = computed(() => {
    if (hoveredSeat.value === null) return null;
    const res = bySeat.value.get(hoveredSeat.value);
    return res ? `Miesto ${hoveredSeat.value}: ${res.guest_name} (${res.order_name})` : `Miesto ${hoveredSeat.value}: voľné`;
});

const form = useForm<{ seat_number: number | null }>({ seat_number: null });

function selectGuest(reservation: SeatReservation) {
    selectedId.value = reservation.id;
    targetSeat.value = null;
    form.clearErrors();
}

function reset() {
    selectedId.value = null;
    targetSeat.value = null;
    form.clearErrors();
}

function onSeatClick(seat: number) {
    form.clearErrors();
    if (!selected.value) {
        const res = bySeat.value.get(seat);
        if (res) selectGuest(res);
        return;
    }
    if (seat === selected.value.seat_number) {
        reset();
        return;
    }
    targetSeat.value = targetSeat.value === seat ? null : seat;
}

const targetInput = computed({
    get: () => (targetSeat.value === null ? '' : String(targetSeat.value)),
    set: (value: string | number) => {
        const parsed = parseInt(String(value), 10);
        targetSeat.value = Number.isFinite(parsed) && parsed !== selected.value?.seat_number ? parsed : null;
    },
});

function submit() {
    if (!selected.value || targetSeat.value === null) return;
    form.seat_number = targetSeat.value;
    form.put(`/event/${props.event.url_slug}/reservation/${selected.value.id}/seat`, {
        preserveScroll: true,
        onSuccess: () => reset(),
    });
}
</script>

<template>
    <Head :title="`Zmena miest – ${event.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-3xl font-bold">Zmena miest</h1>
                <p class="text-muted-foreground">
                    Vyberte hosťa (na mape alebo v zozname) a potom cieľové miesto. Ak je miesto voľné, hosť sa presunie;
                    ak je obsadené, hostia si miesta vymenia.
                </p>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-xl border border-green-300 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
            >
                {{ flash.error }}
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
                <!-- Seat map -->
                <div class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="{ background: COLORS.free }"></span>Voľné</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="{ background: COLORS.paid }"></span>Zaplatené</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="{ background: COLORS.pending }"></span>Čaká na platbu</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="{ background: COLORS.selected }"></span>Vybraný hosť</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="{ background: COLORS.target }"></span>Cieľové miesto</span>
                    </div>
                    <p class="h-5 text-sm text-muted-foreground">{{ hoverLabel }}</p>
                    <SeatMap
                        v-if="svgMap"
                        :src="svgMap"
                        :colors="seatColors"
                        :default-color="COLORS.free"
                        @seat-click="onSeatClick"
                        @seat-hover="hoveredSeat = $event"
                    />
                    <p v-else class="text-sm text-muted-foreground">Toto podujatie nemá mapu sedenia.</p>
                </div>

                <!-- Side panel -->
                <div class="flex flex-col gap-4">
                    <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <h2 class="mb-3 text-lg font-semibold">Presun</h2>

                        <p v-if="!selected" class="text-sm text-muted-foreground">
                            1. Kliknite na obsadené miesto alebo na hosťa v zozname.
                        </p>

                        <div v-else class="flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-2 rounded-lg border border-blue-300 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                                <div>
                                    <p class="font-medium">{{ selected.guest_name }}</p>
                                    <p class="text-sm text-muted-foreground">Miesto {{ selected.seat_number }} · {{ selected.order_name }}</p>
                                </div>
                                <Button variant="ghost" size="sm" class="h-7 w-7 cursor-pointer p-0" title="Zrušiť výber" @click="reset">
                                    <XIcon class="h-4 w-4" />
                                </Button>
                            </div>

                            <div>
                                <Label for="target-seat">2. Cieľové miesto (kliknite na mapu alebo zadajte číslo)</Label>
                                <Input id="target-seat" v-model="targetInput" class="mt-1" type="number" min="0" placeholder="Číslo miesta" />
                            </div>

                            <template v-if="targetSeat !== null">
                                <div
                                    v-if="occupant"
                                    class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950"
                                >
                                    <ArrowLeftRightIcon class="h-4 w-4 shrink-0" />
                                    <span>
                                        <strong>{{ selected.guest_name }}</strong> ({{ selected.seat_number }}) a
                                        <strong>{{ occupant.guest_name }}</strong> ({{ targetSeat }}) si vymenia miesta.
                                    </span>
                                </div>
                                <div
                                    v-else
                                    class="flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 p-3 text-sm dark:border-green-800 dark:bg-green-950"
                                >
                                    <ArrowRightIcon class="h-4 w-4 shrink-0" />
                                    <span>
                                        <strong>{{ selected.guest_name }}</strong> sa presunie z miesta {{ selected.seat_number }} na voľné miesto
                                        {{ targetSeat }}.
                                    </span>
                                </div>
                            </template>

                            <p v-if="form.errors.seat_number" class="text-sm text-red-500">{{ form.errors.seat_number }}</p>

                            <Button
                                class="cursor-pointer bg-blue-600 text-white hover:bg-blue-700"
                                :disabled="targetSeat === null || form.processing"
                                @click="submit"
                            >
                                {{ occupant ? 'Vymeniť miesta' : 'Presunúť hosťa' }}
                            </Button>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <h2 class="mb-3 text-lg font-semibold">Hostia ({{ reservations.length }})</h2>
                        <div class="relative mb-3">
                            <SearchIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" class="pl-9" placeholder="Hľadať meno alebo číslo miesta" />
                        </div>
                        <div class="max-h-[50vh] space-y-1 overflow-y-auto">
                            <button
                                v-for="reservation in filteredReservations"
                                :key="reservation.id"
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 text-left transition-colors hover:bg-muted"
                                :class="reservation.id === selectedId ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-transparent'"
                                @click="selectGuest(reservation)"
                            >
                                <span class="w-10 shrink-0 text-right font-mono text-sm">{{ reservation.seat_number }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ reservation.guest_name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ reservation.order_name }}</span>
                                </span>
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ background: reservation.order_status === 'paid' ? COLORS.paid : COLORS.pending }"
                                    :title="reservation.order_status === 'paid' ? 'Zaplatené' : 'Čaká na platbu'"
                                ></span>
                            </button>
                            <p v-if="filteredReservations.length === 0" class="py-4 text-center text-sm text-muted-foreground">Nič sa nenašlo.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
