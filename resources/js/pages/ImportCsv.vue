<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    UploadIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    AlertCircleIcon,
    ChevronLeftIcon,
    FileTextIcon,
    ArrowRightIcon
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Event {
    id: number;
    title: string;
    url_slug: string;
}

interface PreviewData {
    to_confirm: Array<{
        variable_symbol: string;
        name: string;
        email: string;
        amount?: number;
        expected_amount?: number;
    }>;
    amount_mismatch: Array<{
        variable_symbol: string;
        name: string;
        email: string;
        paid_amount: number;
        expected_amount: number;
    }>;
    to_cancel: Array<{
        variable_symbol: string;
        name: string;
        email: string;
        days_old: number;
    }>;
    to_remain: Array<{
        variable_symbol: string;
        name: string;
        email: string;
    }>;
    not_found: string[];
}

interface ImportResult {
    confirmed_count: number;
    cancelled_count: number;
    already_confirmed_count: number;
    not_found_count: number;
}

const props = defineProps<{
    event: Event;
    preview?: PreviewData;
    result?: ImportResult;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: props.event.title,
        href: `/event/${props.event.url_slug}/manage`,
    },
    {
        title: 'Importovať CSV',
        href: `/event/${props.event.url_slug}/import-csv`,
    },
];

const csvForm = useForm({
    csv_file: null as File | null,
});

const csvFileInput = ref<HTMLInputElement | null>(null);

// Use computed to make step reactive to props changes
const step = computed<'upload' | 'preview' | 'result'>(() => {
    if (props.result) return 'result';
    if (props.preview) return 'preview';
    return 'upload';
});

const handleCsvFileChange = (event: InputEvent) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        csvForm.csv_file = target.files[0];
    }
};

const submitPreview = () => {
    if (!csvForm.csv_file) {
        return;
    }

    csvForm.post(`/event/${props.event.url_slug}/import-csv/preview`, {
        preserveScroll: true,
    });
};

const confirmImport = () => {
    router.post(`/event/${props.event.url_slug}/import-csv/confirm`, {}, {
        preserveScroll: true,
    });
};

const resetUpload = () => {
    router.get(`/event/${props.event.url_slug}/import-csv`);
};
</script>

<template>
    <Head title="Importovať CSV" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 max-w-5xl mx-auto">
            <!-- Header -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Importovať platby z CSV</h1>
                        <p class="text-muted-foreground">{{ event.title }}</p>
                    </div>
                    <Link
                        :href="`/event/${event.url_slug}/manage`"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-foreground transition-all hover:bg-muted"
                    >
                        <ChevronLeftIcon class="w-4 h-4" />
                        Späť
                    </Link>
                </div>
            </div>

            <!-- Step 1: Upload -->
            <div v-if="step === 'upload'" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-8 bg-card">
                <div class="max-w-2xl mx-auto">
                    <div class="flex items-center justify-center mb-6">
                        <FileTextIcon class="w-16 h-16 text-blue-600 opacity-50" />
                    </div>

                    <h2 class="text-2xl font-bold text-center mb-2">Nahrať CSV súbor</h2>
                    <p class="text-center text-muted-foreground mb-8">
                        Nahrajte CSV súbor s variabilnými symbolmi platieb. Súbor musí obsahovať stĺpec "VS".
                    </p>

                    <div class="space-y-4">
                        <div>
                            <Label for="csv_file">CSV súbor</Label>
                            <Input
                                id="csv_file"
                                type="file"
                                accept=".csv,.txt"
                                @change="handleCsvFileChange"
                                :class="{ 'border-red-500': csvForm.errors.csv_file }"
                                ref="csvFileInput"
                                class="cursor-pointer"
                            />
                            <p v-if="csvForm.errors.csv_file" class="mt-1 text-sm text-red-500">
                                {{ csvForm.errors.csv_file }}
                            </p>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <p class="text-sm text-blue-900 dark:text-blue-100 font-medium mb-2">Príklad formátu CSV:</p>
                            <pre class="text-xs text-blue-800 dark:text-blue-200 font-mono">Date,VS,Amount,Payment note
12/06/25,1234567890,85,
12/06/25,9876543210,20,</pre>
                        </div>

                        <Button
                            @click="submitPreview"
                            :disabled="!csvForm.csv_file || csvForm.processing"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white"
                        >
                            <UploadIcon class="w-4 h-4 mr-2" />
                            {{ csvForm.processing ? 'Spracovávam...' : 'Analyzovať súbor' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Preview -->
            <div v-if="step === 'preview' && preview" class="space-y-4">
                <!-- Summary Cards -->
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 p-4">
                        <div class="flex items-center gap-3">
                            <CheckCircleIcon class="w-8 h-8 text-green-600" />
                            <div>
                                <p class="text-sm text-green-800 dark:text-green-200">Na potvrdenie</p>
                                <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ preview.to_confirm.length }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4">
                        <div class="flex items-center gap-3">
                            <XCircleIcon class="w-8 h-8 text-red-600" />
                            <div>
                                <p class="text-sm text-red-800 dark:text-red-200">Na zrušenie</p>
                                <p class="text-2xl font-bold text-red-900 dark:text-red-100">{{ preview.to_cancel.length }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950 p-4">
                        <div class="flex items-center gap-3">
                            <ClockIcon class="w-8 h-8 text-yellow-600" />
                            <div>
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">Zostanú čakajúce</p>
                                <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ preview.to_remain.length }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-4">
                        <div class="flex items-center gap-3">
                            <AlertCircleIcon class="w-8 h-8 text-gray-600" />
                            <div>
                                <p class="text-sm text-gray-800 dark:text-gray-200">Nenájdené</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ preview.not_found.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- To Confirm -->
                <div v-if="preview.to_confirm.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <CheckCircleIcon class="w-5 h-5 text-green-600" />
                        Objednávky na potvrdenie ({{ preview.to_confirm.length }})
                    </h3>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="order in preview.to_confirm"
                            :key="order.variable_symbol"
                            class="flex items-center justify-between p-3 rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800"
                        >
                            <div>
                                <p class="font-medium text-green-900 dark:text-green-100">{{ order.name }}</p>
                                <p class="text-sm text-green-700 dark:text-green-300">{{ order.email }}</p>
                            </div>
                            <span class="text-sm font-mono text-green-800 dark:text-green-200">VS: {{ order.variable_symbol }}</span>
                        </div>
                    </div>
                </div>

                <!-- To Cancel -->
                <div v-if="preview.to_cancel.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <XCircleIcon class="w-5 h-5 text-red-600" />
                        Objednávky na zrušenie ({{ preview.to_cancel.length }})
                    </h3>
                    <p class="text-sm text-muted-foreground mb-4">Objednávky staršie ako 5 dní, ktoré nie sú v CSV súbore</p>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="order in preview.to_cancel"
                            :key="order.variable_symbol"
                            class="flex items-center justify-between p-3 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800"
                        >
                            <div>
                                <p class="font-medium text-red-900 dark:text-red-100">{{ order.name }}</p>
                                <p class="text-sm text-red-700 dark:text-red-300">{{ order.email }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-mono text-red-800 dark:text-red-200 block">VS: {{ order.variable_symbol }}</span>
                                <span class="text-xs text-red-600 dark:text-red-400">{{ order.days_old }} dní</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- To Remain -->
                <div v-if="preview.to_remain.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <ClockIcon class="w-5 h-5 text-yellow-600" />
                        Objednávky zostanú čakajúce ({{ preview.to_remain.length }})
                    </h3>
                    <p class="text-sm text-muted-foreground mb-4">Objednávky mladšie ako 5 dní, ktoré nie sú v CSV súbore</p>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="order in preview.to_remain"
                            :key="order.variable_symbol"
                            class="flex items-center justify-between p-3 rounded-lg bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-800"
                        >
                            <div>
                                <p class="font-medium text-yellow-900 dark:text-yellow-100">{{ order.name }}</p>
                                <p class="text-sm text-yellow-700 dark:text-yellow-300">{{ order.email }}</p>
                            </div>
                            <span class="text-sm font-mono text-yellow-800 dark:text-yellow-200">VS: {{ order.variable_symbol }}</span>
                        </div>
                    </div>
                </div>

                <!-- Not Found -->
                <div v-if="preview.not_found.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <AlertCircleIcon class="w-5 h-5 text-gray-600" />
                        Variabilné symboly nenájdené v čakajúcich objednávkach ({{ preview.not_found.length }})
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="vs in preview.not_found"
                            :key="vs"
                            class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm font-mono"
                        >
                            {{ vs }}
                        </span>
                    </div>
                </div>

                <!-- Amount Mismatch -->
                <div v-if="preview.amount_mismatch.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <AlertCircleIcon class="w-5 h-5 text-gray-600" />
                        Nesúhlasí suma ({{ preview.amount_mismatch.length }})
                    </h3>
                    <p class="text-sm text-muted-foreground mb-4">Objednávky, ktorým nesúhlasí suma, je nutné vyriešiť ručne. Tento import ich nijak neovplyvní.</p>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <div
                            v-for="mismatch in preview.amount_mismatch"
                            :key="mismatch.variable_symbol"
                            class="flex items-center justify-between p-3 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800"
                        >
                            <div>
                                <p class="font-medium text-red-900 dark:text-red-100">{{ mismatch.name }}</p>
                                <p class="text-sm text-red-700 dark:text-red-300">{{ mismatch.email }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-mono text-red-800 dark:text-red-200 block">VS: {{ mismatch.variable_symbol }}</span>
                                <span class="text-xs text-red-600 dark:text-red-400">Očakávaná suma: {{ mismatch.expected_amount }} €, Zaplatená suma: {{ mismatch.paid_amount }} €</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-4">
                    <Button
                        @click="resetUpload"
                        variant="outline"
                        class="flex-1"
                    >
                        <ChevronLeftIcon class="w-4 h-4 mr-2" />
                        Zrušiť a nahrať iný súbor
                    </Button>
                    <Button
                        @click="confirmImport"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white"
                    >
                        Potvrdiť a vykonať import
                        <ArrowRightIcon class="w-4 h-4 ml-2" />
                    </Button>
                </div>
            </div>

            <!-- Step 3: Result -->
            <div v-if="step === 'result' && result" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-8 bg-card">
                <div class="max-w-2xl mx-auto text-center">
                    <div class="flex items-center justify-center mb-6">
                        <CheckCircleIcon class="w-20 h-20 text-green-600" />
                    </div>

                    <h2 class="text-3xl font-bold mb-2">Import dokončený!</h2>
                    <p class="text-muted-foreground mb-8">
                        CSV súbor bol úspešne spracovaný a objednávky boli aktualizované.
                    </p>

                    <div class="grid gap-4 md:grid-cols-2 mb-8">
                        <div class="p-6 rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800">
                            <p class="text-3xl font-bold text-green-900 dark:text-green-100 mb-2">{{ result.confirmed_count }}</p>
                            <p class="text-sm text-green-700 dark:text-green-300">Potvrdených objednávok</p>
                        </div>

                        <div class="p-6 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800">
                            <p class="text-3xl font-bold text-red-900 dark:text-red-100 mb-2">{{ result.cancelled_count }}</p>
                            <p class="text-sm text-red-700 dark:text-red-300">Zrušených objednávok</p>
                        </div>

                        <div class="p-6 rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800">
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mb-2">{{ result.already_confirmed_count }}</p>
                            <p class="text-sm text-blue-700 dark:text-blue-300">Už potvrdených</p>
                        </div>

                        <div class="p-6 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ result.not_found_count }}</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">Nenájdených</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <Button
                            @click="resetUpload"
                            variant="outline"
                            class="flex-1"
                        >
                            Importovať ďalší súbor
                        </Button>
                        <Link
                            :href="`/event/${event.url_slug}/manage`"
                            class="flex-1"
                        >
                            <Button class="w-full bg-blue-600 hover:bg-blue-700 text-white">
                                Späť na správu podujatia
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
