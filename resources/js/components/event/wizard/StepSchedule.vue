<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InfoIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useWizard } from './context';

const { form, error } = useWizard();

const endsAfterStart = computed(
    () =>
        !!form.start_time &&
        !!form.registration_end &&
        form.registration_end > form.start_time,
);

// Offer a sensible default: registration closes when the event starts.
function closeAtStart() {
    form.registration_end = form.start_time;
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label for="start_time">Začiatok podujatia *</Label>
            <Input
                id="start_time"
                v-model="form.start_time"
                type="datetime-local"
                class="max-w-xs"
                :aria-invalid="!!error('start_time')"
            />
            <InputError :message="error('start_time')" />
        </div>

        <div class="rounded-lg border p-4">
            <h3 class="font-medium">Registrácia</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                V tomto období si hostia môžu objednávať lístky na stránke
                podujatia.
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="registration_start">Otvorenie *</Label>
                    <Input
                        id="registration_start"
                        v-model="form.registration_start"
                        type="datetime-local"
                        :aria-invalid="!!error('registration_start')"
                    />
                    <InputError :message="error('registration_start')" />
                </div>
                <div class="space-y-2">
                    <Label for="registration_end">Uzavretie *</Label>
                    <Input
                        id="registration_end"
                        v-model="form.registration_end"
                        type="datetime-local"
                        :aria-invalid="!!error('registration_end')"
                    />
                    <button
                        v-if="form.start_time && !form.registration_end"
                        type="button"
                        class="text-xs text-blue-600 underline"
                        @click="closeAtStart"
                    >
                        Uzavrieť pri začiatku podujatia
                    </button>
                    <InputError :message="error('registration_end')" />
                </div>
            </div>
        </div>

        <p
            v-if="endsAfterStart"
            class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400"
        >
            <InfoIcon class="mt-0.5 size-4 shrink-0" />
            Registrácia končí až po začiatku podujatia. Objednávky sa aj tak
            prestanú prijímať v momente, keď podujatie začne.
        </p>
    </div>
</template>
