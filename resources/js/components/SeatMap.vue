<script setup lang="ts">
import { drawExtraSeats, svgPoint, type ExtraSeat } from '@/lib/extraSeats';
import type { EventTable } from '@/lib/seatRanges';
import { drawTableLabels } from '@/lib/tableLabels';
import { nextTick, onMounted, ref, watch } from 'vue';

// Renders a location's SVG seat map and colours each `circle.seat` via `colors`.
// Seat numbers are resolved like SeatSelector.vue: data-seat, falling back to DOM order.
// Table names are drawn from the event's `tables` setup, not from the SVG.
// The event's `extraSeats` (added in the seat plan editor) are drawn on top of the map.
const props = defineProps<{
    src: string;
    colors: Record<number, string>;
    defaultColor?: string;
    // Optional outline colour per seat (default #333)
    strokes?: Record<number, string>;
    tables?: EventTable[];
    extraSeats?: ExtraSeat[];
}>();

const emit = defineEmits<{
    (e: 'seat-click', seat: number): void;
    (e: 'seat-hover', seat: number | null): void;
    // A click outside any seat, in the SVG's user units
    (e: 'map-click', point: { x: number; y: number }): void;
    // The map has been loaded and its seats drawn
    (e: 'loaded'): void;
}>();

const containerRef = ref<HTMLElement | null>(null);
const htmlContent = ref('');
const loadError = ref(false);
let seatNodes: { node: SVGCircleElement; seat: number }[] = [];

function paint() {
    seatNodes.forEach(({ node, seat }) => {
        node.setAttribute(
            'fill',
            props.colors[seat] ?? props.defaultColor ?? '#57ff75',
        );
        node.setAttribute('stroke', props.strokes?.[seat] ?? '#333');
        node.setAttribute('stroke-width', '2');
        node.style.cursor = 'pointer';
        node.style.transition = 'fill 0.15s';
    });
}

function labelTables() {
    drawTableLabels(
        containerRef.value,
        new Map(seatNodes.map(({ node, seat }) => [seat, node])),
        props.tables,
    );
}

function seatFromEvent(e: Event): number | null {
    const node = (e.target as Element | null)?.closest?.('circle.seat');
    const match = seatNodes.find((entry) => entry.node === node);
    return match ? match.seat : null;
}

function onClick(e: MouseEvent) {
    const seat = seatFromEvent(e);
    if (seat !== null) {
        emit('seat-click', seat);
        return;
    }
    const svg = containerRef.value?.querySelector('svg');
    const point = svg ? svgPoint(svg, e) : null;
    if (point) emit('map-click', point);
}

function collectSeats() {
    drawExtraSeats(containerRef.value, props.extraSeats);
    const nodes = Array.from(
        containerRef.value?.querySelectorAll('circle.seat') ?? [],
    ) as SVGCircleElement[];
    seatNodes = nodes.map((node, i) => {
        const parsed = parseInt(node.dataset.seat ?? '', 10);
        return { node, seat: Number.isFinite(parsed) ? parsed : i };
    });
    paint();
    labelTables();
}

function onMouseOver(e: MouseEvent) {
    emit('seat-hover', seatFromEvent(e));
}

async function load() {
    loadError.value = false;
    try {
        const res = await fetch(props.src);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        htmlContent.value = await res.text();
        await nextTick();
        collectSeats();
        emit('loaded');
    } catch (e) {
        console.error(e);
        loadError.value = true;
    }
}

onMounted(load);
watch(() => props.src, load);
watch(() => [props.colors, props.strokes], paint, { deep: true });
watch(() => props.tables, labelTables, { deep: true });
watch(
    () => props.extraSeats,
    () => htmlContent.value && collectSeats(),
    { deep: true },
);
</script>

<template>
    <div v-if="loadError" class="p-4 text-sm text-red-600">
        Mapu sedenia sa nepodarilo načítať.
    </div>
    <div
        v-else
        class="max-w-full overflow-auto rounded-lg border border-sidebar-border/70 bg-white"
    >
        <div
            ref="containerRef"
            class="seat-map"
            v-html="htmlContent"
            @click="onClick"
            @mouseover="onMouseOver"
            @mouseleave="emit('seat-hover', null)"
        ></div>
    </div>
</template>

<style scoped>
.seat-map :deep(svg) {
    max-width: 100%;
    height: auto;
}

.seat-map :deep(text) {
    pointer-events: none;
    user-select: none;
}
</style>
