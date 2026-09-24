<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarIcon,
    MapPinIcon,
    TicketIcon,
    UserIcon,
    CheckCircleIcon,
    ClockIcon,
    MailIcon,
    PhoneIcon,
    CreditCardIcon,
    EditIcon,
    PlusIcon,
    TrashIcon,
    CheckIcon,
    XIcon,
    ExternalLinkIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    SearchIcon,
    ShieldIcon,
    UploadIcon,
    PrinterIcon,
    ArrowLeftRightIcon
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { marked } from 'marked';

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
    return props.event.user_role === 'owner' || props.event.user_role === 'manager';
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

const isPrintDialogOpen = ref(false);
const printContainerRef = ref<HTMLElement | null>(null);

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
    ticketForm.price = (ticket.price).toString();
    ticketForm.reservations = ticket.reservations.toString();
    ticketForm.clearErrors();
    isDialogOpen.value = true;
};

const submitTicket = () => {
    if (editingTicket.value) {
        // Update existing ticket
        ticketForm.put(`/event/${props.event.url_slug}/ticket/${editingTicket.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDialogOpen.value = false;
                ticketForm.reset();
            },
        });
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
        useForm({}).delete(`/event/${props.event.url_slug}/ticket/${ticketId}`, {
            preserveScroll: true,
        });
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
            return { class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300', label: 'Potvrdená' };
        case 'pending':
            return { class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300', label: 'Čaká' };
        case 'cancelled':
            return { class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300', label: 'Zrušená' };
        default:
            return { class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300', label: status };
    }
};

const totalReservations = props.event.reserved_seats;

const confirmedOrders = props.event.orders.filter(o => o.status === 'paid').length;
const pendingOrders = props.event.orders.filter(o => o.status === 'pending').length;

const getTotalRevenue = () => {
    return props.event.orders
        .filter(o => o.status === 'paid')
        .reduce((sum, order) => {
            return sum + order.tickets.reduce((orderSum, ticket) => {
                return orderSum + (ticket.price * ticket.pivot.amount);
            }, 0);
        }, 0);
};

// Filter and Pagination States
const searchQuery = ref('');
const statusFilter = ref<'all' | 'paid' | 'pending' | 'cancelled'>('all');
const currentPage = ref(1);
const itemsPerPage = ref(5);

// Computed property for filtered orders
const filteredOrders = computed(() => {
    let orders = props.event.orders;

    // Filter by status
    if (statusFilter.value !== 'all') {
        orders = orders.filter(order => order.status === statusFilter.value);
    }

    // Filter by search query
    if (searchQuery.value) {
        orders = orders.filter(order => {
            return order.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                   order.email.toLowerCase().includes(searchQuery.value.toLowerCase());
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

const updateCollaboratorRole = (userId: number, newRole: 'manager' | 'staff') => {
    useForm({ role: newRole }).put(`/event/${props.event.url_slug}/collaborator/${userId}`, {
        preserveScroll: true,
    });
};

const removeCollaborator = (userId: number) => {
    if (confirm('Naozaj chcete odstrániť tohto spolupracovníka?')) {
        useForm({}).delete(`/event/${props.event.url_slug}/collaborator/${userId}`, {
            preserveScroll: true,
        });
    }
};

const openPrintPage = () => {
    window.open(`/event/${props.event.url_slug}/print`, '_blank');
};

// Get all reservations with seat numbers and guest names
const allReservations = computed(() => {
    return props.event.orders
        .filter(o => o.status === 'paid' || o.status === 'pending')
        .flatMap(order => order.reservations);
});

const reservedSeatsMap = computed(() => {
    const map: Record<number, string> = {};
    allReservations.value.forEach(res => {
        map[res.seat_number] = res.guest_name;
    });
    return map;
});

const page = usePage();
const flash = computed(() => (page.props as any).flash as { success?: string; error?: string } | undefined);
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
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold mb-2">{{ event.title }}</h1>
                        <p v-if="event.description" class="text-muted-foreground mb-4" v-html="renderedDescription"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/event/${event.url_slug}`"
                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-foreground transition-all hover:bg-muted"
                            target="_blank"
                        >
                            <TicketIcon class="w-4 h-4" />
                            Zobraziť podujatie
                        </Link>
                        <Link
                            v-if="canManage"
                            :href="`/event/${event.url_slug}/seats`"
                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-foreground transition-all hover:bg-muted"
                        >
                            <ArrowLeftRightIcon class="w-4 h-4" />
                            Zmeniť miesta
                        </Link>
                        <Link
                            v-if="canManage"
                            :href="`/event/${event.url_slug}/edit`"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-all hover:bg-blue-700"
                        >
                            <EditIcon class="w-4 h-4" />
                            Upraviť
                        </Link>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="flex items-center gap-3">
                        <CalendarIcon class="w-5 h-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Začiatok</p>
                            <p class="font-medium">{{ formatDate(event.start_time) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <MapPinIcon class="w-5 h-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Lokalita</p>
                            <p class="font-medium">{{ event.location.address }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <TicketIcon class="w-5 h-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Obsadenosť</p>
                            <p class="font-medium">{{ totalReservations }} / {{ event.seats_total }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <CreditCardIcon class="w-5 h-5 text-muted-foreground" />
                        <div>
                            <p class="text-sm text-muted-foreground">Príjem</p>
                            <p class="font-medium">{{ formatPrice(getTotalRevenue()) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <div class="flex items-center gap-3 mb-2">
                        <CheckCircleIcon class="w-8 h-8 text-green-600" />
                        <div>
                            <p class="text-sm text-muted-foreground">Potvrdené objednávky</p>
                            <p class="text-2xl font-bold">{{ confirmedOrders }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <div class="flex items-center gap-3 mb-2">
                        <ClockIcon class="w-8 h-8 text-yellow-600" />
                        <div>
                            <p class="text-sm text-muted-foreground">Čakajúce objednávky</p>
                            <p class="text-2xl font-bold">{{ pendingOrders }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                    <div class="flex items-center gap-3 mb-2">
                        <UserIcon class="w-8 h-8 text-blue-600" />
                        <div>
                            <p class="text-sm text-muted-foreground">Celkovo rezervácií</p>
                            <p class="text-2xl font-bold">{{ totalReservations }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tickets Section -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold">Typy lístkov</h2>
                    <Button
                        v-if="canManage"
                        @click="openCreateDialog"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white transition-all hover:bg-green-700"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Pridať lístok
                    </Button>
                </div>
                <div v-if="event.tickets.length === 0" class="text-center py-8 text-muted-foreground">
                    <TicketIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>Žiadne lístky</p>
                </div>
                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="ticket in event.tickets"
                        :key="ticket.id"
                        class="p-4 rounded-lg border border-sidebar-border/50 bg-muted/30"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold text-lg">{{ ticket.title }}</h3>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="canManage"
                                    @click="openEditDialog(ticket)"
                                    variant="outline"
                                    class="p-1.5"
                                >
                                    <EditIcon class="w-4 h-4" />
                                </Button>
                                <Button
                                    v-if="canManage"
                                    @click="deleteTicket(ticket.id)"
                                    variant="outline"
                                    class="p-1.5 text-red-600 hover:bg-red-600 hover:text-white"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-bold text-blue-600">{{ formatPrice(ticket.price) }}</span>
                            <span class="text-sm text-muted-foreground">{{ ticket.reservations }} rezervácií</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold">Objednávky</h2>
                    <Link
                        :href="`/event/${event.url_slug}/import-csv`"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-500 px-4 py-2 text-white transition-all hover:bg-indigo-700"
                    >
                        <UploadIcon class="w-4 h-4" />
                        CSV Import
                    </Link>
                </div>

                <!-- Filters -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                    <!-- Search -->
                    <div class="flex items-center gap-2 mb-2 md:mb-0">
                        <SearchIcon class="w-5 h-5 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Hľadať podľa mena alebo e-mailu"
                            class="flex-1"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <Label for="statusFilter" class="sr-only">Filter podľa stavu</Label>
                        <select
                            id="statusFilter"
                            v-model="statusFilter"
                            class="p-2 text-sm rounded-md border focus:ring-1 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="all">Všetky stavy</option>
                            <option value="paid">Potvrdené</option>
                            <option value="pending">Čakajúce</option>
                            <option value="cancelled">Zrušené</option>
                        </select>
                    </div>
                </div>

                <div v-if="event.orders.length === 0" class="text-center py-8 text-muted-foreground">
                    <MailIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>Žiadne objednávky</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="order in paginatedOrders"
                        :key="order.id"
                        class="flex items-center gap-3 p-2 rounded-lg border border-sidebar-border/50 hover:border-sidebar-border transition-colors"
                    >
                        <!-- Order Name & Status -->
                        <div class="flex-1 min-w-0 flex items-center gap-2">
                            <span class="font-medium truncate">{{ order.name }}</span>
                            <span
                                :class="getStatusBadge(order.status).class"
                                class="px-2 py-0.5 text-[10px] font-medium rounded-full whitespace-nowrap"
                            >
                                {{ getStatusBadge(order.status).label }}
                            </span>
                        </div>

                        <!-- Email -->
                        <div class="hidden md:flex items-center gap-1.5 text-xs text-muted-foreground min-w-0 flex-1">
                            <MailIcon class="w-3 h-3 flex-shrink-0" />
                            <span class="truncate">{{ order.email }}</span>
                        </div>

                        <!-- Tickets Count -->
                        <div class="hidden lg:flex items-center gap-1.5 text-xs text-muted-foreground whitespace-nowrap">
                            <TicketIcon class="w-3 h-3" />
                            <span>{{ getTotalTicketCount(order) }} lístkov</span>
                        </div>

                        <!-- Reservations Count -->
                        <div class="hidden lg:flex items-center gap-1.5 text-xs text-muted-foreground whitespace-nowrap">
                            <UserIcon class="w-3 h-3" />
                            <span>{{ order.reservations.length }} rez.</span>
                        </div>

                        <!-- Date -->
                        <div class="hidden xl:block text-xs text-muted-foreground whitespace-nowrap">
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
                                class="h-8 w-8 p-0 text-green-600 hover:text-green-700 hover:bg-green-100 dark:hover:bg-green-900"
                                title="Potvrdiť objednávku"
                            >
                                <CheckIcon class="w-4 h-4" />
                            </Button>

                            <!-- Cancel Button (only for pending) -->
                            <Button
                                v-if="order.status === 'pending'"
                                @click.stop="cancelOrder(order.url_slug)"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-100 dark:hover:bg-red-900"
                                title="Zrušiť objednávku"
                            >
                                <XIcon class="w-4 h-4" />
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
                            <ChevronLeftIcon class="w-4 h-4" />
                            Predchádzajúca
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            Stránka {{ currentPage }} z {{ totalPages }}
                        </span>
                        <Button
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            variant="outline"
                            class="px-4 py-2"
                            :disabled="currentPage === totalPages"
                        >
                            Ďalšia
                            <ChevronRightIcon class="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Collaborators Section (Owner Only) -->
            <div v-if="event.user_role === 'owner'" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold">Spolupracovníci</h2>
                    <Button
                        @click="openCollaboratorDialog"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white transition-all hover:bg-green-700"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Pridať spolupracovníka
                    </Button>
                </div>
                <div v-if="event.users.length === 0" class="text-center py-8 text-muted-foreground">
                    <UserIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>Žiadni spolupracovníci</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="user in event.users"
                        :key="user.id"
                        class="flex items-center justify-between p-3 rounded-lg border border-sidebar-border/50 bg-muted/30"
                    >
                        <div class="flex items-center gap-3">
                            <UserIcon class="w-5 h-5 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ user.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-block px-2 py-1 text-xs font-medium rounded-full"
                                :class="user.pivot?.role === 'manager'
                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'"
                            >
                                {{ user.pivot?.role === 'manager' ? 'Manažér' : 'Personál' }}
                            </span>
                            <Button
                                @click="updateCollaboratorRole(user.id, user.pivot?.role === 'manager' ? 'staff' : 'manager')"
                                variant="outline"
                                class="p-1.5"
                                title="Zmeniť rolu"
                            >
                                <ShieldIcon class="w-4 h-4" />
                            </Button>
                            <Button
                                @click="removeCollaborator(user.id)"
                                variant="outline"
                                class="p-1.5 text-red-600 hover:bg-red-600 hover:text-white"
                                title="Odstrániť"
                            >
                                <TrashIcon class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tickets Print Section -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 bg-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold">Tlač plániku sedenia</h2>
                    <Button
                        @click="openPrintPage"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-all hover:bg-blue-700"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        Tlačiť
                    </Button>
                </div>

                <div v-if="event.orders.length === 0" class="text-center py-8 text-muted-foreground">
                    <TicketIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>Žiadne rezervácie na tlačenie</p>
                </div>
                <div v-else>
                    <!-- Printable Seat Map -->
                    <div
                        v-if="isPrintDialogOpen"
                        ref="printContainerRef"
                        class="p-4 rounded-lg border border-sidebar-border/50 bg-muted/30"
                    >
                        <h3 class="text-lg font-semibold mb-3">Plánik sedenia</h3>
                        <div class="grid grid-cols-4 gap-4">
                            <div
                                v-for="seat in event.seats_total"
                                :key="seat"
                                class="flex items-center justify-center p-2 border rounded-lg"
                            >
                                <div
                                    v-if="reservedSeatsMap[seat]"
                                    class="w-4 h-4 rounded-full bg-red-600"
                                    title="Obsadené"
                                ></div>
                                <div
                                    v-else
                                    class="w-4 h-4 rounded-full bg-green-600"
                                    title="Voľné"
                                ></div>
                                <span class="ml-2 text-sm" v-if="reservedSeatsMap[seat]">
                                    {{ reservedSeatsMap[seat] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Form Dialog -->
            <Dialog v-model:open="isDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {{ editingTicket ? 'Upraviť typ lístka' : 'Pridať nový typ lístka' }}
                        </DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div>
                            <Label for="title">Názov</Label>
                            <Input
                                id="title"
                                v-model="ticketForm.title"
                                placeholder="Napíšte názov lístka"
                                :class="{ 'border-red-500': ticketForm.errors.title }"
                            />
                            <p v-if="ticketForm.errors.title" class="mt-1 text-sm text-red-500">
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
                                :class="{ 'border-red-500': ticketForm.errors.price }"
                            />
                            <p v-if="ticketForm.errors.price" class="mt-1 text-sm text-red-500">
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
                                :class="{ 'border-red-500': ticketForm.errors.reservations }"
                            />
                            <p v-if="ticketForm.errors.reservations" class="mt-1 text-sm text-red-500">
                                {{ ticketForm.errors.reservations }}
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <Button
                            @click="isDialogOpen = false"
                            variant="outline"
                            class="px-4 py-2 cursor-pointer"
                        >
                            Zrušiť
                        </Button>
                        <Button
                            @click="submitTicket"
                            class="px-4 py-2 cursor-pointer bg-blue-600 text-white transition-all hover:bg-blue-700"
                        >
                            Uložiť
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Order Details Modal -->
            <Dialog v-model:open="isOrderDetailsOpen">
                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            <span>Detail objednávky</span>
                            <br>
                            <Link
                                v-if="selectedOrder"
                                :href="`/order/${selectedOrder.url_slug}`"
                                class="inline-flex items-center gap-1.5 text-sm mt-4 text-blue-600 hover:text-blue-700"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon class="w-4 h-4" />
                                Otvoriť v novom okne
                            </Link>
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedOrder" class="space-y-6">
                        <!-- Customer Info -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Zákazník</h3>
                            <div class="space-y-2">
                                <div class="flex items-start gap-3">
                                    <UserIcon class="w-5 h-5 text-muted-foreground mt-0.5" />
                                    <div>
                                        <p class="font-medium">{{ selectedOrder.name }}</p>
                                        <span
                                            :class="getStatusBadge(selectedOrder.status).class"
                                            class="inline-block px-2 py-1 text-xs font-medium rounded-full mt-1"
                                        >
                                            {{ getStatusBadge(selectedOrder.status).label }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <MailIcon class="w-5 h-5 text-muted-foreground" />
                                    <span>{{ selectedOrder.email }}</span>
                                </div>
                                <div v-if="selectedOrder.phone" class="flex items-center gap-3">
                                    <PhoneIcon class="w-5 h-5 text-muted-foreground" />
                                    <span>{{ selectedOrder.phone }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <CreditCardIcon class="w-5 h-5 text-muted-foreground" />
                                    <span>Variabilný symbol: {{ selectedOrder.variable_symbol }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <CalendarIcon class="w-5 h-5 text-muted-foreground" />
                                    <span>{{ formatDate(selectedOrder.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tickets -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Lístky</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="ticket in selectedOrder.tickets"
                                    :key="ticket.id"
                                    class="flex items-center justify-between p-3 rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800"
                                >
                                    <div>
                                        <p class="font-medium text-blue-900 dark:text-blue-100">{{ ticket.title }}</p>
                                        <p class="text-sm text-blue-700 dark:text-blue-300">{{ ticket.pivot.amount }}x {{ formatPrice(ticket.price) }}</p>
                                    </div>
                                    <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                        {{ formatPrice(ticket.price * ticket.pivot.amount) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Reservations -->
                        <div v-if="selectedOrder.reservations.length > 0">
                            <h3 class="text-lg font-semibold mb-3">Rezervácie ({{ selectedOrder.reservations.length }})</h3>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div
                                    v-for="reservation in selectedOrder.reservations"
                                    :key="reservation.id"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border border-sidebar-border/50"
                                >
                                    <UserIcon class="w-5 h-5 text-muted-foreground" />
                                    <div class="flex-1">
                                        <p class="font-medium">{{ reservation.guest_name }}</p>
                                        <p class="text-sm text-muted-foreground">Sedadlo {{ reservation.seat_number }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div v-if="selectedOrder.status === 'pending'" class="flex gap-3 pt-4 border-t">
                            <Button
                                @click="confirmOrder(selectedOrder.url_slug)"
                                class="flex-1 bg-green-600 hover:bg-green-700"
                            >
                                <CheckIcon class="w-4 h-4 mr-2" />
                                Potvrdiť objednávku
                            </Button>
                            <Button
                                @click="cancelOrder(selectedOrder.url_slug)"
                                variant="outline"
                                class="flex-1 text-red-600 hover:bg-red-600 hover:text-white border-red-600"
                            >
                                <XIcon class="w-4 h-4 mr-2" />
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
                                class="p-2 text-sm rounded-md border focus:ring-1 focus:ring-blue-500 focus:outline-none w-full"
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
                            <p v-if="collaboratorForm.errors.user_id" class="mt-1 text-sm text-red-500">
                                {{ collaboratorForm.errors.user_id }}
                            </p>
                        </div>
                        <div>
                            <Label for="role">Rola</Label>
                            <select
                                id="role"
                                v-model="collaboratorForm.role"
                                class="p-2 text-sm rounded-md border focus:ring-1 focus:ring-blue-500 focus:outline-none w-full"
                            >
                                <option value="manager">Manažér</option>
                                <option value="staff">Personál</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <Button
                            @click="isCollaboratorDialogOpen = false"
                            variant="outline"
                            class="px-4 py-2 cursor-pointer"
                        >
                            Zrušiť
                        </Button>
                        <Button
                            @click="submitCollaborator"
                            class="px-4 py-2 cursor-pointer bg-blue-600 text-white transition-all hover:bg-blue-700"
                        >
                            Pridať
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Print styles for seat map */
@media print {
    .print-container {
        display: block !important;
        page-break-after: always;
    }

    .no-print {
        display: none !important;
    }

    /* Hide the header and footer when printing */
    @page {
        margin: 0;
        size: A4 landscape;
    }

    body {
        margin: 0;
        padding: 0;
    }
}
</style>
