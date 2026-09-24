<script setup lang="ts">
import TableEditor from '@/components/event/TableEditor.vue';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';
import { useWizard } from './context';

const { form, error, selectedLocation } = useWizard();

// Show both server errors (tables.0.seats) and client errors (tables.0).
const tableErrors = computed(() => {
    const errors: Record<string, string> = {};
    Object.entries(form.errors as Record<string, string>).forEach(
        ([key, message]) => {
            if (key.startsWith('tables.')) errors[key] = message;
        },
    );
    return errors;
});
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="!selectedLocation?.svg_map"
            class="text-sm text-muted-foreground"
        >
            Vybraná lokalita nemá mapu sedenia, miesta stolov zadajte číslami.
        </p>
        <TableEditor
            v-model:enabled="form.tables_enabled"
            v-model:tables="form.tables"
            :svg-map="selectedLocation?.svg_map"
            :valid-seats="selectedLocation?.valid_seats"
            :errors="tableErrors"
        />
        <InputError :message="error('tables')" />
    </div>
</template>
