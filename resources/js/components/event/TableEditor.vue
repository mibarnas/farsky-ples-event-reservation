<script setup lang="ts">
import SeatMap from '@/components/SeatMap.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatSeatRanges,
    parseSeatRanges,
    validateTables,
    type EventTable,
} from '@/lib/seatRanges';
import {
    CheckIcon,
    ChevronDownIcon,
    MousePointerClickIcon,
    PlusIcon,
    Trash2Icon,
    WandSparklesIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Edits the event's table setup ({"tables":[{"name","seats"}]}) that TablesPlugin reads.
const props = defineProps<{
    svgMap?: string | null;
    validSeats?: number[] | null;
    // Server-side validation errors, keyed like "tables.0.seats".
    errors?: Record<string, string>;
}>();

const enabled = defineModel<boolean>('enabled', { required: true });
const tables = defineModel<EventTable[]>('tables', { required: true });

const PALETTE = [
    '#2563eb',
    '#db2777',
    '#16a34a',
    '#ea580c',
    '#7c3aed',
    '#0891b2',
    '#ca8a04',
    '#dc2626',
    '#4f46e5',
    '#059669',
    '#c026d3',
    '#65a30d',
];
const UNASSIGNED = '#e4e4e7';
const colorFor = (index: number) => PALETTE[index % PALETTE.length];

const activeIndex = ref<number | null>(null);
const hoveredSeat = ref<number | null>(null);

function setTables(next: EventTable[]) {
    tables.value = next;
}

function updateTable(index: number, patch: Partial<EventTable>) {
    setTables(
        tables.value.map((table, i) =>
            i === index ? { ...table, ...patch } : table,
        ),
    );
}

function addTable() {
    const next = [
        ...tables.value,
        { name: `Stôl ${tables.value.length + 1}`, seats: [] },
    ];
    setTables(next);
    activeIndex.value = next.length - 1;
}

function removeTable(index: number) {
    setTables(tables.value.filter((_, i) => i !== index));
    if (activeIndex.value === index) activeIndex.value = null;
    else if (activeIndex.value !== null && activeIndex.value > index)
        activeIndex.value--;
}

// ---- Seat text inputs: free text while typing, normalized on blur ----
const editing = ref<{ index: number; text: string } | null>(null);
const seatInputInvalid = ref<Record<number, string[]>>({});

function seatInputValue(index: number) {
    return editing.value?.index === index
        ? editing.value.text
        : formatSeatRanges(tables.value[index].seats);
}

function onSeatInput(index: number, text: string | number) {
    editing.value = { index, text: String(text) };
}

function commitSeatInput(index: number) {
    if (editing.value?.index !== index) return;
    const { seats, invalid } = parseSeatRanges(editing.value.text);
    seatInputInvalid.value = { ...seatInputInvalid.value, [index]: invalid };
    editing.value = null;
    updateTable(index, { seats });
}

// ---- Seat map ----
const seatOwner = computed(() => {
    const owners = new Map<number, number>();
    tables.value.forEach((table, index) =>
        table.seats.forEach((seat) => {
            if (!owners.has(seat)) owners.set(seat, index);
        }),
    );
    return owners;
});

const seatColors = computed(() => {
    const colors: Record<number, string> = {};
    seatOwner.value.forEach((index, seat) => {
        colors[seat] = colorFor(index);
    });
    return colors;
});

function onSeatClick(seat: number) {
    if (activeIndex.value === null) {
        // Without an active table, clicking an assigned seat selects its table.
        const owner = seatOwner.value.get(seat);
        if (owner !== undefined) activeIndex.value = owner;
        return;
    }

    const active = activeIndex.value;
    const alreadyInActive = tables.value[active].seats.includes(seat);
    setTables(
        tables.value.map((table, i) => {
            if (i === active) {
                const seats = alreadyInActive
                    ? table.seats.filter((s) => s !== seat)
                    : [...table.seats, seat].sort((a, b) => a - b);
                return { ...table, seats };
            }
            // A seat can belong to one table only: move it to the active one.
            return table.seats.includes(seat)
                ? { ...table, seats: table.seats.filter((s) => s !== seat) }
                : table;
        }),
    );
}

const hoverLabel = computed(() => {
    if (hoveredSeat.value === null) return null;
    const owner = seatOwner.value.get(hoveredSeat.value);
    return owner === undefined
        ? `Miesto ${hoveredSeat.value} · nepriradené`
        : `Miesto ${hoveredSeat.value} · ${tables.value[owner].name || 'bez názvu'}`;
});

// ---- Checks ----
const problems = computed(() => validateTables(tables.value, props.validSeats));

const unassignedCount = computed(() => {
    if (!props.validSeats) return null;
    return props.validSeats.filter((seat) => !seatOwner.value.has(seat)).length;
});

const assignedCount = computed(() => seatOwner.value.size);

function serverErrorsFor(index: number): string[] {
    if (!props.errors) return [];
    return Object.entries(props.errors)
        .filter(([key]) => key.startsWith(`tables.${index}.`))
        .map(([, message]) => message);
}

// ---- Generator ----
const generator = ref({
    count: 10,
    seatsPerTable: 12,
    firstSeat: props.validSeats?.length ? Math.min(...props.validSeats) : 1,
    naming: 'prefix' as 'prefix' | 'list',
    prefix: 'Stôl',
    names: '',
});
const confirmReplace = ref(false);

const generatorNames = computed(() =>
    generator.value.naming === 'list'
        ? generator.value.names
              .split('\n')
              .map((name) => name.trim())
              .filter(Boolean)
        : [],
);

const generatorCount = computed(() =>
    generator.value.naming === 'list'
        ? generatorNames.value.length
        : Math.max(0, Math.floor(Number(generator.value.count) || 0)),
);

const generated = computed<EventTable[]>(() => {
    const perTable = Math.max(
        1,
        Math.floor(Number(generator.value.seatsPerTable) || 0),
    );
    const first = Math.floor(Number(generator.value.firstSeat) || 0);
    const needed = generatorCount.value * perTable;

    // Follow the real seat numbers of the map when we know them (maps can have gaps).
    const pool = props.validSeats?.length
        ? [...props.validSeats]
              .sort((a, b) => a - b)
              .filter((seat) => seat >= first)
              .slice(0, needed)
        : Array.from({ length: needed }, (_, i) => first + i);

    return Array.from({ length: generatorCount.value }, (_, i) => ({
        name:
            generator.value.naming === 'list'
                ? generatorNames.value[i]
                : `${generator.value.prefix.trim() || 'Stôl'} ${i + 1}`,
        seats: pool.slice(i * perTable, (i + 1) * perTable),
    })).filter((table) => table.seats.length > 0);
});

const generatorShortfall = computed(
    () => generatorCount.value - generated.value.length,
);

function applyGenerator() {
    if (tables.value.length && !confirmReplace.value) {
        confirmReplace.value = true;
        return;
    }
    confirmReplace.value = false;
    setTables(generated.value.map((table) => ({ ...table })));
    activeIndex.value = null;
    editing.value = null;
    seatInputInvalid.value = {};
}

// Enter in a text field would submit the surrounding form (next wizard step / save).
function swallowEnter(e: KeyboardEvent) {
    if ((e.target as HTMLElement).tagName === 'INPUT') e.preventDefault();
}

// ---- JSON import / export (same format as deploy_assets/tables.json) ----
const jsonText = ref('');
const jsonError = ref('');

function exportJson() {
    jsonText.value = JSON.stringify({ tables: tables.value }, null, 2);
    jsonError.value = '';
}

function importJson() {
    try {
        const parsed = JSON.parse(jsonText.value);
        const list = Array.isArray(parsed) ? parsed : parsed?.tables;
        if (!Array.isArray(list)) throw new Error();
        const imported = list.map((table: unknown) => {
            const t = table as { name?: unknown; seats?: unknown };
            if (typeof t.name !== 'string' || !Array.isArray(t.seats))
                throw new Error();
            return {
                name: t.name,
                seats: [
                    ...new Set(
                        t.seats
                            .map((s) => parseInt(String(s), 10))
                            .filter(Number.isFinite),
                    ),
                ].sort((a, b) => a - b),
            };
        });
        setTables(imported);
        activeIndex.value = null;
        jsonError.value = '';
    } catch {
        jsonError.value =
            'Neplatný formát. Očakáva sa {"tables": [{"name": "...", "seats": [1, 2, 3]}]}.';
    }
}
</script>

<template>
    <div class="space-y-5" @keydown.enter="swallowEnter">
        <label
            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors"
            :class="
                enabled
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                    : 'hover:bg-muted/50'
            "
        >
            <input
                v-model="enabled"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-300"
            />
            <span>
                <span class="block font-medium">Podujatie má stoly</span>
                <span class="block text-sm text-muted-foreground">
                    Hostia uvidia názov svojho stola v potvrdení objednávky, na
                    vstupenke aj v tlačovom zozname.
                </span>
            </span>
        </label>

        <template v-if="enabled">
            <!-- Generator -->
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <WandSparklesIcon class="size-4 text-blue-600" />
                    <h3 class="font-semibold">Rýchle vytvorenie stolov</h3>
                </div>
                <p class="text-sm text-muted-foreground">
                    Vytvorí stoly s rovnakým počtom miest, ktoré idú po sebe. Po
                    vytvorení ich môžete premenovať a upraviť na mape.
                </p>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="generator.naming"
                            type="radio"
                            value="prefix"
                        />
                        Očíslované stoly
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="generator.naming"
                            type="radio"
                            value="list"
                        />
                        Vlastné názvy
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div v-if="generator.naming === 'prefix'" class="space-y-2">
                        <Label for="gen-count">Počet stolov</Label>
                        <Input
                            id="gen-count"
                            v-model.number="generator.count"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div v-if="generator.naming === 'prefix'" class="space-y-2">
                        <Label for="gen-prefix">Názov</Label>
                        <Input
                            id="gen-prefix"
                            v-model="generator.prefix"
                            placeholder="Stôl"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="gen-seats">Miest pri stole</Label>
                        <Input
                            id="gen-seats"
                            v-model.number="generator.seatsPerTable"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="gen-first">Prvé miesto</Label>
                        <Input
                            id="gen-first"
                            v-model.number="generator.firstSeat"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>

                <div v-if="generator.naming === 'list'" class="space-y-2">
                    <Label for="gen-names"
                        >Názvy stolov (jeden na riadok)</Label
                    >
                    <textarea
                        id="gen-names"
                        v-model="generator.names"
                        rows="5"
                        placeholder="Murano&#10;Burano&#10;Torcello"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    ></textarea>
                </div>

                <p
                    v-if="generated.length"
                    class="text-sm text-muted-foreground"
                >
                    Náhľad: {{ generated[0].name }} ({{
                        formatSeatRanges(generated[0].seats)
                    }})<template v-if="generated.length > 1">
                        …
                        {{ generated[generated.length - 1].name }} ({{
                            formatSeatRanges(
                                generated[generated.length - 1].seats,
                            )
                        }})</template
                    >
                </p>
                <p v-if="generatorShortfall > 0" class="text-sm text-amber-600">
                    Na mape nie je dosť miest pre všetky stoly. Vytvorí sa len
                    {{ generated.length }} z {{ generatorCount }}.
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        :variant="confirmReplace ? 'destructive' : 'secondary'"
                        :disabled="!generated.length"
                        @click="applyGenerator"
                    >
                        {{
                            confirmReplace
                                ? `Áno, nahradiť ${tables.length} existujúcich stolov`
                                : `Vytvoriť ${generated.length} stolov`
                        }}
                    </Button>
                    <Button
                        v-if="confirmReplace"
                        type="button"
                        variant="ghost"
                        @click="confirmReplace = false"
                    >
                        Zrušiť
                    </Button>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Table list -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">
                            Stoly
                            <span class="font-normal text-muted-foreground"
                                >({{ tables.length }})</span
                            >
                        </h3>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addTable"
                        >
                            <PlusIcon class="size-4" /> Pridať stôl
                        </Button>
                    </div>

                    <p
                        v-if="!tables.length"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        Zatiaľ žiadne stoly. Použite rýchle vytvorenie vyššie
                        alebo pridajte stôl ručne.
                    </p>

                    <div
                        v-for="(table, index) in tables"
                        :key="index"
                        class="space-y-2 rounded-lg border p-3 transition-shadow"
                        :class="{
                            'ring-2 ring-blue-500': activeIndex === index,
                            'border-red-400':
                                problems[index] ||
                                serverErrorsFor(index).length,
                        }"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="size-4 shrink-0 rounded-full"
                                :style="{ backgroundColor: colorFor(index) }"
                            ></span>
                            <Input
                                :model-value="table.name"
                                placeholder="Názov stola"
                                class="h-8"
                                :aria-label="`Názov stola ${index + 1}`"
                                @update:model-value="
                                    updateTable(index, { name: String($event) })
                                "
                            />
                            <Badge variant="secondary" class="shrink-0">
                                {{ table.seats.length }} miest
                            </Badge>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8 shrink-0 text-red-600"
                                :aria-label="`Odstrániť stôl ${table.name}`"
                                @click="removeTable(index)"
                            >
                                <Trash2Icon class="size-4" />
                            </Button>
                        </div>
                        <div class="flex items-center gap-2">
                            <Input
                                :model-value="seatInputValue(index)"
                                placeholder="napr. 1-12, 15"
                                class="h-8 font-mono text-xs"
                                :aria-label="`Miesta stola ${table.name}`"
                                @update:model-value="onSeatInput(index, $event)"
                                @blur="commitSeatInput(index)"
                                @keydown.enter.prevent="commitSeatInput(index)"
                            />
                            <Button
                                v-if="svgMap"
                                type="button"
                                size="sm"
                                :variant="
                                    activeIndex === index
                                        ? 'default'
                                        : 'outline'
                                "
                                class="h-8 shrink-0"
                                :title="
                                    activeIndex === index
                                        ? 'Ukončiť výber miest na mape'
                                        : 'Vybrať miesta tohto stola klikaním na mape'
                                "
                                @click="
                                    activeIndex =
                                        activeIndex === index ? null : index
                                "
                            >
                                <template v-if="activeIndex === index">
                                    <CheckIcon class="size-4" /> Hotovo
                                </template>
                                <template v-else>
                                    <MousePointerClickIcon class="size-4" />
                                    Vybrať miesta
                                </template>
                            </Button>
                        </div>
                        <p
                            v-if="seatInputInvalid[index]?.length"
                            class="text-xs text-amber-600"
                        >
                            Ignorované: {{ seatInputInvalid[index].join(', ') }}
                        </p>
                        <p
                            v-for="message in [
                                ...(problems[index] ?? []),
                                ...serverErrorsFor(index),
                            ]"
                            :key="message"
                            class="text-xs text-red-600"
                        >
                            {{ message }}
                        </p>
                    </div>
                </div>

                <!-- Seat map -->
                <div
                    v-if="svgMap"
                    class="space-y-2 lg:sticky lg:top-4 lg:self-start"
                >
                    <h3 class="font-semibold">Mapa sedenia</h3>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="activeIndex !== null">
                            Kliknutím na miesto ho pridáte k stolu
                            <strong>{{
                                tables[activeIndex]?.name || 'bez názvu'
                            }}</strong>
                            alebo ho odoberiete.
                        </template>
                        <template v-else>
                            Pri stole kliknite na „Vybrať miesta“ a potom
                            klikajte na miesta na mape. Kliknutím na obsadené
                            miesto vyberiete jeho stôl.
                        </template>
                    </p>
                    <SeatMap
                        :src="svgMap"
                        :colors="seatColors"
                        :default-color="UNASSIGNED"
                        :tables="tables"
                        @seat-click="onSeatClick"
                        @seat-hover="hoveredSeat = $event"
                    />
                    <div
                        class="flex min-h-5 flex-wrap justify-between gap-2 text-sm text-muted-foreground"
                    >
                        <span>{{ hoverLabel ?? '' }}</span>
                        <span>
                            Priradených {{ assignedCount }}
                            <template v-if="unassignedCount !== null"
                                >· nepriradených {{ unassignedCount }}</template
                            >
                        </span>
                    </div>
                </div>
            </div>

            <!-- Advanced: JSON -->
            <Collapsible>
                <CollapsibleTrigger
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ChevronDownIcon class="size-4" /> Pokročilé: import /
                    export JSON
                </CollapsibleTrigger>
                <CollapsibleContent class="mt-3 space-y-2">
                    <textarea
                        v-model="jsonText"
                        rows="8"
                        placeholder='{"tables": [{"name": "Murano", "seats": [1, 2, 3]}]}'
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    ></textarea>
                    <p v-if="jsonError" class="text-sm text-red-600">
                        {{ jsonError }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="exportJson"
                        >
                            Exportovať aktuálne stoly
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!jsonText.trim()"
                            @click="importJson"
                        >
                            Importovať
                        </Button>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </template>
    </div>
</template>
