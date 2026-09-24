<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CheckCircle2Icon, MapPinIcon } from 'lucide-vue-next';
import { useWizard } from './context';

const { form, error, locations, selectedLocation } = useWizard();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label>Lokalita *</Label>
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="location in locations"
                    :key="location.id"
                    type="button"
                    class="flex items-start gap-3 rounded-lg border p-4 text-left transition-colors"
                    :class="
                        String(form.location_id) === String(location.id)
                            ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:bg-blue-950/30'
                            : 'hover:bg-muted/50'
                    "
                    @click="form.location_id = location.id"
                >
                    <MapPinIcon
                        class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    />
                    <span class="flex-1">
                        <span class="block font-medium">{{
                            location.address
                        }}</span>
                        <span class="block text-sm text-muted-foreground">
                            Kapacita {{ location.places_total }} miest
                            <template v-if="location.svg_map"
                                >· s mapou sedenia</template
                            >
                        </span>
                    </span>
                    <CheckCircle2Icon
                        v-if="String(form.location_id) === String(location.id)"
                        class="size-5 shrink-0 text-blue-600"
                    />
                </button>
            </div>
            <p v-if="!locations.length" class="text-sm text-muted-foreground">
                Zatiaľ nie je vytvorená žiadna lokalita. Kontaktujte
                administrátora.
            </p>
            <InputError :message="error('location_id')" />
        </div>

        <div class="space-y-2">
            <Label for="seats_total">Počet miest na predaj</Label>
            <Input
                id="seats_total"
                v-model="form.seats_total"
                type="number"
                min="1"
                class="max-w-xs"
                :placeholder="
                    selectedLocation
                        ? `${selectedLocation.places_total} (podľa lokality)`
                        : 'Podľa lokality'
                "
                :aria-invalid="!!error('seats_total')"
            />
            <p class="text-xs text-muted-foreground">
                Nechajte prázdne a použije sa kapacita lokality. Nižšie číslo
                zadajte, ak nechcete predať všetky miesta.
            </p>
            <InputError :message="error('seats_total')" />
        </div>

        <label
            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
        >
            <input
                v-model="form.multiple_reservations_per_ticket"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-300"
            />
            <span>
                <span class="block font-medium">
                    Viac miest na jeden lístok
                </span>
                <span class="block text-sm text-muted-foreground">
                    Zapnite, ak niektoré lístky platia pre viac osôb (napr.
                    „Pár“ alebo „Rodinný lístok“). Počet miest pre každý lístok
                    nastavíte v kroku Lístky.
                </span>
            </span>
        </label>
    </div>
</template>
