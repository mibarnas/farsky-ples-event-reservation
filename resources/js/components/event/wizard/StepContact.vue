<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePage } from '@inertiajs/vue3';
import { useWizard } from './context';

const { form, error } = useWizard();
const page = usePage();

// Most organizers are their own contact person: offer to prefill from the account.
function useMyDetails() {
    const user = (page.props as any).auth?.user;
    if (!user) return;
    form.contact_name = user.name;
    form.contact_email = user.email;
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    Zobrazí sa hosťom v potvrdení objednávky pre prípad otázok.
                </p>
                <button
                    type="button"
                    class="shrink-0 text-sm text-blue-600 underline"
                    @click="useMyDetails"
                >
                    Použiť moje údaje
                </button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="contact_name">Meno *</Label>
                    <Input
                        id="contact_name"
                        v-model="form.contact_name"
                        autocomplete="name"
                        :aria-invalid="!!error('contact_name')"
                    />
                    <InputError :message="error('contact_name')" />
                </div>
                <div class="space-y-2">
                    <Label for="contact_email">Email *</Label>
                    <Input
                        id="contact_email"
                        v-model="form.contact_email"
                        type="email"
                        autocomplete="email"
                        :aria-invalid="!!error('contact_email')"
                    />
                    <InputError :message="error('contact_email')" />
                </div>
                <div class="space-y-2">
                    <Label for="contact_phone">Telefón</Label>
                    <Input
                        id="contact_phone"
                        v-model="form.contact_phone"
                        type="tel"
                        autocomplete="tel"
                    />
                    <InputError :message="error('contact_phone')" />
                </div>
            </div>
        </div>

        <div class="space-y-2 rounded-lg border p-4">
            <Label for="bank_account">Bankový účet (IBAN) *</Label>
            <Input
                id="bank_account"
                v-model="form.bank_account"
                placeholder="SK00 0000 0000 0000 0000 0000"
                class="font-mono"
                :aria-invalid="!!error('bank_account')"
            />
            <p class="text-xs text-muted-foreground">
                Na tento účet budú hostia posielať platby za lístky.
            </p>
            <InputError :message="error('bank_account')" />
        </div>
    </div>
</template>
