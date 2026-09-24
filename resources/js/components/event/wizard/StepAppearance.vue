<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useObjectUrl } from '@vueuse/core';
import { ImageIcon, XIcon } from 'lucide-vue-next';
import { ref, toRef } from 'vue';
import { useWizard } from './context';

const { form, error } = useWizard();

const backgroundUrl = useObjectUrl(toRef(form, 'background_image'));
const logoUrl = useObjectUrl(toRef(form, 'logo'));

const backgroundInput = ref<HTMLInputElement | null>(null);
const logoInput = ref<HTMLInputElement | null>(null);

function pick(field: 'background_image' | 'logo', e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) form[field] = file;
}

function clear(field: 'background_image' | 'logo') {
    form[field] = null;
    const input = field === 'logo' ? logoInput.value : backgroundInput.value;
    if (input) input.value = '';
}
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-muted-foreground">
            Obrázky sú voliteľné. Bez nich sa použije predvolené pozadie.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="background_image">Obrázok na pozadí</Label>
                <div
                    class="relative flex aspect-video items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30"
                >
                    <img
                        v-if="backgroundUrl"
                        :src="backgroundUrl"
                        alt="Náhľad pozadia"
                        class="size-full object-cover"
                    />
                    <ImageIcon v-else class="size-10 text-muted-foreground" />
                    <Button
                        v-if="form.background_image"
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="absolute top-2 right-2 size-8"
                        aria-label="Odstrániť obrázok"
                        @click="clear('background_image')"
                    >
                        <XIcon class="size-4" />
                    </Button>
                </div>
                <input
                    id="background_image"
                    ref="backgroundInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm"
                    @change="pick('background_image', $event)"
                />
                <p class="text-xs text-muted-foreground">
                    JPEG, PNG alebo WEBP, najviac 5 MB. Ideálne na šírku.
                </p>
                <InputError :message="error('background_image')" />
            </div>

            <div class="space-y-2">
                <Label for="logo">Logo</Label>
                <div
                    class="relative flex aspect-video items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30"
                >
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Náhľad loga"
                        class="max-h-full max-w-full object-contain p-4"
                    />
                    <ImageIcon v-else class="size-10 text-muted-foreground" />
                    <Button
                        v-if="form.logo"
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="absolute top-2 right-2 size-8"
                        aria-label="Odstrániť logo"
                        @click="clear('logo')"
                    >
                        <XIcon class="size-4" />
                    </Button>
                </div>
                <input
                    id="logo"
                    ref="logoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/svg+xml"
                    class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm"
                    @change="pick('logo', $event)"
                />
                <p class="text-xs text-muted-foreground">
                    JPEG, PNG, WEBP alebo SVG, najviac 2 MB.
                </p>
                <InputError :message="error('logo')" />
            </div>
        </div>
    </div>
</template>
