<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed } from 'vue';

interface Location {
    id: number;
    address: string;
    places_total: number;
}

interface Event {
    id: number;
    title: string;
    url_slug: string;
    description: string | null;
    start_time: string;
    registration_start: string;
    registration_end: string;
    seats_total: number;
    location_id: number;
    contact_name: string;
    contact_email: string;
    contact_phone: string | null;
    bank_account: string;
    multiple_reservations_per_ticket: boolean;
    background_image_path?: string | null;
    logo_image_path?: string | null;
    overline?: string | null;
}

const props = defineProps<{
    event: Event;
    locations: Location[];
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
        title: 'Upraviť',
        href: `/event/${props.event.url_slug}/edit`,
    },
];

// Format datetime for datetime-local input (YYYY-MM-DDTHH:MM)
const formatDateTimeLocal = (dateString: string) => {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
    title: props.event.title,
    url_slug: props.event.url_slug,
    description: props.event.description || '',
    start_time: formatDateTimeLocal(props.event.start_time),
    registration_start: formatDateTimeLocal(props.event.registration_start),
    registration_end: formatDateTimeLocal(props.event.registration_end),
    seats_total: props.event.seats_total.toString(),
    location_id: props.event.location_id.toString(),
    contact_name: props.event.contact_name,
    contact_email: props.event.contact_email,
    contact_phone: props.event.contact_phone || '',
    bank_account: props.event.bank_account,
    multiple_reservations_per_ticket: props.event.multiple_reservations_per_ticket,
    overline: props.event.overline || '',
    background_image: null as File | null,
    remove_background_image: false,
    logo: null as File | null,
    remove_logo: false,
    _method: 'PUT',
});

const generateSlug = () => {
    if (form.title) {
        form.url_slug = form.title
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
};

const handleFileChange = (e: any) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.background_image = target.files[0];
        form.remove_background_image = false;
    }
};

const handleLogoChange = (e: any) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
        form.remove_logo = false;
    }
};

const removeBackgroundImage = () => {
    form.background_image = null;
    form.remove_background_image = true;
    // Reset the actual file input element
    const fileInput = document.getElementById('background_image') as HTMLInputElement;
    if (fileInput) {
        fileInput.value = '';
    }
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    // Reset the actual file input element
    const fileInput = document.getElementById('logo') as HTMLInputElement;
    if (fileInput) {
        fileInput.value = '';
    }
};

const previewImageUrl = computed(() => {
    if (form.background_image) {
        return window.URL.createObjectURL(form.background_image);
    }
    return '';
});

const previewLogoUrl = computed(() => {
    if (form.logo) {
        return window.URL.createObjectURL(form.logo);
    }
    return '';
});

const submit = () => {
    // If files are selected, use forceFormData to handle multipart/form-data
    if (form.background_image || form.logo) {
        form.post(`/event/${props.event.url_slug}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                // Reset file inputs on success
                form.background_image = null;
                form.logo = null;
                const bgInput = document.getElementById('background_image') as HTMLInputElement;
                const logoInput = document.getElementById('logo') as HTMLInputElement;
                if (bgInput) bgInput.value = '';
                if (logoInput) logoInput.value = '';
            },
        });
    } else {
        // No files, just submit as regular form data
        const { background_image: _, logo: __, ...formDataWithoutFiles } = form.data();

        router.post(`/event/${props.event.url_slug}`, formDataWithoutFiles, {
            preserveScroll: true,
            onSuccess: () => {
                const bgInput = document.getElementById('background_image') as HTMLInputElement;
                const logoInput = document.getElementById('logo') as HTMLInputElement;
                if (bgInput) bgInput.value = '';
                if (logoInput) logoInput.value = '';
            },
        });
    }
};
</script>

<template>
    <Head :title="`Upraviť ${event.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                <h1 class="text-3xl font-bold mb-6">Upraviť podujatie</h1>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold">Základné informácie</h2>

                        <div class="space-y-2">
                            <Label for="overline">Nadpis (Overline)</Label>
                            <Input
                                id="overline"
                                v-model="form.overline"
                                placeholder="Sekundárny nadpis nad hlavným názvom"
                                :class="{ 'border-red-500': form.errors.overline }"
                            />
                            <p class="text-xs text-gray-500">Voliteľný text, ktorý sa zobrazí nad hlavným názvom podujatia</p>
                            <p v-if="form.errors.overline" class="text-sm text-red-500">
                                {{ form.errors.overline }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="title">Názov podujatia *</Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    @blur="generateSlug"
                                    required
                                    :class="{ 'border-red-500': form.errors.title }"
                                />
                                <p v-if="form.errors.title" class="text-sm text-red-500">
                                    {{ form.errors.title }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="url_slug">URL slug *</Label>
                                <Input
                                    id="url_slug"
                                    v-model="form.url_slug"
                                    required
                                    :class="{ 'border-red-500': form.errors.url_slug }"
                                />
                                <p v-if="form.errors.url_slug" class="text-sm text-red-500">
                                    {{ form.errors.url_slug }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Popis</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                show-markdown
                                :class="form.errors.description ? 'border-red-500' : ''"
                            />
                            <p v-if="form.errors.description" class="text-sm text-red-500">
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Event Details -->
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold">Detaily podujatia</h2>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="start_time">Začiatok podujatia *</Label>
                                <Input
                                    id="start_time"
                                    v-model="form.start_time"
                                    type="datetime-local"
                                    required
                                    :class="{ 'border-red-500': form.errors.start_time }"
                                />
                                <p v-if="form.errors.start_time" class="text-sm text-red-500">
                                    {{ form.errors.start_time }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="seats_total">Celkový počet miest</Label>
                                <Input
                                    id="seats_total"
                                    v-model="form.seats_total"
                                    type="number"
                                    min="1"
                                    disabled
                                    :class="{ 'border-red-500': form.errors.seats_total }"
                                    placeholder="Automaticky z lokality"
                                />
                                <p v-if="form.errors.seats_total" class="text-sm text-red-500">
                                    {{ form.errors.seats_total }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="registration_start">Začiatok registrácie *</Label>
                                <Input
                                    id="registration_start"
                                    v-model="form.registration_start"
                                    type="datetime-local"
                                    required
                                    :class="{ 'border-red-500': form.errors.registration_start }"
                                />
                                <p v-if="form.errors.registration_start" class="text-sm text-red-500">
                                    {{ form.errors.registration_start }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="registration_end">Koniec registrácie *</Label>
                                <Input
                                    id="registration_end"
                                    v-model="form.registration_end"
                                    type="datetime-local"
                                    required
                                    :class="{ 'border-red-500': form.errors.registration_end }"
                                />
                                <p v-if="form.errors.registration_end" class="text-sm text-red-500">
                                    {{ form.errors.registration_end }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="location_id">Lokalita *</Label>
                            <select
                                id="location_id"
                                v-model="form.location_id"
                                required
                                disabled
                                :class="[
                                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                                    { 'border-red-500': form.errors.location_id }
                                ]"
                            >
                                <option value="">Vyberte lokalitu</option>
                                <option
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="location.id"
                                >
                                    {{ location.address }} ({{ location.places_total }} miest)
                                </option>
                            </select>
                            <p v-if="form.errors.location_id" class="text-sm text-red-500">
                                {{ form.errors.location_id }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <input
                                id="multiple_reservations"
                                v-model="form.multiple_reservations_per_ticket"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300"
                            />
                            <Label for="multiple_reservations" class="cursor-pointer font-normal">
                                Povoliť viacero rezervácií na jeden lístok
                            </Label>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold">Kontaktné informácie</h2>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="contact_name">Kontaktné meno *</Label>
                                <Input
                                    id="contact_name"
                                    v-model="form.contact_name"
                                    required
                                    :class="{ 'border-red-500': form.errors.contact_name }"
                                />
                                <p v-if="form.errors.contact_name" class="text-sm text-red-500">
                                    {{ form.errors.contact_name }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="contact_email">Kontaktný email *</Label>
                                <Input
                                    id="contact_email"
                                    v-model="form.contact_email"
                                    type="email"
                                    required
                                    :class="{ 'border-red-500': form.errors.contact_email }"
                                />
                                <p v-if="form.errors.contact_email" class="text-sm text-red-500">
                                    {{ form.errors.contact_email }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="contact_phone">Kontaktný telefón</Label>
                                <Input
                                    id="contact_phone"
                                    v-model="form.contact_phone"
                                    type="tel"
                                    :class="{ 'border-red-500': form.errors.contact_phone }"
                                />
                                <p v-if="form.errors.contact_phone" class="text-sm text-red-500">
                                    {{ form.errors.contact_phone }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="bank_account">Bankový účet *</Label>
                                <Input
                                    id="bank_account"
                                    v-model="form.bank_account"
                                    required
                                    :class="{ 'border-red-500': form.errors.bank_account }"
                                />
                                <p v-if="form.errors.bank_account" class="text-sm text-red-500">
                                    {{ form.errors.bank_account }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Background Image -->
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold">Obrázok na pozadí</h2>

                        <!-- Current Background Image -->
                        <div v-if="event.background_image_path && !form.remove_background_image" class="space-y-2">
                            <Label>Aktuálny obrázok na pozadí</Label>
                            <div class="relative">
                                <img
                                    :src="`/storage/${event.background_image_path}`"
                                    alt="Pozadie podujatia"
                                    class="h-48 w-full rounded-lg object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    @click="removeBackgroundImage"
                                    class="absolute top-2 right-2"
                                >
                                    Odstrániť
                                </Button>
                            </div>
                        </div>

                        <div>
                            <Label>{{ event.background_image_path && !form.remove_background_image ? 'Nahradiť obrázok' : 'Nahrať obrázok' }}</Label>
                            <input
                                id="background_image"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/webp"
                                @change="handleFileChange"
                                class="hidden"
                            />
                            <Label
                                for="background_image"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:bg-gray-800 dark:border-gray-600 p-6 text-center text-gray-500 dark:text-gray-400 transition-all hover:bg-gray-100 dark:hover:bg-gray-700 mt-2"
                            >
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm font-medium">
                                    {{ form.background_image ? 'Nový obrázok vybraný' : 'Kliknite pre výber obrázka' }}
                                </span>
                                <span class="text-xs text-gray-400 mt-1">
                                    JPEG, PNG, JPG, WEBP (max. 5MB)
                                </span>
                            </Label>
                            <p v-if="form.errors.background_image" class="mt-2 text-sm text-red-500">
                                {{ form.errors.background_image }}
                            </p>
                        </div>

                        <!-- Preview of New Image -->
                        <div v-if="form.background_image" class="space-y-2">
                            <Label>Náhľad nového obrázka</Label>
                            <div class="relative">
                                <img
                                    :src="previewImageUrl"
                                    alt="Náhľad obrázka"
                                    class="h-48 w-full rounded-lg object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="form.background_image = null"
                                    class="absolute top-2 right-2"
                                >
                                    Zrušiť
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div class="space-y-4">
                        <h2 class="text-xl font-semibold">Logo</h2>

                        <!-- Current Logo -->
                        <div v-if="event.logo_image_path && !form.remove_logo" class="space-y-2">
                            <Label>Aktuálne logo</Label>
                            <div class="relative">
                                <img
                                    :src="`/storage/${event.logo_image_path}`"
                                    alt="Logo podujatia"
                                    class="h-24 w-full rounded-lg object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    @click="removeLogo"
                                    class="absolute top-2 right-2"
                                >
                                    Odstrániť
                                </Button>
                            </div>
                        </div>

                        <div>
                            <Label>{{ event.logo_image_path && !form.remove_logo ? 'Nahradiť logo' : 'Nahrať logo' }}</Label>
                            <input
                                id="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/webp"
                                @change="handleLogoChange"
                                class="hidden"
                            />
                            <Label
                                for="logo"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:bg-gray-800 dark:border-gray-600 p-6 text-center text-gray-500 dark:text-gray-400 transition-all hover:bg-gray-100 dark:hover:bg-gray-700 mt-2"
                            >
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm font-medium">
                                    {{ form.logo ? 'Nové logo vybrané' : 'Kliknite pre výber loga' }}
                                </span>
                                <span class="text-xs text-gray-400 mt-1">
                                    JPEG, PNG, JPG, WEBP (max. 5MB)
                                </span>
                            </Label>
                            <p v-if="form.errors.logo" class="mt-2 text-sm text-red-500">
                                {{ form.errors.logo }}
                            </p>
                        </div>

                        <!-- Preview of New Logo -->
                        <div v-if="form.logo" class="space-y-2">
                            <Label>Náhľad nového loga</Label>
                            <div class="relative">
                                <img
                                    :src="previewLogoUrl"
                                    alt="Náhľad loga"
                                    class="h-24 w-full rounded-lg object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="form.logo = null"
                                    class="absolute top-2 right-2"
                                >
                                    Zrušiť
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center gap-4 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white cursor-pointer"
                        >
                            {{ form.processing ? 'Ukladá sa...' : 'Uložiť zmeny' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="$inertia.visit(`/event/${event.url_slug}/manage`)"
                            :disabled="form.processing"
                        >
                            Zrušiť
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
