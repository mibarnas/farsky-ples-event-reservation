<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import EventLayout from '@/layouts/EventLayout.vue';
import { create } from "@/routes/order";
import { Link, usePage } from '@inertiajs/vue3';
import { Alert, AlertTitle } from '@/components/ui/alert';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { marked } from 'marked';

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
  multiple_reservations_per_ticket: boolean;
  description?: string;
  background_image_path?: string;
  logo_image_path?: string;
  overline?: string;
}

interface Location {
  id: number;
  name: string;
  address: string;
}

const props = defineProps<{ event: Event; tickets: Ticket[]; location: Location, places_left: number }>();

const page = usePage();
const flashError = computed(() => (page.props as any).flash?.error as string | undefined);

const formattedStartTime = computed(() => {
  if (!props.event.start_time) return '';
  const date = new Date(props.event.start_time);
  return date.toLocaleString('sk-SK', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
});

const formattedLocation = computed(() => {
  if (!props.location.address) return '';
  return props.location.address.replace(/\r?\\n|\r/g, ', ');
});

// Ticks every second so the countdown updates live and the buy button
// unlocks as soon as registration opens. Only started on the client (SSR-safe).
const now = ref(new Date());
let nowTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  now.value = new Date();
  nowTimer = setInterval(() => {
    now.value = new Date();
  }, 1000);
});
onUnmounted(() => {
  if (nowTimer) clearInterval(nowTimer);
});

const formatDateTime = (value?: string) => {
  if (!value) return '';
  return new Date(value).toLocaleString('sk-SK', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formattedRegistrationStart = computed(() => formatDateTime(props.event.registration_start));
const formattedRegistrationEnd = computed(() => formatDateTime(props.event.registration_end));

const isWithinRegistrationWindow = computed(() => {
  if (props.event.registration_start) {
    const startDate = new Date(props.event.registration_start);
    if (now.value < startDate) return false;
  }

  if (props.event.registration_end) {
    const endDate = new Date(props.event.registration_end);
    if (now.value > endDate) return false;
  }

  if (props.event.start_time && now.value >= new Date(props.event.start_time)) return false;

  return true;
});

const registrationNotStarted = computed(() => {
  if (!props.event.registration_start) return false;
  const startDate = new Date(props.event.registration_start);
  return now.value < startDate;
});

const registrationEnded = computed(() => {
  if (props.event.start_time && now.value >= new Date(props.event.start_time)) return true;
  if (!props.event.registration_end) return false;
  const endDate = new Date(props.event.registration_end);
  return now.value > endDate;
});

const countdown = computed(() => {
  if (!registrationNotStarted.value || !props.event.registration_start) return null;
  const diff = Math.max(0, new Date(props.event.registration_start).getTime() - now.value.getTime());
  const totalSeconds = Math.floor(diff / 1000);
  return [
    { value: Math.floor(totalSeconds / 86400), label: 'dní' },
    { value: Math.floor((totalSeconds % 86400) / 3600), label: 'hodín' },
    { value: Math.floor((totalSeconds % 3600) / 60), label: 'minút' },
    { value: totalSeconds % 60, label: 'sekúnd' },
  ];
});

const isSoldOut = computed(() => {
  return props.places_left <= 0;
});

const canPurchase = computed(() => {
  return isWithinRegistrationWindow.value && !isSoldOut.value && props.tickets.length > 0;
});

const buttonText = computed(() => {
  if (registrationNotStarted.value) {
    return 'Predaj ešte nezačal';
  }
  if (registrationEnded.value) {
    return 'Predaj lístkov sa skončil';
  }
  if (isSoldOut.value) {
    return 'Vypredané';
  }
  if (props.tickets.length === 0) {
    return 'Žiadne dostupné lístky';
  }
  return 'Kúpiť lístok na toto podujatie';
});

const renderedDescription = computed(() => {
  if (!props.event.description) return '';
  return marked.parse(props.event.description, { async: false }) as string;
});

const backgroundImageUrl = computed(() => {
  if (!props.event.background_image_path) return null;
  return `/storage/${props.event.background_image_path}`;
});
</script>

<template>
    <EventLayout :background="backgroundImageUrl ?? undefined">
        <Alert v-if="flashError" class="mb-6 dark:bg-red-900 bg-red-200 border-red-600">
            <AlertTitle>
                <i class="fas fa-info-circle mr-2"></i> {{ flashError }}
            </AlertTitle>
        </Alert>
        <div class="flex flex-col gap-8">
            <div v-if="props.event.logo_image_path != '' && props.event.logo_image_path != null" class="flex flex-col items-center">
                <img
                    :src="`/storage/${props.event.logo_image_path}`"
                    alt="Event Logo"
                    class="mb-4 max-h-48 object-contain"
                />
                <p class="dark:text-gray-200 font-bold mt-3">
                    <span><i class="fas fa-calendar-alt mr-2"></i>{{ formattedStartTime }}</span>
                    <span class="ml-4"><i class="fas fa-map-marker-alt mr-2"></i>{{ formattedLocation }}</span>
                </p>
            </div>
            <div v-else-if="props.event.overline != '' && props.event.overline != null" class="text-center">
                <p class="text-2xl dark:text-white font-medium mb-2">{{ props.event.overline }}</p>
                <h1 class="dark:text-white text-6xl font-extrabold">{{ props.event.title }}</h1>
                <p class="dark:text-gray-200 font-bold mt-3 text-lg">
                    <span><i class="fas fa-calendar-alt mr-2"></i>{{ formattedStartTime }}</span>
                    <span class="ml-4"><i class="fas fa-map-marker-alt mr-2"></i>{{ formattedLocation }}</span>
                </p>
            </div>
            <div v-else>
                <h1 class="dark:text-white text-6xl font-extrabold">{{ props.event.title }}</h1>
                <p class="dark:text-gray-200 font-bold mt-3">
                    <span><i class="fas fa-calendar-alt mr-2"></i>{{ formattedStartTime }}</span>
                    <span class="ml-4"><i class="fas fa-map-marker-alt mr-2"></i>{{ formattedLocation }}</span>
                </p>
            </div>

            <div>
                <div
                    v-html="renderedDescription"
                    class="dark:text-gray-200 markdown-content"
                ></div>
            </div>

            <div>
                <h2 class="dark:text-white text-3xl font-bold">Dostupné lístky</h2>
                <div class="flex flex-col gap-3 mt-3">
                    <Card v-for="ticket in props.tickets" v-bind:key="'ticket-' + ticket.id">
                        <CardContent>
                            <div class="flex justify-between">
                                <div>
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
                                </div>
                                <div>
                                    {{ ticket.price }} €
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <div v-if="props.tickets.length == 0">
                        <Card class="border-none">
                            <CardContent>
                                <div>
                                    <i class="fas fa-ticket mr-2"></i>
                                    Momentálne nie sú dostupné žiadne lístky.
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="countdown" class="text-center mt-8">
            <p class="dark:text-gray-200 font-bold mb-3">Registrácia začne o</p>
            <div class="flex justify-center gap-3">
                <div
                    v-for="part in countdown"
                    :key="part.label"
                    class="flex flex-col items-center min-w-16 rounded-lg bg-white/70 dark:bg-black/40 px-3 py-2"
                >
                    <span class="text-3xl font-extrabold tabular-nums dark:text-white">
                        {{ String(part.value).padStart(2, '0') }}
                    </span>
                    <span class="text-xs uppercase text-gray-600 dark:text-gray-300">{{ part.label }}</span>
                </div>
            </div>
        </div>

        <div v-else class="flex w-full justify-center mt-8">
            <Button
                :class="'mt-4 ' + (!canPurchase ? ' disabled opacity-50  cursor-default' : ' cusror-pointer')"
                :tabindex="4"
                :disabled="!canPurchase"
                :href="canPurchase ? create.url(props.event.url_slug) : ''"
                :as="Link"
            >
                {{ buttonText }}
            </Button>
        </div>

        <p
            v-if="formattedRegistrationStart || formattedRegistrationEnd"
            class="mt-6 text-sm text-center dark:text-gray-200"
        >
            <i class="fas fa-clock mr-2"></i>
            Registrácia:
            <span v-if="formattedRegistrationStart">od <strong>{{ formattedRegistrationStart }}</strong></span>
            <span v-if="formattedRegistrationEnd"> do <strong>{{ formattedRegistrationEnd }}</strong></span>
        </p>
    </EventLayout>
</template>

<style scoped>
</style>
