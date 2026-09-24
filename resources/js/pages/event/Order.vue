<script lang="ts" setup>
import OrderLayout from '@/layouts/OrderLayout.vue';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import SeatSelector from '@/components/order/SeatSelector.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import EventCard from '@/components/order/EventCard.vue';

// import { create } from '@/routes/reservation';

interface Ticket {
    id: number;
    title: string;
    price: number;
    reservations: number;
}

interface Event {
    id: number;
    title: string;
    start_time: string;
    url_slug: string;
    seats_total: number;
    registration_start?: string;
    registration_end?: string;
    user_id?: number;
    location: string;
    reservations: number[];
    seat_names?: Record<number, string>;
    multiple_reservations_per_ticket: boolean;
}

interface Location {
    id: number;
    name: string;
    address: string;
}

const props = defineProps<{ event: Event; tickets: Ticket[]; location: Location }>();
const filteredTickets = computed(() =>
    props.tickets.filter(ticket => quantities[ticket.id] > 0)
);

const readableErrors = ref<string[]>([]);
const errors = ref<{ [key: string]: string }>({});

const orderForm = reactive({
    name: '',
    email: '',
    phone: '',
});

function submitOrder() {
    // Simple Inertia template — fill in your route and adjust payload as needed
    const payload = {
        name: orderForm.name,
        email: orderForm.email,
        phone: orderForm.phone,
        // Map of ticketId -> quantity
        tickets: { ...quantities },
        // Selected seat indices and guest names
        seats: [...selectedSeats.value],
        guests: [...guestNames.value],
        // Helpful context (optional)
        event_id: props.event.id,
        event_slug: props.event.url_slug,
        total_price: totalPrice.value,
    };

    // Post to the web route for this event's order store endpoint
    router.post(OrderController.create(props.event.url_slug).url, payload, {
        preserveScroll: true,
        onStart: () => {
            // Clear any generic banners
            readableErrors.value = [];
            errors.value = {};
        },
        onError: (serverErrors: Record<string, string>) => {
            // Server-side validation errors (422) will be here
            errors.value = { ...serverErrors };

            if (serverErrors.seats) {
                readableErrors.value.push(serverErrors.seats);
                selectedSeats.value = [];
                guestNames.value = [];
                currentStep.value = 2;
            } else {
                readableErrors.value.push('Nepodarilo sa odoslať formulár. Skontrolujte chyby a skúste to znova.');
                readableErrors.value.push(...Object.values(serverErrors));
            }
        },
        onSuccess: () => {
            // Optional: navigate, show toast, or reset local state
            // Example: reset selections
            // selectedSeats.value = [];
            // guestNames.value = [];
        },
        onFinish: () => {
            // Called after success or error
        },
    });
}

const quantities = reactive<{ [key: number]: number }>({});
props.tickets.forEach((ticket) => {
    quantities[ticket.id] = 0;
});

function increment(ticketId: number) {
    quantities[ticketId] = (quantities[ticketId] || 0) + 1;
}

function decrement(ticketId: number) {
    if (quantities[ticketId] > 0) {
        quantities[ticketId]--;
    }
}

const totalPrice = computed(() => {
    return props.tickets.reduce((sum, ticket) => {
        return sum + (quantities[ticket.id] || 0) * ticket.price;
    }, 0);
});

const currentStep = ref(1);
const stepLabels = [
    'Údaje a lístky',
    'Výber miest',
    'Mená hostí',
    'Potvrdenie objednávky',
];

function nextStep() {
    if (currentStep.value === 1 && !validateStepOne()) {
        return;
    }
    readableErrors.value = [];
    if (currentStep.value < 4) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

const selectedSeats = ref<number[]>([]);
const guestNames = ref<string[]>([]);

// Compute the dynamic max seats based on selected ticket quantities
const maxSelectableSeats = computed(() => {
    return props.tickets.reduce((sum, ticket) => {
        const qty = quantities[ticket.id] || 0;
        const perTicket = ticket.reservations ?? 1;
        return sum + qty * perTicket;
    }, 0);
});

// Ensure we never keep more selected seats than allowed
watch(maxSelectableSeats, (limit) => {
    if (selectedSeats.value.length > limit) {
        selectedSeats.value = selectedSeats.value.slice(0, Math.max(0, limit));
    }
});

// Sync guestNames with selectedSeats
watch(selectedSeats, (seats) => {
    // If seats are added, add empty guest names
    while (guestNames.value.length < seats.length) {
        guestNames.value.push('');
    }
    // If seats are removed, remove extra guest names
    while (guestNames.value.length > seats.length) {
        guestNames.value.pop();
    }
});

const allTicketsZero = computed(() =>
    Object.values(quantities).every(qty => qty === 0)
);

const validateStepOne = () => {
    readableErrors.value = [];
    errors.value = {};
    let hasError = false;
    if (!orderForm.name.trim()) {
        errors.value.name = 'Meno je povinné.';
        hasError = true;
    }
    if (!orderForm.email.trim()) {
        errors.value.email = 'Email je povinný.';
        hasError = true;
    } else if (!/\S+@\S+\.\S+/.test(orderForm.email)) {
        errors.value.email = 'Neplatný formát emailu.';
        hasError = true;
    }
    if (!orderForm.phone.trim()) {
        errors.value.phone = 'Telefónne číslo je povinné.';
        hasError = true;
    } else {
        // Remove spaces, dashes, parentheses, and plus sign to count digits only
        const digitsOnly = orderForm.phone.replace(/[^\d]/g, '');
        // Check if format is valid (can contain +, digits, spaces, dashes, parentheses)
        // AND check if digit count is between 9 and 13 (covers Slovak and international formats)
        if (!(/^\+?[0-9\s\-()]+$/.test(orderForm.phone)) || (digitsOnly.length != 10 && digitsOnly.length != 12)) {
            errors.value.phone = 'Neplatný formát telefónneho čísla. Zadajte platné číslo, napr. +421234567893 alebo 0902349832.';
            hasError = true;
        }
    }
    if (allTicketsZero.value) {
        errors.value.tickets = 'Musíte si vybrať aspoň jeden lístok.';
        hasError = true;
    }
    if (hasError) {
        readableErrors.value.unshift('Niektoré polia obsahujú chyby. Skontrolujte ich a skúste to znova.');
        return false;
    }
    return true;
};

</script>

<template>
    <OrderLayout :event="props.event" :location="props.location">
        <div class="flex flex-col gap-8">
            <div>
                <EventCard :event="props.event" :location="props.location" />

                <!-- Step Counter -->
                <div class="lg:flex lg:items-center lg:justify-center gap-2 text-center">
                    <template v-for="(label, idx) in stepLabels" :key="label">
                        <div
                            :class="[
                                'rounded-full border-1 px-4 py-2 ',
                                idx + 1 === currentStep
                                    ? 'bg-black text-white dark:bg-white dark:text-black'
                                    : 'dark:text-white',
                                'transition-colors duration-200',
                            ]"
                        >
                            {{ idx + 1 }}. {{ label }}
                        </div>
                        <span
                            v-if="idx < stepLabels.length - 1"
                            class="mx-2 text-gray-400"
                            >→</span
                        >
                    </template>
                </div>
                <div class="mt-4">
                    <Alert class="dark:bg-red-900 bg-red-200 border-red-600" v-for="(error, key) in readableErrors" :key="'error-' + key">
                        <AlertTitle>
                            <i class="fas fa-info-circle mr-2"></i> {{ error }}
                        </AlertTitle>
                    </Alert>
                </div>
            </div>
            <template v-if="currentStep === 1">
                <!-- Step 1: User details and ticket selection -->
                <div>
                    <h2 class="text-3xl font-bold dark:text-white">
                        Vaše údaje
                    </h2>
                    <div>
                        <Form
                            :form="orderForm"
                            @submit="submitOrder"
                        >
                            <div class="mt-4 flex flex-col gap-2">
                                <div>
                                    <Label
                                        class="text-black dark:text-white mt-3"
                                        for="name"
                                        >Meno a priezvisko</Label
                                    >
                                    <Input
                                        id="name"
                                        v-model="orderForm.name"
                                        class="mt-2 dark:text-white"
                                        required
                                        type="text"
                                    />
                                    <InputError
                                        :message="errors.name"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label
                                        class="text-black dark:text-white mt-3"
                                        for="email"
                                        >Email</Label
                                    >
                                    <Input
                                        id="email"
                                        v-model="orderForm.email"
                                        class="mt-2 dark:text-white"
                                        required
                                        type="email"
                                    />
                                    <InputError
                                        :message="errors.email"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label
                                        class="text-black dark:text-white mt-3"
                                        for="phone"
                                        >Telefónne číslo</Label
                                    >
                                    <Input
                                        id="phone"
                                        v-model="orderForm.phone"
                                        class="mt-2 dark:text-white"
                                        required
                                        type="tel"
                                    />
                                    <InputError
                                        :message="errors.phone"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                        </Form>
                    </div>
                </div>

                <div>
                    <h2 class="text-3xl font-bold dark:text-white">
                        Vyberte si lístky
                    </h2>
                    <div class="mt-3 flex flex-col gap-3">
                        <template v-if="Array.isArray(props.tickets) && props.tickets.length">
                            <Card
                                v-for="ticket in props.tickets"
                                v-bind:key="'ticket-' + ticket.id"
                            >
                                <CardContent>
                                    <div class="lg:flex lg:justify-between">
                                        <div class="flex items-center gap-8">
                                            <i class="fas fa-ticket mr-2"></i>
                                            <div class="inline-flex gap-2">
                                                <p class="mb-0">
                                                    {{ ticket.title }}
                                                </p>
                                                <p class="text-md text-gray-500 dark:text-gray-400 mb-0" v-if="event.multiple_reservations_per_ticket">
                                                    {{ ticket.reservations }}
                                                    {{ ticket.reservations == 1 ? 'miesto' : (
                                                        ticket.reservations >= 5 ? 'miest' : 'miesta'
                                                ) }}
                                                </p>
                                            </div>
                                            <span class="font-bold"
                                                >{{ ticket.price }} €</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-2 justify-end">
                                            <button
                                                class="rounded border px-2 py-0 cursor-pointer"
                                                type="button"
                                                @click="decrement(ticket.id)"
                                            >
                                                -
                                            </button>
                                            <input
                                                :value="quantities[ticket.id]"
                                                class="w-12 rounded border text-center"
                                                min="0"
                                                readonly
                                                type="number"
                                            />
                                            <button
                                                class="rounded border px-2 py-0 cursor-pointer"
                                                type="button"
                                                @click="increment(ticket.id)"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </template>
                        <template v-else>
                            <Card class="border-none">
                                <CardContent>
                                    <div>
                                        <i class="fas fa-ticket mr-2"></i>
                                        Momentálne nie sú dostupné žiadne lístky.
                                    </div>
                                </CardContent>
                            </Card>
                        </template>
                    </div>
                </div>

                <div class="flex w-full justify-between">
                    <h2 class="text-2xl font-bold dark:text-white">Spolu</h2>
                    <h2 class="text-2xl font-bold dark:text-white">
                        {{ totalPrice }} &euro;
                    </h2>
                </div>

                <div class="mt-4 flex w-full justify-between">
                    <Button
                        :disabled="currentStep === 1"
                        variant="secondary"
                        @click="prevStep"
                        >Späť
                    </Button>
                    <Button
                        :tabindex="4"
                        class="cursor-pointer"
                        :disabled="allTicketsZero"
                        @click="nextStep"
                    >
                        <span>Pokračovať k výberu miest</span>
                    </Button>
                </div>
            </template>
            <template v-else-if="currentStep === 2">
                <!-- Step 2: Seat selection -->
                <div>
                    <h2 class="text-3xl font-bold dark:text-white mb-2">Výber miest</h2>
                    <p class="text-lg dark:text-white">Zvolených {{ selectedSeats.length }} z {{ maxSelectableSeats }}</p>
                </div>
                <div class="flex w-full items-center justify-center">
                    <SeatSelector
                        v-model:modelValue="selectedSeats"
                        :src="props.event.location"
                        seat-selector="circle.seat"
                        :reserved-seats="props.event.reservations"
                        :seat-names="props.event.seat_names"
                        :max-selected="maxSelectableSeats"
                    />
                </div>
                <div class="mt-4 flex w-full justify-between">
                    <Button
                        variant="secondary"
                        class="cursor-pointer"
                        @click="prevStep"
                        >Späť
                    </Button>
                    <Button
                        :tabindex="4"
                        :disabled="selectedSeats.length !== maxSelectableSeats"
                        class="cursor-pointer"
                        @click="nextStep"
                    >
                        <span>Pokračovať na mená hostí</span>
                    </Button>
                </div>
            </template>
            <template v-else-if="currentStep === 3">
                <!-- Step 3: Guest names for each seat -->
                <div>
                    <h2 class="mb-4 text-3xl font-bold dark:text-white">
                        Mená hostí
                    </h2>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(seat, idx) in selectedSeats"
                            :key="seat"
                            class="flex flex-col gap-3"
                        >
                            <Label
                                :for="'guest-' + seat"
                                class="text-md text-black dark:text-white"
                                >Hosť {{ idx + 1 }} (miesto
                                {{ seat }})</Label
                            >
                            <Input
                                :id="'guest-' + seat"
                                v-model="guestNames[idx]"
                                type="text"
                                placeholder="Meno hosťa"
                                required
                                class="dark:text-white"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex w-full justify-between">
                    <Button
                        variant="secondary"
                        class="cursor-pointer"
                        @click="prevStep"
                        >Späť
                    </Button>
                    <Button
                        class="cursor-pointer"
                        :tabindex="4"
                        :disabled="guestNames.some((name) => !name)"
                        @click="nextStep"
                    >
                        <span>Pokračovať na potvrdenie objednávky</span>
                    </Button>
                </div>
            </template>
            <template v-else-if="currentStep === 4">
                <div class="flex flex-col gap-2">
                    <!-- Step 4: Confirmation -->
                    <h2 class="text-3xl font-bold dark:text-white mb-4">Zhrnutie</h2>
                    <div>
                        <h2 class="mb-4 text-2xl font-bold dark:text-white">
                            Vaše údaje
                        </h2>
                        <p class="dark:text-white">
                            <strong>Meno:</strong> {{ orderForm.name }}<br />
                            <strong>Email:</strong> {{ orderForm.email }}<br />
                            <strong>Telefón:</strong> {{ orderForm.phone }}
                        </p>
                        <h2 class="mb-4 mt-4 text-2xl font-bold dark:text-white">
                            Vybrané lístky
                        </h2>
                        <div
                            v-for="ticket in filteredTickets"
                            :key="'summary-ticket-' + ticket.id"
                            class="dark:text-white"
                        >
                            {{ ticket.title }} x {{ quantities[ticket.id] }} -
                            {{
                                (quantities[ticket.id] * ticket.price).toFixed(2)
                            }}
                            &euro;
                        </div>
                        <h2 class="mb-4 mt-4 text-2xl font-bold dark:text-white">
                            Vybrané miesta
                        </h2>
                        <ul class="list-disc pl-5 dark:text-white">
                            <li
                                v-for="(seat, idx) in selectedSeats"
                                :key="'summary-seat-' + seat"
                            >
                                Miesto {{ seat }} - {{ guestNames[idx] }}
                            </li>
                        </ul>
                    </div>
                    <div class="flex w-full justify-between">
                        <h2 class="text-2xl font-bold dark:text-white">Spolu</h2>
                        <h2 class="text-2xl font-bold dark:text-white">
                            {{ totalPrice }} &euro;
                        </h2>
                    </div>
                    <div class="mt-4">
                        <Alert class="">
                            <AlertTitle>
                                <i class="fas fa-info-circle mr-2"></i> Po objednaní dostanete e-mailom potvrdenie s detailami vašej objednávky a podrobnostiami o platbe.
                            </AlertTitle>
                            <AlertDescription>
                                Úhrada objednávky prebieha výlučne bankovým prevodom.
                            </AlertDescription>
                        </Alert>
                    </div>
                    <div class="mt-4 flex w-full justify-between">
                        <Button
                            variant="secondary"
                            class="cursor-pointer"
                            @click="prevStep"
                        >Späť
                        </Button>
                        <Button
                            class="cursor-pointer"
                            :tabindex="4"
                            :disabled="false"
                            @click="submitOrder"
                        >
                            <span>Objednať</span>
                        </Button>
                    </div>
                </div>
            </template>
        </div>
    </OrderLayout>
</template>

<style>
/* Hide number input arrows for Chrome, Safari, Edge */
input[type='number']::-webkit-inner-spin-button,
input[type='number']::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Hide number input arrows for Firefox */
input[type='number'] {
    appearance: textfield;
    -moz-appearance: textfield;
}
</style>
