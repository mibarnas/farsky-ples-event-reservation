<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AdminEvent {
    id: number;
    title: string;
    url_slug: string;
    start_time: string;
    seats_total: number;
    reserved_seats: number;
    orders_count: number;
    collaborators_count: number;
    location: string | null;
    owner: { name: string; email: string } | null;
}

interface AdminUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    is_admin: boolean;
    is_root_admin: boolean;
    events_count: number;
    shared_events_count: number;
}

const props = defineProps<{
    stats: { users: number; events: number; orders: number; reservations: number };
    events: AdminEvent[];
    users: AdminUser[];
    aliasEventId: number | null;
    aliasDomains: string[];
}>();

const aliasForm = useForm<{ event_id: number | null }>({ event_id: props.aliasEventId });

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

const toggleAdmin = (user: AdminUser) => {
    const makeAdmin = !user.is_admin;
    const question = makeAdmin
        ? `Udeliť používateľovi ${user.name} (${user.email}) prístup do administrácie?`
        : `Odobrať používateľovi ${user.name} prístup do administrácie?`;

    if (!confirm(question)) return;

    router.put(`/admin/users/${user.id}/admin`, { is_admin: makeAdmin }, { preserveScroll: true });
};

const saveAliasEvent = () => {
    aliasForm.put('/admin/alias-event', {
        preserveScroll: true,
        onSuccess: () => aliasForm.defaults(),
    });
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Administrácia', href: '/admin' }];

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('sk-SK', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
</script>

<template>
    <Head title="Administrácia" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div>
                <h2 class="text-2xl font-bold">Administrácia</h2>
                <p class="text-sm text-muted-foreground">
                    Prehľad v režime len na čítanie. Na úpravu podujatia musíte byť pridaný ako spolupracovník.
                </p>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300"
            >
                {{ flash.error }}
            </div>

            <div class="grid gap-4 grid-cols-2 md:grid-cols-4">
                <div
                    v-for="(value, label) in {
                        'Používatelia': stats.users,
                        'Podujatia': stats.events,
                        'Objednávky': stats.orders,
                        'Rezervované miesta': stats.reservations,
                    }"
                    :key="label"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">{{ label }}</p>
                    <p class="text-2xl font-semibold tabular-nums">{{ value }}</p>
                </div>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h3 class="font-semibold">Presmerovanie domény</h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    Návštevníci
                    <span class="font-medium text-foreground">{{ aliasDomains.join(', ') || '—' }}</span>
                    budú presmerovaní na vybrané podujatie. Bez výberu sa presmerujú na úvodnú stránku.
                </p>
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="saveAliasEvent">
                    <label for="aliasEvent" class="sr-only">Podujatie</label>
                    <select
                        id="aliasEvent"
                        v-model="aliasForm.event_id"
                        class="h-9 min-w-0 flex-1 rounded-md border bg-transparent px-2 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none sm:max-w-md"
                    >
                        <option :value="null">— žiadne (úvodná stránka) —</option>
                        <option v-for="event in events" :key="event.id" :value="event.id">
                            {{ event.title }} ({{ formatDate(event.start_time) }})
                        </option>
                    </select>
                    <button
                        type="submit"
                        :disabled="aliasForm.processing || !aliasForm.isDirty"
                        class="h-9 rounded-lg bg-blue-600 px-4 text-sm text-white transition-all hover:bg-blue-700 disabled:opacity-50"
                    >
                        Uložiť
                    </button>
                    <span v-if="aliasForm.recentlySuccessful" class="text-sm text-green-600">Uložené</span>
                </form>
                <p v-if="aliasForm.errors.event_id" class="mt-2 text-sm text-red-600">{{ aliasForm.errors.event_id }}</p>
            </section>

            <section class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <h3 class="border-b border-sidebar-border/70 px-4 py-3 font-semibold dark:border-sidebar-border">
                    Podujatia
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Názov</th>
                                <th class="px-4 py-2 font-medium">Začiatok</th>
                                <th class="px-4 py-2 font-medium">Miesto</th>
                                <th class="px-4 py-2 font-medium">Vlastník</th>
                                <th class="px-4 py-2 font-medium text-right">Obsadenosť</th>
                                <th class="px-4 py-2 font-medium text-right">Objednávky</th>
                                <th class="px-4 py-2 font-medium text-right">Spolupracovníci</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="events.length === 0">
                                <td colspan="7" class="px-4 py-6 text-center text-muted-foreground">Žiadne podujatia</td>
                            </tr>
                            <tr
                                v-for="event in events"
                                :key="event.id"
                                class="border-t border-sidebar-border/50"
                            >
                                <td class="px-4 py-2">
                                    <Link :href="`/event/${event.url_slug}/manage`" class="font-medium hover:underline">
                                        {{ event.title }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(event.start_time) }}</td>
                                <td class="px-4 py-2">{{ event.location ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <template v-if="event.owner">
                                        {{ event.owner.name }}
                                        <span class="block text-xs text-muted-foreground">{{ event.owner.email }}</span>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ event.reserved_seats }} / {{ event.seats_total }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ event.orders_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ event.collaborators_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <h3 class="border-b border-sidebar-border/70 px-4 py-3 font-semibold dark:border-sidebar-border">
                    Používatelia
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Meno</th>
                                <th class="px-4 py-2 font-medium">E-mail</th>
                                <th class="px-4 py-2 font-medium">Overený</th>
                                <th class="px-4 py-2 font-medium">Registrovaný</th>
                                <th class="px-4 py-2 font-medium text-right">Vlastné podujatia</th>
                                <th class="px-4 py-2 font-medium text-right">Zdieľané podujatia</th>
                                <th class="px-4 py-2 font-medium">Administrátor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="border-t border-sidebar-border/50">
                                <td class="px-4 py-2">{{ user.name }}</td>
                                <td class="px-4 py-2">{{ user.email }}</td>
                                <td class="px-4 py-2">{{ user.email_verified_at ? 'áno' : 'nie' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(user.created_at) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ user.events_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ user.shared_events_count }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span v-if="user.is_root_admin" class="text-muted-foreground">hlavný admin</span>
                                    <span v-else-if="user.id === currentUserId" class="text-muted-foreground">áno (vy)</span>
                                    <button
                                        v-else
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs transition-colors hover:bg-muted"
                                        :class="user.is_admin ? 'border-red-300 text-red-700 dark:border-red-800 dark:text-red-400' : 'border-sidebar-border'"
                                        @click="toggleAdmin(user)"
                                    >
                                        {{ user.is_admin ? 'Odobrať admina' : 'Urobiť adminom' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
