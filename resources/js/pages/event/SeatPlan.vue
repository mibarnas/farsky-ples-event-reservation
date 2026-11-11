<script setup lang="ts">
import SeatMap from '@/components/SeatMap.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { seatRadius, svgPoint, type ExtraSeat } from '@/lib/extraSeats';
import type { EventTable } from '@/lib/seatRanges';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    LockIcon,
    MagnetIcon,
    MousePointer2Icon,
    PlusIcon,
    RotateCcwIcon,
    SaveIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

const props = defineProps<{
    event: { id: number; title: string; url_slug: string; seats_total: number };
    svgMap: string | null;
    tables: EventTable[];
    // Seat numbers of the location's map; these can't be edited here
    baseSeats: number[];
    extraSeats: ExtraSeat[];
    // Seats with an active reservation; added seats among them can't be removed
    reservedSeats: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.event.title, href: `/event/${props.event.url_slug}/manage` },
    {
        title: 'Editor plániku sedenia',
        href: `/event/${props.event.url_slug}/seat-plan`,
    },
];

const page = usePage();
const flash = computed(
    () =>
        (page.props as any).flash as
            | { success?: string; error?: string }
            | undefined,
);
const serverErrors = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>),
);

// Soft fills keep the dark seat numbers readable; the outline carries the hue.
// Deliberately not the booking colours (green = free, red = booked) used elsewhere.
const STYLE = {
    base: { fill: '#f4f4f5', stroke: '#a1a1aa' },
    added: { fill: '#ddd6fe', stroke: '#7c3aed' },
    reserved: { fill: '#fecaca', stroke: '#dc2626' },
    selected: { fill: '#bfdbfe', stroke: '#2563eb' },
};
const RING = '#2563eb';

const clone = (seats: ExtraSeat[]) => seats.map((seat) => ({ ...seat }));
const seats = ref<ExtraSeat[]>(clone(props.extraSeats));
const selectedSeat = ref<number | null>(null);
// After a save the page reloads with fresh props
watch(
    () => props.extraSeats,
    (value) => {
        seats.value = clone(value);
        selectedSeat.value = null;
    },
);

const baseSet = computed(() => new Set(props.baseSeats));
const reservedSet = computed(() => new Set(props.reservedSeats));
const usedNumbers = computed(
    () => new Set([...props.baseSeats, ...seats.value.map((s) => s.seat)]),
);

/** First unused seat number from `from` on; by default after the highest one. */
function nextFreeNumber(from?: number) {
    let candidate = from ?? Math.max(-1, ...usedNumbers.value) + 1;
    while (usedNumbers.value.has(candidate)) candidate++;
    return candidate;
}

type Mode = 'add' | 'select';
const mode = ref<Mode>('add');
const snap = ref(true);
const nextNumber = ref<number>(nextFreeNumber());
const hoveredSeat = ref<number | null>(null);
const notice = ref('');

// The number the next placed seat will actually get
const upcomingNumber = computed(() =>
    nextFreeNumber(Math.max(0, Math.floor(nextNumber.value || 0))),
);

const selected = computed(
    () => seats.value.find((s) => s.seat === selectedSeat.value) ?? null,
);
const selectedReserved = computed(
    () => !!selected.value && reservedSet.value.has(selected.value.seat),
);

const dirty = computed(
    () => JSON.stringify(seats.value) !== JSON.stringify(props.extraSeats),
);

function styleFor(seat: number) {
    if (seat === selectedSeat.value) return STYLE.selected;
    if (baseSet.value.has(seat)) return STYLE.base;
    return reservedSet.value.has(seat) ? STYLE.reserved : STYLE.added;
}

const seatFills = computed(() => {
    const fills: Record<number, string> = {};
    [...props.baseSeats, ...seats.value.map((s) => s.seat)].forEach(
        (seat) => (fills[seat] = styleFor(seat).fill),
    );
    return fills;
});
const seatStrokes = computed(() => {
    const strokes: Record<number, string> = {};
    [...props.baseSeats, ...seats.value.map((s) => s.seat)].forEach(
        (seat) => (strokes[seat] = styleFor(seat).stroke),
    );
    return strokes;
});

// ---- Map geometry ----
const mapRef = ref<HTMLElement | null>(null);
const svgEl = () => mapRef.value?.querySelector('svg') ?? null;

/** Centres and radius of every seat currently drawn on the map. */
function drawnSeats() {
    const nodes = Array.from(
        mapRef.value?.querySelectorAll<SVGCircleElement>('circle.seat') ?? [],
    );
    return nodes.map((node) => ({
        seat: parseInt(node.dataset.seat ?? '', 10),
        x: node.cx.baseVal.value,
        y: node.cy.baseVal.value,
        r: node.r.baseVal.value,
    }));
}

const round = (value: number) => Math.round(value * 10) / 10;

/** The map's visible area (viewBox) in SVG user units. */
function mapBounds() {
    const svg = svgEl();
    if (!svg) return null;
    const box = svg.viewBox.baseVal;
    if (box && box.width > 0 && box.height > 0)
        return { x: box.x, y: box.y, width: box.width, height: box.height };
    const width = svg.width.baseVal.value;
    const height = svg.height.baseVal.value;
    return width > 0 && height > 0 ? { x: 0, y: 0, width, height } : null;
}

/** Keeps a seat's centre far enough from the edges that the whole seat stays visible. */
function clampToMap(axis: 'x' | 'y', value: number) {
    const bounds = mapBounds();
    if (!bounds) return value;
    const r = seatRadius(svgEl());
    const [min, size] =
        axis === 'x' ? [bounds.x, bounds.width] : [bounds.y, bounds.height];
    return Math.min(Math.max(value, min + r), min + size - r);
}

/** Where a seat placed at `point` would land (after snapping), and what it would overlap. */
function placement(point: { x: number; y: number }) {
    const drawn = drawnSeats();
    const r = seatRadius(svgEl());
    let { x, y } = point;

    // Line the new seat up with nearby seats' columns and rows
    if (snap.value) {
        const near = (a: number, b: number) => Math.abs(a - b) <= r * 0.8;
        const column = drawn.find(
            (s) => near(s.x, x) && Math.abs(s.y - y) < r * 8,
        );
        const row = drawn.find(
            (s) => near(s.y, y) && Math.abs(s.x - x) < r * 8,
        );
        if (column) x = column.x;
        if (row) y = row.y;
    }

    x = clampToMap('x', x);
    y = clampToMap('y', y);

    const clash = drawn.find((s) => Math.hypot(s.x - x, s.y - y) < r * 1.8);
    return { x, y, clash: clash?.seat ?? null };
}

// ---- Map interaction ----
const preview = ref<{ x: number; y: number; clash: number | null } | null>(
    null,
);

function onMapClick(point: { x: number; y: number }) {
    notice.value = '';
    if (mode.value === 'select') {
        selectedSeat.value = null;
        return;
    }

    const { x, y, clash } = placement(point);
    if (clash !== null) {
        notice.value = `Príliš blízko k miestu ${clash}.`;
        return;
    }

    const number = upcomingNumber.value;
    seats.value = [...seats.value, { seat: number, x: round(x), y: round(y) }];
    selectedSeat.value = number;
    nextNumber.value = nextFreeNumber(number + 1);
    preview.value = null;
}

function onSeatClick(seat: number) {
    notice.value = '';
    if (baseSet.value.has(seat)) {
        notice.value = `Miesto ${seat} patrí k mape lokality a nedá sa tu upraviť.`;
        return;
    }
    selectedSeat.value = selectedSeat.value === seat ? null : seat;
}

function onMouseMove(e: MouseEvent) {
    const svg = svgEl();
    const target = e.target as Element | null;
    if (
        mode.value !== 'add' ||
        !svg ||
        !target ||
        !svg.contains(target) ||
        target.closest('circle.seat')
    ) {
        preview.value = null;
        return;
    }
    const point = svgPoint(svg, e);
    preview.value = point ? placement(point) : null;
}

watch(mode, () => {
    preview.value = null;
    notice.value = '';
});

// ---- Overlay: selection ring and placement preview, drawn into the SVG ----
const SVG_NS = 'http://www.w3.org/2000/svg';
const OVERLAY_CLASS = 'editor-overlay';

function svgNode(tag: string, attrs: Record<string, string | number>) {
    const node = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([key, value]) =>
        node.setAttribute(key, String(value)),
    );
    return node;
}

/** The map's own legend (Voľné/Rezervované/Vybrané) contradicts the editor's colours. */
function hideMapLegend(svg: SVGSVGElement) {
    svg.querySelectorAll(':scope > g').forEach((group) => {
        if (
            group.querySelector('circle:not(.seat)') &&
            !group.querySelector('circle.seat')
        )
            (group as SVGGElement).style.display = 'none';
    });
}

function drawOverlay() {
    const svg = svgEl();
    if (!svg) return;
    svg.querySelector(`g.${OVERLAY_CLASS}`)?.remove();

    const r = seatRadius(svg);
    const group = svgNode('g', {
        class: OVERLAY_CLASS,
        'pointer-events': 'none',
    });

    if (selected.value) {
        group.appendChild(
            svgNode('circle', {
                cx: selected.value.x,
                cy: selected.value.y,
                r: r + 4,
                fill: 'none',
                stroke: RING,
                'stroke-width': 2.5,
            }),
        );
    }

    if (preview.value) {
        const blocked = preview.value.clash !== null;
        const style = blocked ? STYLE.reserved : STYLE.added;
        group.appendChild(
            svgNode('circle', {
                cx: preview.value.x,
                cy: preview.value.y,
                r,
                fill: style.fill,
                stroke: style.stroke,
                'stroke-width': 2,
                'stroke-dasharray': '3 2',
                opacity: 0.85,
            }),
        );
        const label = svgNode('text', {
            x: preview.value.x,
            y: preview.value.y + r * 0.4,
            'text-anchor': 'middle',
            'font-size': (
                r * (upcomingNumber.value >= 100 ? 0.7 : 0.8)
            ).toFixed(1),
            'font-weight': 'bold',
            fill: style.stroke,
        });
        label.textContent = blocked ? '×' : String(upcomingNumber.value);
        group.appendChild(label);
    }

    svg.appendChild(group);
}

function onMapLoaded() {
    const svg = svgEl();
    if (svg) hideMapLegend(svg);
    drawOverlay();
}

watch([selected, preview, upcomingNumber], () => nextTick(drawOverlay), {
    deep: true,
    flush: 'post',
});

// ---- Editing the selected seat ----
const numberDraft = ref('');
watch(
    selectedSeat,
    (seat) => (numberDraft.value = seat === null ? '' : String(seat)),
);

const numberError = computed(() => {
    if (!selected.value) return '';
    const value = Number(numberDraft.value);
    if (!Number.isInteger(value) || value < 0)
        return 'Zadajte celé číslo 0 alebo väčšie.';
    if (value !== selected.value.seat && usedNumbers.value.has(value))
        return `Miesto ${value} už existuje.`;
    return '';
});

function commitNumber() {
    if (!selected.value || numberError.value || selectedReserved.value) return;
    const value = Number(numberDraft.value);
    if (value === selected.value.seat) return;
    const old = selected.value.seat;
    seats.value = seats.value.map((s) =>
        s.seat === old ? { ...s, seat: value } : s,
    );
    selectedSeat.value = value;
}

function updatePosition(axis: 'x' | 'y', value: string | number) {
    const parsed = Number(value);
    if (!selected.value || !Number.isFinite(parsed)) return;
    const seat = selected.value.seat;
    seats.value = seats.value.map((s) =>
        s.seat === seat ? { ...s, [axis]: round(parsed) } : s,
    );
}

function nudge(dx: number, dy: number) {
    if (!selected.value) return;
    updatePosition('x', clampToMap('x', selected.value.x + dx));
    updatePosition('y', clampToMap('y', selected.value.y + dy));
}

/** Pulls seats back inside the map, so none end up invisible or unclickable. */
function clampSeats() {
    seats.value = seats.value.map((s) => ({
        ...s,
        x: round(clampToMap('x', s.x)),
        y: round(clampToMap('y', s.y)),
    }));
}

function removeSelected() {
    if (!selected.value || selectedReserved.value) return;
    const seat = selected.value.seat;
    seats.value = seats.value.filter((s) => s.seat !== seat);
    selectedSeat.value = null;
    nextNumber.value = nextFreeNumber();
}

function reset() {
    seats.value = clone(props.extraSeats);
    selectedSeat.value = null;
    notice.value = '';
    nextNumber.value = nextFreeNumber();
}

// ---- Keyboard: Esc deselects, Delete removes, arrows move (Shift = 10) ----
function onKeydown(e: KeyboardEvent) {
    const target = e.target as HTMLElement | null;
    if (target?.closest('input, textarea, select, [contenteditable="true"]'))
        return;

    if (e.key === 'Escape') {
        selectedSeat.value = null;
        return;
    }
    if (!selected.value) return;

    if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        removeSelected();
        return;
    }

    const step = e.shiftKey ? 10 : 1;
    const moves: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    if (moves[e.key]) {
        e.preventDefault();
        nudge(...moves[e.key]);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// ---- Saving ----
const saving = ref(false);

function save() {
    clampSeats();
    router.put(
        `/event/${props.event.url_slug}/seat-plan`,
        { extra_seats: seats.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
        },
    );
}

const delta = computed(() => seats.value.length - props.extraSeats.length);

const statusLine = computed(() => {
    if (notice.value) return notice.value;
    const seat = hoveredSeat.value;
    if (seat !== null) {
        if (baseSet.value.has(seat))
            return `Miesto ${seat} · mapa lokality (needitovateľné)`;
        return reservedSet.value.has(seat)
            ? `Miesto ${seat} · pridané · má rezerváciu`
            : `Miesto ${seat} · pridané · kliknutím vyberiete`;
    }
    if (preview.value?.clash != null)
        return `Sem sa miesto nezmestí – príliš blízko k miestu ${preview.value.clash}.`;
    return mode.value === 'add'
        ? `Kliknite na voľnú plochu – pridá sa miesto ${upcomingNumber.value}.`
        : 'Kliknite na pridané miesto a upravte ho.';
});

const sortedSeats = computed(() =>
    [...seats.value].sort((a, b) => a.seat - b.seat),
);

const legend = [
    { label: 'Mapa lokality', style: STYLE.base },
    { label: 'Pridané', style: STYLE.added },
    { label: 'Pridané, s rezerváciou', style: STYLE.reserved },
    { label: 'Vybrané', style: STYLE.selected },
];
</script>

<template>
    <Head :title="`Editor plániku sedenia – ${event.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
            <div
                v-if="serverErrors.length"
                class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
            >
                <p v-for="message in serverErrors" :key="message">
                    {{ message }}
                </p>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold">Editor plániku sedenia</h1>
                    <p class="text-sm text-muted-foreground">
                        Pridané miesta platia len pre toto podujatie, mapa
                        lokality ani iné podujatia sa nezmenia.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="dirty"
                        class="text-sm text-amber-600 dark:text-amber-400"
                        >Neuložené zmeny</span
                    >
                    <Button
                        type="button"
                        variant="outline"
                        class="cursor-pointer"
                        :disabled="!dirty || saving"
                        @click="reset"
                    >
                        <RotateCcwIcon class="size-4" /> Zahodiť
                    </Button>
                    <Button
                        type="button"
                        class="cursor-pointer"
                        :disabled="!dirty || saving || !!numberError"
                        @click="save"
                    >
                        <SaveIcon class="size-4" />
                        {{ saving ? 'Ukladám…' : 'Uložiť' }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <!-- Seat map -->
                <div
                    class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <!-- Mode switch -->
                        <div
                            class="inline-flex rounded-lg border border-sidebar-border p-0.5"
                            role="radiogroup"
                            aria-label="Režim editora"
                        >
                            <button
                                v-for="option in [
                                    {
                                        key: 'add' as Mode,
                                        label: 'Pridať miesto',
                                        icon: PlusIcon,
                                    },
                                    {
                                        key: 'select' as Mode,
                                        label: 'Vybrať',
                                        icon: MousePointer2Icon,
                                    },
                                ]"
                                :key="option.key"
                                type="button"
                                role="radio"
                                :aria-checked="mode === option.key"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                                :class="
                                    mode === option.key
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="mode = option.key"
                            >
                                <component :is="option.icon" class="size-4" />
                                {{ option.label }}
                            </button>
                        </div>

                        <label
                            class="inline-flex cursor-pointer items-center gap-2 text-sm text-muted-foreground select-none"
                            title="Zarovná nové miesto do riadku či stĺpca so susednými miestami"
                        >
                            <input
                                v-model="snap"
                                type="checkbox"
                                class="size-4 accent-primary"
                            />
                            <MagnetIcon class="size-4" /> Zarovnávať k susedom
                        </label>
                    </div>

                    <p
                        class="min-h-5 text-sm"
                        :class="
                            notice || preview?.clash != null
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-muted-foreground'
                        "
                        aria-live="polite"
                    >
                        {{ statusLine }}
                    </p>

                    <div
                        v-if="svgMap"
                        ref="mapRef"
                        class="editor-map"
                        :class="{ 'mode-add': mode === 'add' }"
                        @mousemove="onMouseMove"
                        @mouseleave="preview = null"
                    >
                        <SeatMap
                            :src="svgMap"
                            :colors="seatFills"
                            :strokes="seatStrokes"
                            :default-color="STYLE.base.fill"
                            :tables="tables"
                            :extra-seats="seats"
                            @seat-click="onSeatClick"
                            @seat-hover="hoveredSeat = $event"
                            @map-click="onMapClick"
                            @loaded="onMapLoaded"
                        />
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Toto podujatie nemá mapu sedenia.
                    </p>

                    <div
                        class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-for="item in legend"
                            :key="item.label"
                            class="inline-flex items-center gap-1.5"
                        >
                            <span
                                class="size-3 rounded-full border-2"
                                :style="{
                                    background: item.style.fill,
                                    borderColor: item.style.stroke,
                                }"
                            ></span>
                            {{ item.label }}
                        </span>
                    </div>
                </div>

                <!-- Side panel -->
                <div class="flex flex-col gap-4">
                    <!-- Selected seat -->
                    <div
                        v-if="selected"
                        class="flex flex-col gap-3 rounded-xl border-2 border-blue-500/60 bg-card p-4"
                    >
                        <div class="flex items-center justify-between">
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                Miesto {{ selected.seat }}
                                <span
                                    v-if="selectedReserved"
                                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300"
                                >
                                    <LockIcon class="size-3" /> Rezervované
                                </span>
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 cursor-pointer p-0"
                                title="Zrušiť výber (Esc)"
                                @click="selectedSeat = null"
                            >
                                <XIcon class="h-4 w-4" />
                            </Button>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="seat-number">Číslo miesta</Label>
                            <Input
                                id="seat-number"
                                v-model="numberDraft"
                                type="number"
                                min="0"
                                class="h-8"
                                :disabled="selectedReserved"
                                @blur="commitNumber"
                                @keydown.enter.prevent="commitNumber"
                            />
                            <p v-if="numberError" class="text-xs text-red-600">
                                {{ numberError }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1.5">
                                <Label for="seat-x">X</Label>
                                <Input
                                    id="seat-x"
                                    :model-value="selected.x"
                                    type="number"
                                    step="1"
                                    class="h-8"
                                    @update:model-value="
                                        updatePosition('x', $event)
                                    "
                                    @blur="clampSeats"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="seat-y">Y</Label>
                                <Input
                                    id="seat-y"
                                    :model-value="selected.y"
                                    type="number"
                                    step="1"
                                    class="h-8"
                                    @update:model-value="
                                        updatePosition('y', $event)
                                    "
                                    @blur="clampSeats"
                                />
                            </div>
                        </div>

                        <p
                            v-if="selectedReserved"
                            class="text-xs text-muted-foreground"
                        >
                            Miesto má aktívnu rezerváciu, preto ho nemožno
                            odstrániť ani prečíslovať. Hosťa môžete presunúť v
                            „Zmeniť miesta“.
                        </p>
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                            @click="removeSelected"
                        >
                            <Trash2Icon class="size-4" /> Odstrániť miesto
                        </Button>
                    </div>

                    <!-- Next seat -->
                    <div
                        v-if="mode === 'add'"
                        class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4"
                    >
                        <Label for="next-number">Číslo ďalšieho miesta</Label>
                        <Input
                            id="next-number"
                            v-model.number="nextNumber"
                            type="number"
                            min="0"
                            class="h-8"
                        />
                        <p
                            v-if="upcomingNumber !== nextNumber"
                            class="text-xs text-amber-600 dark:text-amber-400"
                        >
                            Miesto {{ nextNumber }} už existuje, použije sa
                            {{ upcomingNumber }}.
                        </p>
                    </div>

                    <!-- Added seats -->
                    <div
                        class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4"
                    >
                        <div class="flex items-baseline justify-between">
                            <h2 class="font-semibold">Pridané miesta</h2>
                            <span class="text-sm text-muted-foreground">{{
                                seats.length
                            }}</span>
                        </div>
                        <p
                            v-if="!seats.length"
                            class="text-sm text-muted-foreground"
                        >
                            Zatiaľ žiadne. V režime „Pridať miesto“ kliknite na
                            voľnú plochu mapy.
                        </p>
                        <div v-else class="flex flex-wrap gap-1.5">
                            <button
                                v-for="seat in sortedSeats"
                                :key="seat.seat"
                                type="button"
                                class="inline-flex min-w-9 cursor-pointer items-center justify-center gap-1 rounded-full border-2 px-2 py-0.5 text-sm font-medium text-zinc-800 transition-shadow"
                                :class="{
                                    'ring-2 ring-blue-500 ring-offset-1 ring-offset-background':
                                        selectedSeat === seat.seat,
                                }"
                                :style="{
                                    background: styleFor(seat.seat).fill,
                                    borderColor: styleFor(seat.seat).stroke,
                                }"
                                @click="selectedSeat = seat.seat"
                            >
                                <LockIcon
                                    v-if="reservedSet.has(seat.seat)"
                                    class="size-3"
                                />
                                {{ seat.seat }}
                            </button>
                        </div>

                        <div
                            class="rounded-lg bg-muted/60 px-3 py-2 text-sm text-muted-foreground"
                        >
                            Miest na predaj:
                            <span class="font-medium text-foreground">{{
                                event.seats_total
                            }}</span>
                            <template v-if="delta !== 0">
                                →
                                <span class="font-medium text-foreground">{{
                                    Math.max(1, event.seats_total + delta)
                                }}</span>
                                po uložení
                            </template>
                        </div>
                    </div>

                    <p class="px-1 text-xs text-muted-foreground">
                        Skratky: <kbd>Esc</kbd> zruší výber,
                        <kbd>Delete</kbd> odstráni miesto, šípky posúvajú (so
                        <kbd>Shift</kbd> o 10).
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* The location's own seats can't be edited here, so they don't look clickable */
.editor-map :deep(circle.seat) {
    cursor: default !important;
}
.editor-map :deep(g.extra-seats circle.seat) {
    cursor: pointer !important;
}
.editor-map.mode-add :deep(svg) {
    cursor: crosshair;
}

kbd {
    border-radius: 0.25rem;
    border: 1px solid var(--border, #e4e4e7);
    padding: 0 0.25rem;
    font-family: inherit;
    font-size: 0.7rem;
}
</style>
