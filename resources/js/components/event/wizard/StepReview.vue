<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatSeatRanges } from '@/lib/seatRanges';
import { PencilIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { STEPS, useWizard, type StepKey } from './context';

const { form, selectedLocation, goTo } = useWizard();

const formatDate = (value: string) =>
    value
        ? new Date(value).toLocaleString('sk-SK', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

const formatPrice = (value: string | number) =>
    new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value) || 0);

const stepIndex = (key: StepKey) => STEPS.findIndex((s) => s.key === key);

const sections = computed(() => [
    {
        key: 'basics' as StepKey,
        rows: [
            ['Názov', form.title],
            ['Nadpis nad názvom', form.overline || '—'],
            ['Adresa stránky', `/event/${form.url_slug}`],
            [
                'Popis',
                form.description
                    ? `${form.description.slice(0, 120)}${form.description.length > 120 ? '…' : ''}`
                    : '—',
            ],
        ],
    },
    {
        key: 'schedule' as StepKey,
        rows: [
            ['Začiatok podujatia', formatDate(form.start_time)],
            [
                'Registrácia',
                `${formatDate(form.registration_start)} – ${formatDate(form.registration_end)}`,
            ],
        ],
    },
    {
        key: 'venue' as StepKey,
        rows: [
            ['Lokalita', selectedLocation.value?.address ?? '—'],
            [
                'Počet miest',
                String(
                    form.seats_total ||
                        selectedLocation.value?.places_total ||
                        '—',
                ),
            ],
            [
                'Viac miest na lístok',
                form.multiple_reservations_per_ticket ? 'Áno' : 'Nie',
            ],
        ],
    },
    {
        key: 'contact' as StepKey,
        rows: [
            [
                'Kontakt',
                [form.contact_name, form.contact_email, form.contact_phone]
                    .filter(Boolean)
                    .join(' · '),
            ],
            ['Bankový účet', form.bank_account],
        ],
    },
    {
        key: 'appearance' as StepKey,
        rows: [
            ['Obrázok na pozadí', form.background_image?.name ?? 'predvolený'],
            ['Logo', form.logo?.name ?? 'bez loga'],
        ],
    },
]);
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Skontrolujte údaje. Všetko okrem lokality môžete neskôr zmeniť v
            úprave podujatia.
        </p>

        <template v-for="section in sections" :key="section.key">
            <section class="rounded-lg border p-4">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="font-semibold">
                        {{ STEPS[stepIndex(section.key)].title }}
                    </h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="goTo(stepIndex(section.key))"
                    >
                        <PencilIcon class="size-3.5" /> Upraviť
                    </Button>
                </div>
                <dl
                    class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[12rem_1fr]"
                >
                    <template
                        v-for="[label, value] in section.rows"
                        :key="label"
                    >
                        <dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="break-words">{{ value }}</dd>
                    </template>
                </dl>
            </section>

            <!-- Tables and tickets are lists, so they get their own layout after the venue. -->
            <template v-if="section.key === 'venue'">
                <section class="rounded-lg border p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold">Stoly</h3>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="goTo(stepIndex('tables'))"
                        >
                            <PencilIcon class="size-3.5" /> Upraviť
                        </Button>
                    </div>
                    <p
                        v-if="!form.tables_enabled"
                        class="text-sm text-muted-foreground"
                    >
                        Bez stolov
                    </p>
                    <ul v-else class="grid gap-1 text-sm sm:grid-cols-2">
                        <li v-for="table in form.tables" :key="table.name">
                            <span class="font-medium">{{ table.name }}</span>
                            <span class="text-muted-foreground">
                                · {{ table.seats.length }} miest ({{
                                    formatSeatRanges(table.seats)
                                }})
                            </span>
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg border p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold">Lístky</h3>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="goTo(stepIndex('tickets'))"
                        >
                            <PencilIcon class="size-3.5" /> Upraviť
                        </Button>
                    </div>
                    <p
                        v-if="!form.tickets.length"
                        class="text-sm text-amber-700 dark:text-amber-400"
                    >
                        Žiadne lístky. Pridáte ich neskôr v správe podujatia.
                    </p>
                    <ul v-else class="space-y-1 text-sm">
                        <li
                            v-for="(ticket, i) in form.tickets"
                            :key="i"
                            class="flex justify-between gap-4"
                        >
                            <span>
                                {{ ticket.title }}
                                <span
                                    v-if="Number(ticket.reservations) > 1"
                                    class="text-muted-foreground"
                                >
                                    ({{ ticket.reservations }} miesta)
                                </span>
                            </span>
                            <span class="font-medium">{{
                                formatPrice(ticket.price)
                            }}</span>
                        </li>
                    </ul>
                </section>
            </template>
        </template>
    </div>
</template>
