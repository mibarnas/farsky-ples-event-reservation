<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { slugify, useWizard } from './context';

const { form, error, slugTouched } = useWizard();

const origin = typeof window !== 'undefined' ? window.location.origin : '';

function onTitle(value: string | number) {
    form.title = String(value);
    if (!slugTouched.value) form.url_slug = slugify(form.title);
}

function onSlug(value: string | number) {
    slugTouched.value = true;
    form.url_slug = String(value);
}

function resetSlug() {
    slugTouched.value = false;
    form.url_slug = slugify(form.title);
}
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-2">
            <Label for="title">Názov podujatia *</Label>
            <Input
                id="title"
                :model-value="form.title"
                placeholder="napr. Farský ples 2027"
                autofocus
                :aria-invalid="!!error('title')"
                @update:model-value="onTitle"
            />
            <InputError :message="error('title')" />
        </div>

        <div class="space-y-2">
            <Label for="overline">Nadpis nad názvom</Label>
            <Input
                id="overline"
                v-model="form.overline"
                placeholder="napr. Farnosť Vajnory pozýva na"
            />
            <p class="text-xs text-muted-foreground">
                Voliteľný menší text, ktorý sa zobrazí nad názvom podujatia.
            </p>
            <InputError :message="error('overline')" />
        </div>

        <div class="space-y-2">
            <Label for="url_slug">Adresa stránky *</Label>
            <div class="flex items-center gap-2">
                <span
                    class="hidden shrink-0 text-sm text-muted-foreground sm:inline"
                    >{{ origin }}/event/</span
                >
                <Input
                    id="url_slug"
                    :model-value="form.url_slug"
                    placeholder="farsky-ples-2027"
                    :aria-invalid="!!error('url_slug')"
                    @update:model-value="onSlug"
                />
            </div>
            <p class="text-xs text-muted-foreground">
                <template v-if="slugTouched">
                    Adresu ste upravili ručne.
                    <button
                        type="button"
                        class="underline hover:text-foreground"
                        @click="resetSlug"
                    >
                        Vytvoriť znova z názvu
                    </button>
                </template>
                <template v-else>
                    Vytvára sa automaticky z názvu. Môžete ju upraviť.
                </template>
            </p>
            <InputError :message="error('url_slug')" />
        </div>

        <div class="space-y-2">
            <Label for="description">Popis</Label>
            <Textarea
                id="description"
                v-model="form.description"
                rows="6"
                show-markdown
            />
            <p class="text-xs text-muted-foreground">
                Zobrazí sa na stránke podujatia. Podporuje Markdown (tučné
                písmo, zoznamy, odkazy).
            </p>
            <InputError :message="error('description')" />
        </div>
    </div>
</template>
