<script setup lang="ts">
import PageTabs, { type PageTab } from '@/components/PageTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircleIcon,
    ArrowLeftRightIcon,
    CalendarIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    CreditCardIcon,
    EditIcon,
    ExternalLinkIcon,
    LandmarkIcon,
    LayoutDashboardIcon,
    MailIcon,
    MapPinIcon,
    PhoneIcon,
    PlusIcon,
    PrinterIcon,
    ReceiptIcon,
    SearchIcon,
    ShieldIcon,
    TicketIcon,
    TrashIcon,
    UploadIcon,
    UserIcon,
    UsersIcon,
    XIcon,
} from 'lucide-vue-next';
import { marked } from 'marked';
import { computed, ref, watch } from 'vue';

interface Location {
    id: number;
    address: string;
    svg_map: string;
    places_total: number;
}

interface Ticket {
    id: number;
    title: string;
    price: number;
    reservations: number;
}

interface Reservation {
    id: number;
    guest_name: string;
    seat_number: number;
}

interface Order {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    variable_symbol: string;
    url_slug: string;
    created_at: string;
    tickets: Array<Ticket & { pivot: { amount: number } }>;
    reservations: Reservation[];
}

interface User {
    id: number;
    name: string;
    email: string;
    pivot?: {
        role: 'manager' | 'staff';
    };
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
    contact_name: string;
    contact_email: string;
    contact_phone: string | null;
    bank_account: string;
    user_role: 'owner' | 'manager' | 'staff';
    location: Location;
    tickets: Ticket[];
    orders: Order[];
    reserved_seats: number;
    users: User[];
}

const props = defineProps<{
    event: Event;
    allUsers: User[];
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
];

// Check if user can manage (owner or manager only)
const canManage = computed(() => {
    return (
        props.event.user_role === 'owner' || props.event.user_role === 'manager'
    );
});

const renderedDescription = computed(() => {
    if (!props.event.description) return '';
    return marked.parse(props.event.description);
});

const isDialogOpen = ref(false);
const editingTicket = ref<Ticket | null>(null);

const isOrderDetailsOpen = ref(false);
const selectedOrder = ref<Order | null>(null);

const isCollaboratorDialogOpen = ref(false);

const ticketForm = useForm({
    title: '',
    price: '',
    reservations: '1',
});

const collaboratorForm = useForm({
    user_id: '',
    role: 'manager',
});

const openCreateDialog = () => {
    editingTicket.value = null;
    ticketForm.reset();
    ticketForm.clearErrors();
    isDialogOpen.value = true;
};

const openEditDialog = (ticket: Ticket) => {
    editingTicket.value = ticket;
    ticketForm.title = ticket.title;
    ticketForm.price = ticket.price.toString();
    ticketForm.reservations = ticket.reservations.toString();
    ticketForm.clearErrors();
    isDialogOpen.value = true;
};

const submitTicket = () => {
    if (editingTicket.value) {
        // Update existing ticket
        ticketForm.put(
            `/event/${props.event.url_slug}/ticket/${editingTicket.value.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    isDialogOpen.value = false;
                    ticketForm.reset();
                },
            },
        );
    } else {
        // Create new ticket
        ticketForm.post(`/event/${props.event.url_slug}/ticket`, {
            preserveScroll: true,
            onSuccess: () => {
                isDialogOpen.value = false;
                ticketForm.reset();
            },
        });
    }
};

const deleteTicket = (ticketId: number) => {
    if (confirm('Naozaj chcete odstrániť tento typ lístka?')) {
        useForm({}).delete(
            `/event/${props.event.url_slug}/ticket/${ticketId}`,
            {
                preserveScroll: true,
            },
        );
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('sk-SK', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency: 'EUR',
    }).format(price);
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'paid':
            return {
                class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                label: 'Potvrdená',
            };
        case 'pending':
            return {
                class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                label: 'Čaká',
            };
        case 'cancelled':
            return {
                class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                label: 'Zrušená',
            };
        default:
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                label: status,
            };
    }
};

const totalReservations = props.event.reserved_seats;

const confirmedOrders = props.event.orders.filter(
    (o) => o.status === 'paid',
).length;
const pendingOrders = props.event.orders.filter(
    (o) => o.status === 'pending',
).length;

const getTotalRevenue = () => {
    return props.event.orders
        .filter((o) => o.status === 'paid')
        .reduce((sum, order) => {
            return (
                sum +
                order.tickets.reduce((orderSum, ticket) => {
                    return orderSum + ticket.price * ticket.pivot.amount;
                }, 0)
            );
        }, 0);
};

// Filter and Pagination States
const searchQuery = ref('');
const statusFilter = ref<'all' | 'paid' | 'pending' | 'cancelled'>('all');
const currentPage = ref(1);
const itemsPerPage = ref(20);

// Computed property for filtered orders
const filteredOrders = computed(() => {
    let orders = props.event.orders;

    // Filter by status
    if (statusFilter.value !== 'all') {
        orders = orders.filter((order) => order.status === statusFilter.value);
    }

    // Filter by search query
    if (searchQuery.value) {
        orders = orders.filter((order) => {
            return (
                order.name
                    .toLowerCase()
                    .includes(searchQuery.value.toLowerCase()) ||
                order.email
                    .toLowerCase()
                    .includes(searchQuery.value.toLowerCase())
            );
        });
    }

    return orders;
});

// Computed property for paginated orders
const paginatedOrders = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredOrders.value.slice(start, end);
});

// A new filter may have fewer pages than the one we're on.
watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;
});

// Total pages computed property
const totalPages = computed(() => {
    return Math.ceil(filteredOrders.value.length / itemsPerPage.value);
});

const openOrderDetails = (order: Order) => {
    selectedOrder.value = order;
    isOrderDetailsOpen.value = true;
};

const closeOrderDetails = () => {
    isOrderDetailsOpen.value = false;
    selectedOrder.value = null;
};

const confirmOrder = (orderSlug: string) => {
    if (confirm('Naozaj chcete potvrdiť túto objednávku?')) {
        useForm({}).post(`/order/${orderSlug}/confirm`, {
            preserveScroll: true,
            onSuccess: () => {
                closeOrderDetails();
            },
        });
    }
};

const cancelOrder = (orderSlug: string) => {
    if (confirm('Naozaj chcete zrušiť túto objednávku?')) {
        useForm({}).post(`/order/${orderSlug}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                closeOrderDetails();
            },
        });
    }
};

const getTotalTicketCount = (order: Order) => {
    return order.tickets.reduce((sum, ticket) => {
        return sum + (ticket.pivot.amount || 0);
    }, 0);
};

const openCollaboratorDialog = () => {
    collaboratorForm.reset();
    collaboratorForm.clearErrors();
    isCollaboratorDialogOpen.value = true;
};

// Collaborator management functions
const submitCollaborator = () => {
    collaboratorForm.post(`/event/${props.event.url_slug}/collaborator`, {
        preserveScroll: true,
        onSuccess: () => {
            isCollaboratorDialogOpen.value = false;
            collaboratorForm.reset();
        },
    });
};

const updateCollaboratorRole = (
    userId: number,
    newRole: 'manager' | 'staff',
) => {
    useForm({ role: newRole }).put(
        `/event/${props.event.url_slug}/collaborator/${userId}`,
        {
            preserveScroll: true,
        },
    );
};

const removeCollaborator = (userId: number) => {
    if (confirm('Naozaj chcete odstrániť tohto spolupracovníka?')) {
        useForm({}).delete(
            `/event/${props.event.url_slug}/collaborator/${userId}`,
            {
                preserveScroll: true,
            },
        );
    }
};

const openPrintPage = () => {
    window.open(`/event/${props.event.url_slug}/print`, '_blank');
};

const page = usePage();
const flash = computed(
    () =>
        (page.props as any).flash as
            | { success?: string; error?: string }
            | undefined,
);

// ---- Tabs ----
type TabKey = 'overview' | 'orders' | 'tickets' | 'collaborators';

const activeTab = ref<TabKey>('overview');

const tabs = computed<PageTab<TabKey>[]>(() => [
    { key: 'overview', title: 'Prehľad', icon: LayoutDashboardIcon },
    {
        key: 'orders',
        title: 'Objednávky',
        icon: ReceiptIcon,
        count: props.event.orders.length,
    },
    {
        key: 'tickets',
        title: 'Typy lístkov',
        icon: TicketIcon,
        count: props.event.tickets.length,
    },
    ...(props.event.user_role === 'owner'
        ? [
              {
                  key: 'collaborators' as const,
                  title: 'Spolupracovníci',
                  icon: UsersIcon,
                  count: props.event.users.length,
              },
          ]
        : []),
]);

const showPendingOrders = () => {
    statusFilter.value = 'pending';
    searchQuery.value = '';
    activeTab.value = 'orders';
};

// Slovak plural: 1 / 2–4 / 5+
const plural = (n: number, one: string, few: string, many: string) =>
    n === 1 ? one : n >= 2 && n <= 4 ? few : many;

const occupancy = computed(() =>
    props.event.seats_total
        ? Math.min(
              100,
              Math.round((totalReservations / props.event.seats_total) * 100),
          )
        : 0,
);
</script>

<template>
    <Head :title="event.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Flash Messages -->
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

            <!-- Event Header -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-6 pb-0 dark:border-sidebar-border"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div class="min-w-0">
                        <h1 class="text-3xl font-bold">{{ event.title }}</h1>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <CalendarIcon class="h-4 w-4" />
                                {{ formatDate(event.start_time) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <MapPinIcon class="h-4 w-4" />
                                {{ event.location.address }}
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            :href="`/event/${event.url_slug}`"
                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-foreground transition-all hover:bg-muted"
                            target="_blank"
                        >
                            <ExternalLinkIcon class="h-4 w-4" />
                            Zobraziť podujatie
                        </a>
                        <Button
                            variant="outline"
                            class="h-auto px-4 py-2"
                            @click="openPrintPage"
                        >
                            <PrinterIcon class="h-4 w-4" />
                            Tlačiť plánik
                        </Button>
                        <Link
                            v-if="canManage"
                            :href="`/event/${event.url_slug}/seats`"
                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-foreground transition-all hover:bg-muted"
                        >
                            <ArrowLeftRightIcon class="h-4 w-4" />
                            Zmeniť miesta
                        </Link>
                        <Link
                            v-if="canManage"
                            :href="`/event/${event.url_slug}/edit`"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-all hover:bg-blue-700"
                        >
                            <EditIcon class="h-4 w-4" />
                            Upraviť
                        </Link>
                    </div>
                </div>

                <PageTabs
                    v-model="activeTab"
                    :tabs="tabs"
                    label="Správa podujatia"
                    class="-mx-6 mt-6 px-6"
                />
            </div>

            <!-- Overview -->
            <template v-if="activeTab === 'overview'">
                <div
                    v-if="pendingOrders > 0"
                    class="flex flex-col gap-3 rounded-xl border border-yellow-300 bg-yellow-50 p-4 sm:flex-row sm:items-center dark:border-yellow-800 dark:bg-yellow-950/30"
                >
                    <AlertCircleIcon class="h-5 w-5 shrink-0 text-yellow-600" />
                    <p class="flex-1 text-sm">
                        {{ pendingOrders }}
                        {{
                            plural(
                                pendingOrders,
                                'objednávka čaká',
                                'objednávky čakajú',
                                'objednávok čaká',
                            )
                        }}
                        na potvrdenie platby.
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="showPendingOrders"
                    >
                        Zobraziť čakajúce
                    </Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <UserIcon class="h-8 w-8 text-blue-600" />
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Obsadenosť
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ totalReservations }}
                                    <span
                                        class="text-base font-normal text-muted-foreground"
                                        >/ {{ event.seats_total }}</span
                                    >
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 h-1.5 rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-blue-600"
                                :style="{ width: `${occupancy}%` }"
                            ></div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <CreditCardIcon class="h-8 w-8 text-emerald-600" />
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Príjem
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ formatPrice(getTotalRevenue()) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <CheckCircleIcon class="h-8 w-8 text-green-600" />
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Potvrdené objednávky
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ confirmedOrders }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <ClockIcon class="h-8 w-8 text-yellow-600" />
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Čakajúce objednávky
                                </p>
                                <p class="text-2xl font-bold">
                                    {{ pendingOrders }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 lg:col-span-1 dark:border-sidebar-border"
                    >
                        <h2 class="mb-4 text-lg font-semibold">Podrobnosti</h2>
                        <div class="space-y-4 text-sm">
                            <div class="flex gap-3">
                                <ClockIcon
                                    class="h-5 w-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <p class="text-muted-foreground">
                                        Registrácia
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            formatDate(event.registration_start)
                                        }}
                                        –
                                        {{ formatDate(event.registration_end) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <UserIcon
                                    class="h-5 w-5 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <p class="text-muted-foreground">Kontakt</p>
                                    <p class="font-medium">
                                        {{ event.contact_name }}
                                    </p>
                                    <p class="truncate">
                                        {{ event.contact_email }}
                                    </p>
                                    <p v-if="event.contact_phone">
                                        {{ event.contact_phone }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <LandmarkIcon
                                    class="h-5 w-5 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <p class="text-muted-foreground">
                                        Bankový účet
                                    </p>
                                    <p class="font-medium break-all">
                                        {{ event.bank_account }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 lg:col-span-2 dark:border-sidebar-border"
                    >
                        <h2 class="mb-4 text-lg font-semibold">Popis</h2>
                        <div
                            v-if="event.description"
                            class="text-muted-foreground"
                            v-html="renderedDescription"
                        ></div>
                        <p v-else class="text-sm text-muted-foreground">
                            Podujatie nemá popis.
                        </p>
                    </div>
                </div>
            </template>

            <!-- Tickets Section -->
            <div
                v-if="activeTab === 'tickets'"
                class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Typy lístkov</h2>
                    <Button
                        v-if="canManage"
                        @click="openCreateDialog"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white transition-all hover:bg-green-700"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Pridať lístok
                    </Button>
                </div>
                <div
                    v-if="event.tickets.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    <TicketIcon class="mx-auto mb-3 h-12 w-12 opacity-50" />
                    <p>Žiadne lístky</p>
                </div>
                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="ticket in event.tickets"
                        :key="ticket.id"
                        class="rounded-lg border border-sidebar-border/50 bg-muted/30 p-4"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-lg font-semibold">
                                {{ ticket.title }}
                            </h3>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="canManage"
                                    @click="openEditDialog(ticket)"
                                    variant="outline"
                                    class="p-1.5"
                                >
                                    <EditIcon class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="canManage"
                                    @click="deleteTicket(ticket.id)"
                                    variant="outline"
                                    class="p-1.5 text-red-600 hover:bg-red-600 hover:text-white"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-bold text-blue-600">{{
                                formatPrice(ticket.price)
                            }}</span>
                            <span class="text-sm text-muted-foreground"
                                >{{ ticket.reservations }} rezervácií</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Section -->
            <div
                v-if="activeTab === 'orders'"
                class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
            >
                <!-- Filters -->
                <div
                    class="mb-4 flex flex-col gap-2 md:flex-row md:items-center"
                >
                    <div class="relative flex-1 md:max-w-sm">
                        <SearchIcon
                            class="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Hľadať podľa mena alebo e-mailu"
                            aria-label="Hľadať objednávky"
                            class="pl-8"
                        />
                    </div>
                    <Label for="statusFilter" class="sr-only"
                        >Filter podľa stavu</Label
                    >
                    <select
                        id="statusFilter"
                        v-model="statusFilter"
                        class="h-9 rounded-md border bg-transparent px-2 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    >
                        <option value="all">Všetky stavy</option>
                        <option value="paid">Potvrdené</option>
                        <option value="pending">Čakajúce</option>
                        <option value="cancelled">Zrušené</option>
                    </select>
                    <Link
                        :href="`/event/${event.url_slug}/import-csv`"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-sm text-foreground transition-all hover:bg-muted md:ml-auto"
                    >
                        <UploadIcon class="h-4 w-4" />
                        Import z CSV
                    </Link>
                </div>

                <div
                    v-if="event.orders.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    <MailIcon class="mx-auto mb-3 h-12 w-12 opacity-50" />
                    <p>Žiadne objednávky</p>
                </div>
                <div
                    v-else-if="filteredOrders.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    <SearchIcon class="mx-auto mb-3 h-12 w-12 opacity-50" />
                    <p>Žiadne objednávky nezodpovedajú filtru</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="order in paginatedOrders"
                        :key="order.id"
                        class="flex items-center gap-3 rounded-lg border border-sidebar-border/50 p-2 transition-colors hover:border-sidebar-border"
                    >
                        <!-- Order Name & Status -->
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <span class="truncate font-medium">{{
                                order.name
                            }}</span>
                            <span
                                :class="getStatusBadge(order.status).class"
                                class="rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap"
                            >
                                {{ getStatusBadge(order.status).label }}
                            </span>
                        </div>

                        <!-- Email -->
                        <div
                            class="hidden min-w-0 flex-1 items-center gap-1.5 text-xs text-muted-foreground md:flex"
                        >
                            <MailIcon class="h-3 w-3 flex-shrink-0" />
                            <span class="truncate">{{ order.email }}</span>
                        </div>

                        <!-- Tickets Count -->
                        <div
                            class="hidden items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground lg:flex"
                        >
                            <TicketIcon class="h-3 w-3" />
                            <span
                                >{{ getTotalTicketCount(order) }} lístkov</span
                            >
                        </div>

                        <!-- Reservations Count -->
                        <div
                            class="hidden items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground lg:flex"
                        >
                            <UserIcon class="h-3 w-3" />
                            <span>{{ order.reservations.length }} rez.</span>
                        </div>

                        <!-- Date -->
                        <div
                            class="hidden text-xs whitespace-nowrap text-muted-foreground xl:block"
                        >
                            {{ formatDate(order.created_at) }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1">
                            <!-- Confirm Button (only for pending) -->
                            <Button
                                v-if="order.status === 'pending'"
                                @click.stop="confirmOrder(order.url_slug)"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-green-600 hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900"
                                title="Potvrdiť objednávku"
                            >
                                <CheckIcon class="h-4 w-4" />
                            </Button>

                            <!-- Cancel Button (only for pending) -->
                            <Button
                                v-if="order.status === 'pending'"
                                @click.stop="cancelOrder(order.url_slug)"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-600 hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900"
                                title="Zrušiť objednávku"
                            >
                                <XIcon class="h-4 w-4" />
                            </Button>

                            <!-- Details Button -->
                            <Button
                                @click="openOrderDetails(order)"
                                variant="ghost"
                                size="sm"
                                class="h-8 px-3 text-xs"
                            >
                                Detail
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="mt-4">
                    <div class="flex items-center justify-between">
                        <Button
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            variant="outline"
                            class="px-4 py-2"
                            :disabled="currentPage === 1"
                        >
                            <ChevronLeftIcon class="h-4 w-4" />
                            Predchádzajúca
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            Stránka {{ currentPage }} z {{ totalPages }}
                        </span>
                        <Button
                            @click="
                                currentPage = Math.min(
                                    totalPages,
                                    currentPage + 1,
                                )
                            "
                            variant="outline"
                            class="px-4 py-2"
                            :disabled="currentPage === totalPages"
                        >
                            Ďalšia
                            <ChevronRightIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Collaborators Section (Owner Only) -->
            <div
                v-if="
                    activeTab === 'collaborators' && event.user_role === 'owner'
                "
                class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Spolupracovníci</h2>
                    <Button
                        @click="openCollaboratorDialog"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white transition-all hover:bg-green-700"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Pridať spolupracovníka
                    </Button>
                </div>
                <div
                    v-if="event.users.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    <UserIcon class="mx-auto mb-3 h-12 w-12 opacity-50" />
                    <p>Žiadni spolupracovníci</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="user in event.users"
                        :key="user.id"
                        class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-muted/30 p-3"
                    >
                        <div class="flex items-center gap-3">
                            <UserIcon class="h-5 w-5 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ user.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ user.email }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-block rounded-full px-2 py-1 text-xs font-medium"
                                :class="
                                    user.pivot?.role === 'manager'
                                        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
                                        : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
                                "
                            >
                                {{
                                    user.pivot?.role === 'manager'
                                        ? 'Manažér'
                                        : 'Personál'
                                }}
                            </span>
                            <Button
                                @click="
                                    updateCollaboratorRole(
                                        user.id,
                                        user.pivot?.role === 'manager'
                                            ? 'staff'
                                            : 'manager',
                                    )
                                "
                                variant="outline"
                                class="p-1.5"
                                title="Zmeniť rolu"
                            >
                                <ShieldIcon class="h-4 w-4" />
                            </Button>
                            <Button
                                @click="removeCollaborator(user.id)"
                                variant="outline"
                                class="p-1.5 text-red-600 hover:bg-red-600 hover:text-white"
                                title="Odstrániť"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Form Dialog -->
            <Dialog v-model:open="isDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingTicket
                                    ? 'Upraviť typ lístka'
                                    : 'Pridať nový typ lístka'
                            }}
                        </DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div>
                            <Label for="title">Názov</Label>
                            <Input
                                id="title"
                                v-model="ticketForm.title"
                                placeholder="Napíšte názov lístka"
                                :class="{
                                    'border-red-500': ticketForm.errors.title,
                                }"
                            />
                            <p
                                v-if="ticketForm.errors.title"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ ticketForm.errors.title }}
                            </p>
                        </div>
                        <div>
                            <Label for="price">Cena</Label>
                            <Input
                                id="price"
                                v-model="ticketForm.price"
                                placeholder="Napíšte cenu lístka"
                                type="number"
                                min="0"
                                step="0.01"
                                :class="{
                                    'border-red-500': ticketForm.errors.price,
                                }"
                            />
                            <p
                                v-if="ticketForm.errors.price"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ ticketForm.errors.price }}
                            </p>
                        </div>
                        <div>
                            <Label for="reservations">Počet rezervácií</Label>
                            <Input
                                id="reservations"
                                v-model="ticketForm.reservations"
                                placeholder="Napíšte počet rezervácií"
                                type="number"
                                min="1"
                                :class="{
                                    'border-red-500':
                                        ticketForm.errors.reservations,
                                }"
                            />
                            <p
                                v-if="ticketForm.errors.reservations"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ ticketForm.errors.reservations }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button
                            @click="isDialogOpen = false"
                            variant="outline"
                            class="cursor-pointer px-4 py-2"
                        >
                            Zrušiť
                        </Button>
                        <Button
                            @click="submitTicket"
                            class="cursor-pointer bg-blue-600 px-4 py-2 text-white transition-all hover:bg-blue-700"
                        >
                            Uložiť
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Order Details Modal -->
            <Dialog v-model:open="isOrderDetailsOpen">
                <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            <span>Detail objednávky</span>
                            <br />
                            <Link
                                v-if="selectedOrder"
                                :href="`/order/${selectedOrder.url_slug}`"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon class="h-4 w-4" />
                                Otvoriť v novom okne
                            </Link>
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedOrder" class="space-y-6">
                        <!-- Customer Info -->
                        <div>
                            <h3 class="mb-3 text-lg font-semibold">Zákazník</h3>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3">
                                    <UserIcon
                                        class="mt-0.5 h-5 w-5 text-muted-foreground"
                                    />
                                    <div>
                                        <p class="font-medium">
                                            {{ selectedOrder.name }}
                                        </p>
                                        <span
                                            :class="
                                                getStatusBadge(
                                                    selectedOrder.status,
                                                ).class
                                            "
                                            class="mt-1 inline-block rounded-full px-2 py-1 text-xs font-medium"
                                        >
                                            {{
                                                getStatusBadge(
                                                    selectedOrder.status,
                                                ).label
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <MailIcon
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                    <span>{{ selectedOrder.email }}</span>
                                </div>
                                <div
                                    v-if="selectedOrder.phone"
                                    class="flex items-center gap-3"
                                >
                                    <PhoneIcon
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                    <span>{{ selectedOrder.phone }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <CreditCardIcon
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                    <span
                                        >Variabilný symbol:
                                        {{
                                            selectedOrder.variable_symbol
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-3">
                                    <CalendarIcon
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                    <span>{{
                                        formatDate(selectedOrder.created_at)
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tickets -->
                        <div>
                            <h3 class="mb-3 text-lg font-semibold">Lístky</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="ticket in selectedOrder.tickets"
                                    :key="ticket.id"
                                    class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950"
                                >
                                    <div>
                                        <p
                                            class="font-medium text-blue-900 dark:text-blue-100"
                                        >
                                            {{ ticket.title }}
                                        </p>
                                        <p
                                            class="text-sm text-blue-700 dark:text-blue-300"
                                        >
                                            {{ ticket.pivot.amount }}x
                                            {{ formatPrice(ticket.price) }}
                                        </p>
                                    </div>
                                    <p
                                        class="text-lg font-bold text-blue-900 dark:text-blue-100"
                                    >
                                        {{
                                            formatPrice(
                                                ticket.price *
                                                    ticket.pivot.amount,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Reservations -->
                        <div v-if="selectedOrder.reservations.length > 0">
                            <h3 class="mb-3 text-lg font-semibold">
                                Rezervácie ({{
                                    selectedOrder.reservations.length
                                }})
                            </h3>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div
                                    v-for="reservation in selectedOrder.reservations"
                                    :key="reservation.id"
                                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/50 bg-muted/50 p-3"
                                >
                                    <UserIcon
                                        class="h-5 w-5 text-muted-foreground"
                                    />
                                    <div class="flex-1">
                                        <p class="font-medium">
                                            {{ reservation.guest_name }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Sedadlo
                                            {{ reservation.seat_number }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div
                            v-if="selectedOrder.status === 'pending'"
                            class="flex gap-3 border-t pt-4"
                        >
                            <Button
                                @click="confirmOrder(selectedOrder.url_slug)"
                                class="flex-1 bg-green-600 hover:bg-green-700"
                            >
                                <CheckIcon class="mr-2 h-4 w-4" />
                                Potvrdiť objednávku
                            </Button>
                            <Button
                                @click="cancelOrder(selectedOrder.url_slug)"
                                variant="outline"
                                class="flex-1 border-red-600 text-red-600 hover:bg-red-600 hover:text-white"
                            >
                                <XIcon class="mr-2 h-4 w-4" />
                                Zrušiť objednávku
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Collaborator Form Dialog -->
            <Dialog v-model:open="isCollaboratorDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Pridať spolupracovníka</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div>
                            <Label for="user_id">Užívateľ</Label>
                            <select
                                id="user_id"
                                v-model="collaboratorForm.user_id"
                                class="w-full rounded-md border p-2 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">Vyberte užívateľa</option>
                                <option
                                    v-for="user in props.allUsers"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <p
                                v-if="collaboratorForm.errors.user_id"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ collaboratorForm.errors.user_id }}
                            </p>
                        </div>
                        <div>
                            <Label for="role">Rola</Label>
                            <select
                                id="role"
                                v-model="collaboratorForm.role"
                                class="w-full rounded-md border p-2 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="manager">Manažér</option>
                                <option value="staff">Personál</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button
                            @click="isCollaboratorDialogOpen = false"
                            variant="outline"
                            class="cursor-pointer px-4 py-2"
                        >
                            Zrušiť
                        </Button>
                        <Button
                            @click="submitCollaborator"
                            class="cursor-pointer bg-blue-600 px-4 py-2 text-white transition-all hover:bg-blue-700"
                        >
                            Pridať
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
