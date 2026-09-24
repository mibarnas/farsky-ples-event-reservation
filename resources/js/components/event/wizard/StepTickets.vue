<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InfoIcon, PlusIcon, Trash2Icon } from 'lucide-vue-next';
import { useWizard } from './context';

const { form, error } = useWizard();

function addTicket() {
    form.tickets = [...form.tickets, { title: '', price: '', reservations: 1 }];
}

function removeTicket(index: number) {
    form.tickets = form.tickets.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Typy vstupeniek, ktoré si hostia môžu objednať. Ďalšie lístky môžete
            pridať alebo upraviť aj neskôr v správe podujatia.
        </p>

        <div
            v-for="(ticket, index) in form.tickets"
            :key="index"
            class="grid items-start gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_8rem_8rem_auto]"
        >
            <div class="space-y-1">
                <Label :for="`ticket-title-${index}`">Názov *</Label>
                <Input
                    :id="`ticket-title-${index}`"
                    v-model="ticket.title"
                    placeholder="napr. Vstupenka"
                />
                <InputError :message="error(`tickets.${index}.title`)" />
            </div>
            <div class="space-y-1">
                <Label :for="`ticket-price-${index}`">Cena (€) *</Label>
                <Input
                    :id="`ticket-price-${index}`"
                    v-model="ticket.price"
                    type="number"
                    min="0"
                    step="any"
                />
                <InputError :message="error(`tickets.${index}.price`)" />
            </div>
            <div class="space-y-1">
                <Label :for="`ticket-res-${index}`">Počet miest *</Label>
                <Input
                    :id="`ticket-res-${index}`"
                    v-model="ticket.reservations"
                    type="number"
                    min="1"
                    step="1"
                    :disabled="!form.multiple_reservations_per_ticket"
                />
                <InputError :message="error(`tickets.${index}.reservations`)" />
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="text-red-600 sm:mt-6"
                :aria-label="`Odstrániť lístok ${ticket.title}`"
                @click="removeTicket(index)"
            >
                <Trash2Icon class="size-4" />
            </Button>
        </div>

        <p
            v-if="!form.multiple_reservations_per_ticket"
            class="text-xs text-muted-foreground"
        >
            Každý lístok platí pre jedno miesto. Viac miest na lístok povolíte v
            kroku Miesto.
        </p>

        <Button type="button" variant="outline" @click="addTicket">
            <PlusIcon class="size-4" /> Pridať typ lístka
        </Button>

        <p
            v-if="!form.tickets.length"
            class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400"
        >
            <InfoIcon class="mt-0.5 size-4 shrink-0" />
            Bez lístkov si hostia nebudú môcť nič objednať. Môžete ich pridať aj
            neskôr.
        </p>
    </div>
</template>
