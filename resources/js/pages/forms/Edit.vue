<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    AlignLeft,
    AtSign,
    Calendar,
    ChevronsDownUp,
    ChevronsUpDown,
    Eye,
    Hash,
    List,
    ListChecks,
    Phone,
    Plus,
    Search,
    Settings2,
    ToggleRight,
    Trash2,
    Type,
    User,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    edit as formEdit,
    index as formsIndex,
    preview as formPreview,
    update as formUpdate,
} from '@/routes/forms';

type FieldTypeValue =
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

type FieldShape = {
    key: string;
    label: string;
    type: FieldTypeValue;
    required: boolean;
    placeholder?: string | null;
    options?: string[];
};

type DraggableField = FieldShape & { _uid: number; _originalKey: string };

type FormShape = {
    id: number;
    title: string;
    description: string | null;
    status: 'draft' | 'published' | 'closed';
    status_label: string;
    schema: { fields: FieldShape[] };
    schema_version: number;
    required_consents: string[];
    custom_consents: Array<{ key: string; label: string; text: string }>;
};

type FieldTypeOption = {
    value: FieldTypeValue;
    label: string;
    requires_options: boolean;
};

type ConsentOption = {
    value: string;
    label: string;
    text: string;
};

const props = defineProps<{
    form: FormShape;
    fieldTypeOptions: FieldTypeOption[];
    consentOptions: ConsentOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Forms', href: formsIndex() },
        { title: props.form.title, href: formEdit(props.form.id) },
    ],
});

const fieldTypeIcons: Record<FieldTypeValue, LucideIcon> = {
    text: Type,
    textarea: AlignLeft,
    number: Hash,
    date: Calendar,
    select: List,
    checkboxes: ListChecks,
    toggle: ToggleRight,
    email: AtSign,
    name: User,
    phone: Phone,
};

type Category = {
    name: string;
    types: FieldTypeValue[];
};

const categories: Category[] = [
    { name: 'Text', types: ['textarea', 'text'] },
    { name: 'Choice', types: ['select', 'checkboxes', 'toggle'] },
    { name: 'Contact Info', types: ['email', 'name', 'phone'] },
    { name: 'Number', types: ['number'] },
    { name: 'Date and Time', types: ['date'] },
];

let nextUid = 0;

function makeUid(): number {
    nextUid += 1;

    return nextUid;
}

type DraggableCustomConsent = {
    _uid: number;
    _originalKey: string;
    key: string;
    label: string;
    text: string;
};

const builder = useForm({
    title: props.form.title,
    description: props.form.description ?? '',
    schema: {
        fields: props.form.schema.fields.map((field) => ({
            _uid: makeUid(),
            _originalKey: field.key,
            key: field.key,
            label: field.label,
            type: field.type,
            required: field.required,
            placeholder: field.placeholder ?? '',
            options: field.options ?? [],
        })) as DraggableField[],
    },
    required_consents: [...props.form.required_consents],
    custom_consents: props.form.custom_consents.map((entry) => ({
        _uid: makeUid(),
        _originalKey: entry.key,
        key: entry.key,
        label: entry.label,
        text: entry.text,
    })) as DraggableCustomConsent[],
});

function addCustomConsent() {
    if (readOnly.value) {
        return;
    }

    builder.custom_consents.push({
        _uid: makeUid(),
        _originalKey: '',
        key: '',
        label: '',
        text: '',
    });
}

function removeCustomConsent(uid: number) {
    const index = builder.custom_consents.findIndex((c) => c._uid === uid);

    if (index !== -1) {
        builder.custom_consents.splice(index, 1);
    }
}

function customConsentError(
    index: number,
    attr: 'key' | 'label' | 'text',
): string | undefined {
    const errors = builder.errors as Record<string, string | undefined>;

    return errors[`custom_consents.${index}.${attr}`];
}

const readOnly = computed(() => props.form.status === 'closed');
const search = ref('');
const selectedUid = ref<number | null>(null);
const sectionCollapsed = ref(false);

function fieldTypeRequiresOptions(type: string): boolean {
    return (
        props.fieldTypeOptions.find((option) => option.value === type)
            ?.requires_options ?? false
    );
}

function fieldTypeLabel(type: FieldTypeValue): string {
    return (
        props.fieldTypeOptions.find((option) => option.value === type)?.label ?? type
    );
}

function previewInputType(type: FieldTypeValue): string {
    switch (type) {
        case 'number':
            return 'number';
        case 'date':
            return 'date';
        case 'email':
            return 'email';
        case 'phone':
            return 'tel';
        default:
            return 'text';
    }
}

const defaultLabels: Partial<Record<FieldTypeValue, string>> = {
    email: 'Email',
    name: 'Name',
    phone: 'Phone',
};

function blankFieldOfType(type: FieldTypeValue): DraggableField {
    return {
        _uid: makeUid(),
        _originalKey: '',
        key: '',
        label: defaultLabels[type] ?? '',
        type,
        required: false,
        placeholder: '',
        options: [],
    };
}

function slugifyKey(label: string): string {
    const slug = label
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    if (slug === '') {
        return 'field';
    }

    return /^[a-z]/.test(slug) ? slug : `field_${slug}`;
}

function appendField(type: FieldTypeValue) {
    if (readOnly.value) {
        return;
    }

    const field = blankFieldOfType(type);
    builder.schema.fields.push(field);
    selectedUid.value = field._uid;
}

function cloneFromPalette(item: FieldTypeOption): DraggableField {
    return blankFieldOfType(item.value);
}

function removeField(uid: number) {
    const index = builder.schema.fields.findIndex((f) => f._uid === uid);

    if (index === -1) {
        return;
    }

    builder.schema.fields.splice(index, 1);

    if (selectedUid.value === uid) {
        selectedUid.value = null;
    }
}

function paletteOptionsFor(category: Category): FieldTypeOption[] {
    const query = search.value.trim().toLowerCase();

    return props.fieldTypeOptions.filter((option) => {
        if (! category.types.includes(option.value)) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return option.label.toLowerCase().includes(query);
    });
}

const visibleCategories = computed(() =>
    categories.filter((category) => paletteOptionsFor(category).length > 0),
);

const selectedField = computed<DraggableField | null>(() => {
    if (selectedUid.value === null) {
        return null;
    }

    return (
        builder.schema.fields.find((field) => field._uid === selectedUid.value) ??
        null
    );
});

const selectedFieldIndex = computed(() =>
    selectedUid.value === null
        ? -1
        : builder.schema.fields.findIndex(
              (field) => field._uid === selectedUid.value,
          ),
);

const newOption = ref<Record<number, string>>({});

function addOption() {
    const index = selectedFieldIndex.value;

    if (index < 0) {
        return;
    }

    const field = builder.schema.fields[index];
    const value = (newOption.value[field._uid] ?? '').trim();

    if (value === '') {
        return;
    }

    field.options = [...(field.options ?? []), value];
    newOption.value[field._uid] = '';
}

function removeOption(optionIndex: number) {
    const index = selectedFieldIndex.value;

    if (index < 0) {
        return;
    }

    const field = builder.schema.fields[index];
    field.options = (field.options ?? []).filter((_, i) => i !== optionIndex);
}

function selectField(uid: number) {
    selectedUid.value = uid;
}

function submit() {
    builder
        .transform((data) => {
            const usedKeys = new Set<string>();

            const fields = data.schema.fields.map((field) => {
                let key = field._originalKey || slugifyKey(field.label);
                const base = key;
                let suffix = 2;

                while (usedKeys.has(key)) {
                    key = `${base}_${suffix}`;
                    suffix += 1;
                }
                usedKeys.add(key);

                const payload: Record<string, unknown> = {
                    key,
                    label: field.label,
                    type: field.type,
                    required: field.required,
                    placeholder: field.placeholder?.trim() ?? '',
                };

                if (fieldTypeRequiresOptions(field.type)) {
                    payload.options = field.options;
                }

                return payload;
            });

            const usedConsentKeys = new Set<string>();
            const customConsents = data.custom_consents.map((entry) => {
                let key = entry._originalKey || slugifyKey(entry.label);
                const base = key;
                let suffix = 2;

                while (usedConsentKeys.has(key)) {
                    key = `${base}_${suffix}`;
                    suffix += 1;
                }
                usedConsentKeys.add(key);

                return {
                    key,
                    label: entry.label,
                    text: entry.text,
                };
            });

            return {
                ...data,
                schema: { fields },
                custom_consents: customConsents,
            };
        })
        .patch(formUpdate(props.form.id));
}

function fieldError(index: number, attr: string): string | undefined {
    if (index < 0) {
        return undefined;
    }

    const key = `schema.fields.${index}.${attr}`;
    const errors = builder.errors as Record<string, string | undefined>;

    return errors[key];
}
</script>

<template>
    <Head :title="`Edit ${form.title}`" />

    <div class="flex h-[calc(100svh-3.5rem)] flex-col">
        <header
            class="flex items-center justify-between border-b bg-card px-4 py-2.5 md:px-6"
        >
            <Heading
                variant="small"
                :title="`Edit ${form.title}`"
                :description="`Schema version ${form.schema_version}`"
            />
            <div class="flex items-center gap-2">
                <Badge>{{ form.status_label }}</Badge>
                <Button as-child variant="ghost" size="sm">
                    <Link :href="formsIndex()">Cancel</Link>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="formPreview(form.id).url"
                        target="_blank"
                        rel="noopener"
                        data-test="preview-link"
                    >
                        <Eye class="mr-1.5 size-4" />
                        Preview
                    </a>
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :disabled="builder.processing || readOnly"
                    @click="submit"
                >
                    Save changes
                </Button>
            </div>
        </header>

        <div
            v-if="readOnly"
            class="border-b border-yellow-500/50 bg-yellow-500/10 px-4 py-2 text-sm text-yellow-700 md:px-6 dark:text-yellow-300"
        >
            This form is closed. Reopen it from the list to edit again.
        </div>

        <div
            class="grid flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[18rem_1fr_22rem]"
        >
            <aside
                class="hidden flex-col overflow-y-auto border-r bg-muted/20 lg:flex"
            >
                <div class="border-b p-4">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search Field Types..."
                            class="pl-9"
                        />
                    </div>
                </div>

                <div class="flex-1 space-y-6 overflow-y-auto p-4">
                    <div
                        v-for="category in visibleCategories"
                        :key="category.name"
                    >
                        <h3
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            {{ category.name }}
                        </h3>
                        <draggable
                            :model-value="paletteOptionsFor(category)"
                            :group="{
                                name: 'fields',
                                pull: 'clone',
                                put: false,
                            }"
                            :sort="false"
                            :clone="cloneFromPalette"
                            item-key="value"
                            :disabled="readOnly"
                            :animation="0"
                            :revert-clone="false"
                            ghost-class="opacity-0"
                            drag-class="cursor-grabbing"
                            tag="div"
                            class="grid grid-cols-2 gap-2"
                        >
                            <template #item="{ element: option }">
                                <button
                                    type="button"
                                    class="flex cursor-grab items-center gap-2 rounded-lg border bg-card px-3 py-2 text-left text-sm shadow-xs transition hover:border-primary hover:shadow-sm active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="readOnly"
                                    :data-test="`palette-${option.value}`"
                                    @click="appendField(option.value)"
                                >
                                    <component
                                        :is="fieldTypeIcons[option.value]"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span class="truncate">{{
                                        option.label
                                    }}</span>
                                </button>
                            </template>
                        </draggable>
                    </div>

                    <p
                        v-if="visibleCategories.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No field types match "{{ search }}".
                    </p>
                </div>
            </aside>

            <main
                class="overflow-y-auto bg-muted/10 px-4 py-8 md:px-8"
                @click="selectedUid = null"
            >
                <div class="mx-auto max-w-3xl space-y-6" @click.stop>
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-block size-2 rounded-full"
                            :class="
                                form.status === 'published'
                                    ? 'bg-emerald-500'
                                    : form.status === 'draft'
                                      ? 'bg-amber-500'
                                      : 'bg-muted-foreground'
                            "
                        />
                        <input
                            v-model="builder.title"
                            class="flex-1 border-none bg-transparent text-2xl font-medium focus:outline-none focus:ring-0"
                            :disabled="readOnly"
                            placeholder="Untitled form"
                        />
                    </div>
                    <InputError :message="builder.errors.title" />

                    <input
                        v-model="builder.description"
                        class="w-full border-none bg-transparent text-sm text-muted-foreground focus:outline-none focus:ring-0"
                        :disabled="readOnly"
                        placeholder="Add a description visible to respondents"
                    />

                    <p
                        v-if="builder.errors.schema"
                        class="text-sm text-destructive"
                    >
                        {{ builder.errors.schema }}
                    </p>

                    <div class="rounded-xl border bg-card p-6 shadow-sm">
                        <div
                            class="mb-4 flex items-center justify-between text-sm text-muted-foreground"
                        >
                            <span>Section</span>
                            <button
                                type="button"
                                class="rounded p-1 hover:bg-muted hover:text-foreground"
                                :aria-label="
                                    sectionCollapsed
                                        ? 'Expand section'
                                        : 'Collapse section'
                                "
                                @click="sectionCollapsed = !sectionCollapsed"
                            >
                                <component
                                    :is="
                                        sectionCollapsed
                                            ? ChevronsUpDown
                                            : ChevronsDownUp
                                    "
                                    class="size-4"
                                />
                            </button>
                        </div>

                        <draggable
                            v-show="!sectionCollapsed"
                            v-model="builder.schema.fields"
                            :group="{ name: 'fields' }"
                            item-key="_uid"
                            :disabled="readOnly"
                            :animation="150"
                            ghost-class="opacity-50"
                            tag="div"
                            class="min-h-[8rem] space-y-2"
                        >
                            <template #header>
                                <div
                                    v-if="builder.schema.fields.length === 0"
                                    class="rounded-md border border-dashed py-12 text-center text-sm text-muted-foreground"
                                >
                                    Drag a field type from the left, or click
                                    one to add it here.
                                </div>
                            </template>
                            <template #item="{ element: field, index }">
                                <div
                                    class="group cursor-pointer rounded-lg border border-transparent p-4 transition hover:border-muted-foreground/30"
                                    :class="{
                                        'border-primary bg-primary/5 ring-2 ring-primary/20 hover:border-primary':
                                            selectedUid === field._uid,
                                    }"
                                    :data-test="`field-row-${index}`"
                                    @click.stop="selectField(field._uid)"
                                >
                                    <div
                                        class="mb-1.5 flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <Label
                                                class="text-sm font-medium"
                                            >
                                                {{
                                                    field.label ||
                                                    fieldTypeLabel(field.type)
                                                }}
                                                <span
                                                    v-if="field.required"
                                                    class="text-destructive"
                                                    >*</span
                                                >
                                            </Label>
                                        </div>
                                        <button
                                            v-show="
                                                selectedUid === field._uid
                                            "
                                            type="button"
                                            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive"
                                            :disabled="readOnly"
                                            :aria-label="`Delete ${field.label || 'field'}`"
                                            @click.stop="removeField(field._uid)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>

                                    <template v-if="field.type === 'textarea'">
                                        <textarea
                                            disabled
                                            rows="3"
                                            class="pointer-events-none mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            :placeholder="
                                                field.placeholder ||
                                                'Long answer text'
                                            "
                                        />
                                    </template>
                                    <template
                                        v-else-if="field.type === 'select'"
                                    >
                                        <select
                                            disabled
                                            class="pointer-events-none mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            <option>
                                                {{
                                                    field.placeholder ||
                                                    'Choose an option'
                                                }}
                                            </option>
                                            <option
                                                v-for="opt in field.options"
                                                :key="opt"
                                            >
                                                {{ opt }}
                                            </option>
                                        </select>
                                    </template>
                                    <template
                                        v-else-if="field.type === 'toggle'"
                                    >
                                        <div
                                            class="pointer-events-none mt-2 flex items-center gap-2 text-sm text-muted-foreground"
                                        >
                                            <Switch :model-value="false" />
                                            <span>{{
                                                field.placeholder ||
                                                field.label ||
                                                'Off'
                                            }}</span>
                                        </div>
                                    </template>
                                    <template
                                        v-else-if="field.type === 'checkboxes'"
                                    >
                                        <div
                                            v-if="(field.options ?? []).length > 0"
                                            class="pointer-events-none mt-2 space-y-1.5"
                                        >
                                            <label
                                                v-for="opt in field.options"
                                                :key="opt"
                                                class="flex items-center gap-2 text-sm text-muted-foreground"
                                            >
                                                <input
                                                    type="checkbox"
                                                    disabled
                                                    class="size-4 rounded border-input"
                                                />
                                                <span>{{ opt }}</span>
                                            </label>
                                        </div>
                                        <p
                                            v-else
                                            class="pointer-events-none mt-2 text-sm italic text-muted-foreground"
                                        >
                                            Add options in the panel on the right.
                                        </p>
                                    </template>
                                    <template v-else>
                                        <input
                                            disabled
                                            :type="previewInputType(field.type)"
                                            class="pointer-events-none mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            :placeholder="
                                                field.placeholder ||
                                                fieldTypeLabel(field.type)
                                            "
                                        />
                                    </template>
                                </div>
                            </template>
                        </draggable>
                    </div>

                    <section
                        class="space-y-4 rounded-xl border bg-card p-6 shadow-sm"
                        data-test="required-consents-picker"
                    >
                        <div>
                            <h2 class="text-sm font-semibold">
                                Required parental consents
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Public respondents will see these as required
                                checkboxes. The text shown at submission time is
                                snapshotted on each consent record.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label
                                v-for="option in consentOptions"
                                :key="option.value"
                                class="flex items-start gap-3 rounded-md border p-3 text-sm"
                            >
                                <input
                                    v-model="builder.required_consents"
                                    type="checkbox"
                                    :value="option.value"
                                    :disabled="readOnly"
                                    class="mt-1 h-4 w-4 rounded border-input"
                                    :data-test="`consent-toggle-${option.value}`"
                                />
                                <span class="flex-1 space-y-1">
                                    <span class="block font-medium">{{
                                        option.label
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ option.text }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="space-y-3 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold">
                                    Custom consents
                                </h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="readOnly"
                                    data-test="add-custom-consent"
                                    @click="addCustomConsent"
                                >
                                    <Plus class="mr-1 size-4" />
                                    Add custom
                                </Button>
                            </div>

                            <div
                                v-if="builder.custom_consents.length === 0"
                                class="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground"
                            >
                                No custom consents yet. Add one to ask for
                                form-specific acknowledgements.
                            </div>

                            <div
                                v-for="(
                                    consent, consentIndex
                                ) in builder.custom_consents"
                                :key="consent._uid"
                                class="space-y-3 rounded-md border p-3"
                                :data-test="`custom-consent-${consentIndex}`"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 space-y-1.5">
                                        <Label
                                            :for="`custom-consent-label-${consent._uid}`"
                                            class="text-xs"
                                        >
                                            Label
                                        </Label>
                                        <Input
                                            :id="`custom-consent-label-${consent._uid}`"
                                            v-model="consent.label"
                                            placeholder="e.g. Parking lot rules"
                                            :disabled="readOnly"
                                        />
                                        <InputError
                                            :message="
                                                customConsentError(
                                                    consentIndex,
                                                    'label',
                                                )
                                            "
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-6 rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="readOnly"
                                        aria-label="Remove custom consent"
                                        @click="removeCustomConsent(consent._uid)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        :for="`custom-consent-text-${consent._uid}`"
                                        class="text-xs"
                                    >
                                        Consent text
                                    </Label>
                                    <Textarea
                                        :id="`custom-consent-text-${consent._uid}`"
                                        v-model="consent.text"
                                        rows="3"
                                        :disabled="readOnly"
                                        placeholder="What the respondent agrees to."
                                    />
                                    <InputError
                                        :message="
                                            customConsentError(
                                                consentIndex,
                                                'text',
                                            )
                                        "
                                    />
                                </div>
                                <InputError
                                    :message="
                                        customConsentError(
                                            consentIndex,
                                            'key',
                                        )
                                    "
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <aside
                class="hidden overflow-y-auto border-l bg-card lg:block"
            >
                <div v-if="selectedField" class="space-y-6 p-5">
                    <div class="flex items-center gap-2 border-b pb-3">
                        <component
                            :is="fieldTypeIcons[selectedField.type]"
                            class="size-5 text-muted-foreground"
                        />
                        <h2 class="text-base font-medium">
                            {{
                                selectedField.label ||
                                fieldTypeLabel(selectedField.type)
                            }}
                        </h2>
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`config-label-${selectedField._uid}`">
                            Label
                        </Label>
                        <Input
                            :id="`config-label-${selectedField._uid}`"
                            v-model="selectedField.label"
                            placeholder="First name"
                            :disabled="readOnly"
                        />
                        <InputError
                            :message="fieldError(selectedFieldIndex, 'label')"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`config-type-${selectedField._uid}`">
                            Type
                        </Label>
                        <select
                            :id="`config-type-${selectedField._uid}`"
                            v-model="selectedField.type"
                            :disabled="readOnly"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none disabled:opacity-60"
                        >
                            <option
                                v-for="option in fieldTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError
                            :message="fieldError(selectedFieldIndex, 'type')"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            :for="`config-placeholder-${selectedField._uid}`"
                        >
                            Placeholder
                        </Label>
                        <Input
                            :id="`config-placeholder-${selectedField._uid}`"
                            v-model="selectedField.placeholder"
                            :disabled="readOnly"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-0.5">
                            <Label class="text-sm font-medium">Required</Label>
                            <p class="text-xs text-muted-foreground">
                                Make this field required or optional.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="selectedField.required"
                            :disabled="readOnly"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                selectedField.required
                                    ? 'bg-primary'
                                    : 'bg-muted-foreground/30'
                            "
                            @click="
                                selectedField.required = !selectedField.required
                            "
                        >
                            <span
                                class="inline-block size-4 transform rounded-full bg-white shadow transition"
                                :class="
                                    selectedField.required
                                        ? 'translate-x-[1.125rem]'
                                        : 'translate-x-0.5'
                                "
                            />
                        </button>
                    </div>

                    <div
                        v-if="fieldTypeRequiresOptions(selectedField.type)"
                        class="space-y-2 border-t pt-4"
                    >
                        <Label>Options</Label>
                        <ul
                            v-if="(selectedField.options ?? []).length > 0"
                            class="space-y-1"
                        >
                            <li
                                v-for="(
                                    option, optionIndex
                                ) in selectedField.options"
                                :key="`${selectedField._uid}-${optionIndex}`"
                                class="flex items-center justify-between gap-2 rounded border bg-muted/40 px-3 py-1 text-sm"
                            >
                                <span class="truncate">{{ option }}</span>
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="readOnly"
                                    :aria-label="`Remove option ${option}`"
                                    @click="removeOption(optionIndex)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </li>
                        </ul>
                        <div class="flex gap-2">
                            <Input
                                v-model="newOption[selectedField._uid]"
                                placeholder="Add option"
                                :disabled="readOnly"
                                @keydown.enter.prevent="addOption"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                :disabled="readOnly"
                                @click="addOption"
                            >
                                Add
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center"
                >
                    <Settings2 class="size-6 text-muted-foreground" />
                    <p class="text-sm font-medium">No field selected</p>
                    <p class="text-xs text-muted-foreground">
                        Click a field in the canvas to edit its settings.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
