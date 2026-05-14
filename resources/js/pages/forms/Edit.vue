<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { GripVertical, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as formsIndex, edit as formEdit, update as formUpdate } from '@/routes/forms';

type FieldShape = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox';
    required: boolean;
    placeholder?: string | null;
    options?: string[];
};

type DraggableField = FieldShape & { _uid: number };

type FormShape = {
    id: number;
    title: string;
    description: string | null;
    status: 'draft' | 'published' | 'closed';
    status_label: string;
    schema: { fields: FieldShape[] };
    schema_version: number;
};

type FieldTypeOption = {
    value: string;
    label: string;
    requires_options: boolean;
};

const props = defineProps<{
    form: FormShape;
    fieldTypeOptions: FieldTypeOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Forms', href: formsIndex() },
        { title: props.form.title, href: formEdit(props.form.id) },
    ],
});

let nextUid = 0;

function makeUid(): number {
    nextUid += 1;
    return nextUid;
}

const builder = useForm({
    title: props.form.title,
    description: props.form.description ?? '',
    schema: {
        fields: props.form.schema.fields.map((field) => ({
            _uid: makeUid(),
            key: field.key,
            label: field.label,
            type: field.type,
            required: field.required,
            placeholder: field.placeholder ?? '',
            options: field.options ?? [],
        })),
    },
});

const readOnly = computed(() => props.form.status === 'closed');

function addField() {
    builder.schema.fields.push({
        _uid: makeUid(),
        key: '',
        label: '',
        type: 'text',
        required: false,
        placeholder: '',
        options: [],
    });
}

function removeField(index: number) {
    builder.schema.fields.splice(index, 1);
}

function fieldTypeRequiresOptions(type: string): boolean {
    return props.fieldTypeOptions.find((option) => option.value === type)
        ?.requires_options ?? false;
}

const newOption = ref<Record<number, string>>({});

function addOption(field: DraggableField) {
    const value = (newOption.value[field._uid] ?? '').trim();
    if (value === '') return;
    field.options = [...(field.options ?? []), value];
    newOption.value[field._uid] = '';
}

function removeOption(field: DraggableField, optionIndex: number) {
    field.options = field.options?.filter((_, i) => i !== optionIndex);
}

function submit() {
    builder.transform((data) => ({
        ...data,
        schema: {
            fields: data.schema.fields.map((field) => {
                const payload: Record<string, unknown> = {
                    key: field.key,
                    label: field.label,
                    type: field.type,
                    required: field.required,
                    placeholder: field.placeholder?.trim() ?? '',
                };

                if (fieldTypeRequiresOptions(field.type)) {
                    payload.options = field.options;
                }

                return payload;
            }),
        },
    })).patch(formUpdate(props.form.id));
}

function fieldError(index: number, attr: string): string | undefined {
    const key = `schema.fields.${index}.${attr}`;
    const errors = builder.errors as Record<string, string | undefined>;
    return errors[key];
}
</script>

<template>
    <Head :title="`Edit ${form.title}`" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                variant="small"
                :title="`Edit ${form.title}`"
                :description="`Schema version ${form.schema_version}.`"
            />
            <div class="flex flex-wrap items-center gap-3">
                <Badge>{{ form.status_label }}</Badge>
                <Button as-child variant="ghost">
                    <Link :href="formsIndex()">Back to forms</Link>
                </Button>
            </div>
        </div>

        <div
            v-if="readOnly"
            class="rounded-md border border-yellow-500/50 bg-yellow-500/10 p-3 text-sm text-yellow-700 dark:text-yellow-300"
        >
            This form is closed. Reopen it from the list to edit again.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    v-model="builder.title"
                    required
                    :disabled="readOnly"
                />
                <InputError :message="builder.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    v-model="builder.description"
                    rows="2"
                    :disabled="readOnly"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none disabled:opacity-60"
                ></textarea>
                <InputError :message="builder.errors.description" />
            </div>

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold">Fields</h2>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="readOnly"
                        @click="addField"
                        data-test="add-field"
                    >
                        Add field
                    </Button>
                </div>
                <p
                    v-if="builder.errors.schema"
                    class="text-sm text-destructive"
                >
                    {{ builder.errors.schema }}
                </p>

                <draggable
                    v-model="builder.schema.fields"
                    tag="ul"
                    item-key="_uid"
                    handle=".drag-handle"
                    :disabled="readOnly"
                    :animation="150"
                    ghost-class="opacity-50"
                    class="space-y-4"
                >
                    <template #item="{ element: field, index }">
                        <li
                            class="space-y-3 rounded-lg border bg-card p-4"
                            :data-test="`field-row-${index}`"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="drag-handle inline-flex h-7 w-7 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50 active:cursor-grabbing"
                                        :disabled="readOnly"
                                        :aria-label="`Drag field ${index + 1}`"
                                    >
                                        <GripVertical class="size-4" />
                                    </button>
                                    <span class="text-xs text-muted-foreground">
                                        #{{ index + 1 }}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive"
                                    :disabled="readOnly"
                                    @click="removeField(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`field-key-${field._uid}`">Key</Label>
                                    <Input
                                        :id="`field-key-${field._uid}`"
                                        v-model="field.key"
                                        placeholder="first_name"
                                        :disabled="readOnly"
                                    />
                                    <InputError :message="fieldError(index, 'key')" />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`field-label-${field._uid}`">Label</Label>
                                    <Input
                                        :id="`field-label-${field._uid}`"
                                        v-model="field.label"
                                        placeholder="First name"
                                        :disabled="readOnly"
                                    />
                                    <InputError
                                        :message="fieldError(index, 'label')"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`field-type-${field._uid}`">Type</Label>
                                    <select
                                        :id="`field-type-${field._uid}`"
                                        v-model="field.type"
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
                                        :message="fieldError(index, 'type')"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`field-placeholder-${field._uid}`">
                                        Placeholder
                                    </Label>
                                    <Input
                                        :id="`field-placeholder-${field._uid}`"
                                        v-model="field.placeholder"
                                        :disabled="readOnly"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    :id="`field-required-${field._uid}`"
                                    v-model="field.required"
                                    type="checkbox"
                                    :disabled="readOnly"
                                    class="h-4 w-4 rounded border-input"
                                />
                                <Label
                                    :for="`field-required-${field._uid}`"
                                    class="cursor-pointer"
                                >
                                    Required
                                </Label>
                            </div>

                            <div
                                v-if="fieldTypeRequiresOptions(field.type)"
                                class="space-y-2 rounded-md border border-dashed p-3"
                            >
                                <Label>Options</Label>
                                <ul class="space-y-1">
                                    <li
                                        v-for="(option, optionIndex) in field.options"
                                        :key="optionIndex"
                                        class="flex items-center justify-between gap-2 rounded border bg-muted/40 px-3 py-1 text-sm"
                                    >
                                        <span>{{ option }}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="readOnly"
                                            @click="removeOption(field, optionIndex)"
                                        >
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </li>
                                </ul>
                                <div class="flex gap-2">
                                    <Input
                                        v-model="newOption[field._uid]"
                                        placeholder="Add option"
                                        :disabled="readOnly"
                                        @keydown.enter.prevent="addOption(field)"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        :disabled="readOnly"
                                        @click="addOption(field)"
                                    >
                                        Add
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </template>
                </draggable>
            </section>

            <div class="flex justify-end">
                <Button type="submit" :disabled="builder.processing || readOnly">
                    Save changes
                </Button>
            </div>
        </form>
    </div>
</template>
