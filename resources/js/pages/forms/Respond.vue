<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicFormController from '@/actions/App/Http/Controllers/Forms/PublicFormController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

type FieldShape = {
    key: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'number'
        | 'date'
        | 'select'
        | 'checkboxes'
        | 'toggle'
        | 'email'
        | 'name'
        | 'phone';
    required: boolean;
    placeholder?: string | null;
    options?: string[];
};

type ConsentEntry = {
    type: string;
    key: string;
    label: string;
    text: string;
    version: number;
};

type FormPayload = {
    id: number;
    title: string;
    description: string | null;
    schema: { fields: FieldShape[] };
    schema_version: number;
    consents: ConsentEntry[];
};

const props = withDefaults(
    defineProps<{ form: FormPayload; preview?: boolean }>(),
    { preview: false },
);

defineOptions({ layout: null });

type FieldValue = string | string[] | boolean;

const initialData: Record<string, FieldValue> = {};
for (const field of props.form.schema.fields) {
    if (field.type === 'checkboxes') {
        initialData[field.key] = [];
    } else if (field.type === 'toggle') {
        initialData[field.key] = false;
    } else {
        initialData[field.key] = '';
    }
}

const initialConsents: Record<string, boolean> = {};
for (const consent of props.form.consents) {
    initialConsents[consent.key] = false;
}

const formState = useForm<{
    values: Record<string, FieldValue>;
    consents: Record<string, boolean>;
}>({
    values: initialData,
    consents: initialConsents,
});

function autocompleteFor(type: FieldShape['type']): string {
    switch (type) {
        case 'email':
            return 'email';
        case 'name':
            return 'name';
        case 'phone':
            return 'tel';
        default:
            return 'off';
    }
}

function htmlInputType(type: FieldShape['type']): string {
    if (type === 'phone') {
        return 'tel';
    }

    if (type === 'name') {
        return 'text';
    }

    return type;
}

function placeholderFor(field: FieldShape): string {
    if (field.placeholder) {
        return field.placeholder;
    }

    if (field.type === 'phone') {
        return '555-123-4567';
    }

    return '';
}

function formatPhone(digits: string): string {
    const d = digits.slice(0, 10);
    const parts: string[] = [];

    if (d.length > 0) {
        parts.push(d.slice(0, Math.min(3, d.length)));
    }
    if (d.length > 3) {
        parts.push(d.slice(3, Math.min(6, d.length)));
    }
    if (d.length > 6) {
        parts.push(d.slice(6, 10));
    }

    return parts.join('-');
}

function setPhoneValue(key: string, raw: string | number): void {
    const digits = String(raw).replace(/\D/g, '');
    formState.values[key] = formatPhone(digits);
}

function setCheckboxOption(
    key: string,
    option: string,
    checked: boolean | 'indeterminate',
): void {
    const current = (formState.values[key] as string[]) ?? [];

    if (checked === true) {
        if (! current.includes(option)) {
            formState.values[key] = [...current, option];
        }

        return;
    }

    formState.values[key] = current.filter((value) => value !== option);
}

function isOptionChecked(key: string, option: string): boolean {
    const current = formState.values[key];

    return Array.isArray(current) && current.includes(option);
}

function fieldError(key: string): string | undefined {
    const errors = formState.errors as unknown as Record<string, string>;

    for (const errorKey of Object.keys(errors)) {
        if (
            errorKey === `data.${key}` ||
            errorKey.startsWith(`data.${key}.`)
        ) {
            return errors[errorKey];
        }
    }

    return undefined;
}

function consentError(key: string): string | undefined {
    const errors = formState.errors as unknown as Record<string, string>;

    return errors[`consents.${key}`];
}

const submitLabel = computed(() =>
    props.preview ? 'Submit disabled in preview' : 'Submit response',
);

function submit(): void {
    if (props.preview) {
        return;
    }

    formState
        .transform((d) => ({ data: d.values, consents: d.consents }))
        .post(PublicFormController.submit.url({ form: props.form.id }));
}
</script>

<template>
    <Head :title="form.title" />

    <div class="flex min-h-screen flex-col items-center bg-background px-4 py-10">
        <div
            v-if="preview"
            class="mb-4 w-full max-w-2xl rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
            data-test="preview-banner"
        >
            <strong class="font-medium">Preview mode</strong> — responses cannot
            be submitted from this view. Close this tab to return to the editor.
        </div>

        <Card class="w-full max-w-2xl">
            <CardHeader>
                <CardTitle>{{ form.title }}</CardTitle>
                <CardDescription v-if="form.description">
                    {{ form.description }}
                </CardDescription>
            </CardHeader>

            <form @submit.prevent="submit">
                <CardContent class="space-y-5">
                    <div
                        v-for="field in form.schema.fields"
                        :key="field.key"
                        class="grid gap-2"
                    >
                        <Label :for="`field-${field.key}`">
                            {{ field.label }}
                            <span v-if="field.required" class="text-destructive"
                                >*</span
                            >
                        </Label>

                        <Input
                            v-if="
                                ['text', 'number', 'date', 'email', 'name'].includes(
                                    field.type,
                                )
                            "
                            :id="`field-${field.key}`"
                            v-model="formState.values[field.key] as string"
                            :type="htmlInputType(field.type)"
                            :required="field.required"
                            :placeholder="placeholderFor(field)"
                            :autocomplete="autocompleteFor(field.type)"
                        />

                        <Input
                            v-else-if="field.type === 'phone'"
                            :id="`field-${field.key}`"
                            :model-value="formState.values[field.key] as string"
                            type="tel"
                            inputmode="tel"
                            :maxlength="12"
                            :required="field.required"
                            :placeholder="placeholderFor(field)"
                            autocomplete="tel"
                            @update:model-value="
                                (v) => setPhoneValue(field.key, v)
                            "
                        />

                        <Textarea
                            v-else-if="field.type === 'textarea'"
                            :id="`field-${field.key}`"
                            v-model="formState.values[field.key] as string"
                            :required="field.required"
                            :placeholder="field.placeholder ?? ''"
                            rows="3"
                        />

                        <Select
                            v-else-if="field.type === 'select'"
                            v-model="formState.values[field.key] as string"
                            :required="field.required"
                        >
                            <SelectTrigger
                                :id="`field-${field.key}`"
                                class="w-full"
                            >
                                <SelectValue placeholder="Pick one" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in field.options ?? []"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div
                            v-else-if="field.type === 'toggle'"
                            class="flex items-center gap-2"
                        >
                            <Switch
                                :id="`field-${field.key}`"
                                v-model="formState.values[field.key] as boolean"
                            />
                            <Label
                                v-if="field.placeholder"
                                :for="`field-${field.key}`"
                                class="text-sm font-normal text-muted-foreground"
                            >
                                {{ field.placeholder }}
                            </Label>
                        </div>

                        <div
                            v-else-if="field.type === 'checkboxes'"
                            class="space-y-2"
                        >
                            <label
                                v-for="option in field.options ?? []"
                                :key="option"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="
                                        isOptionChecked(field.key, option)
                                    "
                                    @update:model-value="
                                        (v) =>
                                            setCheckboxOption(
                                                field.key,
                                                option,
                                                v,
                                            )
                                    "
                                />
                                <span>{{ option }}</span>
                            </label>
                        </div>

                        <InputError :message="fieldError(field.key)" />
                    </div>

                    <div
                        v-if="form.consents.length > 0"
                        class="space-y-3 border-t pt-4"
                        data-test="respond-consents"
                    >
                        <h3 class="text-sm font-semibold">Required consents</h3>
                        <div
                            v-for="consent in form.consents"
                            :key="consent.key"
                            class="space-y-2 rounded-lg border p-3"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ consent.label }}
                            </p>
                            <p class="text-sm leading-snug">
                                {{ consent.text }}
                            </p>
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    v-model="formState.consents[consent.key]"
                                    :data-test="`consent-${consent.key}`"
                                />
                                <span>I accept</span>
                            </label>
                            <InputError :message="consentError(consent.key)" />
                        </div>
                    </div>
                </CardContent>

                <CardFooter class="mt-6 justify-end border-t pt-6">
                    <Button
                        type="submit"
                        :disabled="formState.processing || preview"
                    >
                        {{ submitLabel }}
                    </Button>
                </CardFooter>
            </form>
        </Card>
    </div>
</template>
