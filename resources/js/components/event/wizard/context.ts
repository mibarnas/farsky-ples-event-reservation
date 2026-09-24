import { validateTables, type EventTable } from '@/lib/seatRanges';
import type { InertiaForm } from '@inertiajs/vue3';
import { inject, type InjectionKey, type Ref } from 'vue';

export interface WizardLocation {
    id: number;
    address: string;
    places_total: number;
    svg_map: string | null;
    valid_seats: number[] | null;
}

export interface WizardTicket {
    title: string;
    price: string | number;
    reservations: string | number;
}

export interface WizardFormData {
    title: string;
    overline: string;
    url_slug: string;
    description: string;
    start_time: string;
    registration_start: string;
    registration_end: string;
    location_id: string | number;
    seats_total: string | number;
    multiple_reservations_per_ticket: boolean;
    tables_enabled: boolean;
    tables: EventTable[];
    tickets: WizardTicket[];
    contact_name: string;
    contact_email: string;
    contact_phone: string;
    bank_account: string;
    background_image: File | null;
    logo: File | null;
}

export type WizardForm = InertiaForm<WizardFormData>;

export type StepKey =
    | 'basics'
    | 'schedule'
    | 'venue'
    | 'tables'
    | 'tickets'
    | 'contact'
    | 'appearance'
    | 'review';

export interface WizardStep {
    key: StepKey;
    title: string;
    description: string;
    // Form fields (and error-key prefixes) that belong to this step.
    fields: string[];
}

export const STEPS: WizardStep[] = [
    {
        key: 'basics',
        title: 'Základné info',
        description: 'Názov, adresa stránky a popis',
        fields: ['title', 'overline', 'url_slug', 'description'],
    },
    {
        key: 'schedule',
        title: 'Termíny',
        description: 'Kedy podujatie začína a kedy sa dá registrovať',
        fields: ['start_time', 'registration_start', 'registration_end'],
    },
    {
        key: 'venue',
        title: 'Miesto',
        description: 'Lokalita, kapacita a rezervácie',
        fields: [
            'location_id',
            'seats_total',
            'multiple_reservations_per_ticket',
        ],
    },
    {
        key: 'tables',
        title: 'Stoly',
        description: 'Názvy stolov a ich miesta (voliteľné)',
        fields: ['tables_enabled', 'tables'],
    },
    {
        key: 'tickets',
        title: 'Lístky',
        description: 'Typy vstupeniek a ich ceny',
        fields: ['tickets'],
    },
    {
        key: 'contact',
        title: 'Kontakt a platba',
        description: 'Kto je kontaktná osoba a kam sa platí',
        fields: [
            'contact_name',
            'contact_email',
            'contact_phone',
            'bank_account',
        ],
    },
    {
        key: 'appearance',
        title: 'Vzhľad',
        description: 'Obrázok na pozadí a logo (voliteľné)',
        fields: ['background_image', 'logo'],
    },
    {
        key: 'review',
        title: 'Zhrnutie',
        description: 'Skontrolujte údaje a vytvorte podujatie',
        fields: [],
    },
];

/** Index of the step an error key (e.g. "tickets.0.price") belongs to, or -1. */
export function stepIndexForField(key: string): number {
    return STEPS.findIndex((step) =>
        step.fields.some(
            (field) => key === field || key.startsWith(`${field}.`),
        ),
    );
}

export function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** Client-side checks for one step; returns messages keyed by form field. */
export function validateStep(
    key: StepKey,
    form: WizardFormData,
    locations: WizardLocation[],
): Record<string, string> {
    const errors: Record<string, string> = {};
    const required = (field: keyof WizardFormData, message: string) => {
        if (!String(form[field] ?? '').trim()) errors[field] = message;
    };

    switch (key) {
        case 'basics':
            required('title', 'Zadajte názov podujatia.');
            required('url_slug', 'Zadajte adresu stránky.');
            if (form.url_slug && !SLUG.test(form.url_slug))
                errors.url_slug =
                    'Použite len malé písmená bez diakritiky, číslice a pomlčky.';
            break;

        case 'schedule':
            required('start_time', 'Zadajte začiatok podujatia.');
            required('registration_start', 'Zadajte začiatok registrácie.');
            required('registration_end', 'Zadajte koniec registrácie.');
            if (
                form.registration_start &&
                form.registration_end &&
                form.registration_end <= form.registration_start
            )
                errors.registration_end =
                    'Koniec registrácie musí byť po jej začiatku.';
            break;

        case 'venue':
            required('location_id', 'Vyberte lokalitu.');
            if (form.seats_total !== '' && Number(form.seats_total) < 1)
                errors.seats_total = 'Počet miest musí byť aspoň 1.';
            break;

        case 'tables': {
            if (!form.tables_enabled) break;
            if (!form.tables.length) {
                errors.tables =
                    'Pridajte aspoň jeden stôl alebo vypnite možnosť „Podujatie má stoly“.';
                break;
            }
            const location = locations.find(
                (l) => String(l.id) === String(form.location_id),
            );
            const problems = validateTables(form.tables, location?.valid_seats);
            Object.entries(problems).forEach(([index, messages]) => {
                errors[`tables.${index}`] = messages[0];
            });
            break;
        }

        case 'tickets':
            form.tickets.forEach((ticket, i) => {
                if (!String(ticket.title).trim())
                    errors[`tickets.${i}.title`] = 'Zadajte názov lístka.';
                if (ticket.price === '' || Number(ticket.price) < 0)
                    errors[`tickets.${i}.price`] =
                        'Zadajte cenu (0 alebo viac).';
                if (
                    !Number.isInteger(Number(ticket.reservations)) ||
                    Number(ticket.reservations) < 1
                )
                    errors[`tickets.${i}.reservations`] =
                        'Zadajte celé číslo, aspoň 1.';
            });
            break;

        case 'contact':
            required('contact_name', 'Zadajte meno kontaktnej osoby.');
            required('contact_email', 'Zadajte kontaktný email.');
            if (form.contact_email && !EMAIL.test(form.contact_email))
                errors.contact_email = 'Email nemá správny formát.';
            required('bank_account', 'Zadajte číslo účtu (IBAN).');
            break;

        case 'appearance':
            if (
                form.background_image &&
                form.background_image.size > 5 * 1024 * 1024
            )
                errors.background_image = 'Obrázok môže mať najviac 5 MB.';
            if (form.logo && form.logo.size > 2 * 1024 * 1024)
                errors.logo = 'Logo môže mať najviac 2 MB.';
            break;
    }

    return errors;
}

export interface WizardContext {
    form: WizardForm;
    locations: WizardLocation[];
    selectedLocation: Ref<WizardLocation | null>;
    // Errors from the client-side step check, shown until the field is fixed.
    clientErrors: Ref<Record<string, string>>;
    slugTouched: Ref<boolean>;
    goTo: (index: number) => void;
}

export const wizardKey: InjectionKey<WizardContext> = Symbol('event-wizard');

export function useWizard(): WizardContext & {
    error: (field: string) => string | undefined;
} {
    const context = inject(wizardKey);
    if (!context)
        throw new Error('useWizard() must be used inside the event wizard');

    const error = (field: string) =>
        context.clientErrors.value[field] ??
        (context.form.errors as Record<string, string>)[field];

    return { ...context, error };
}
