<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FieldShape = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox';
    required: boolean;
    placeholder?: string | null;
    options?: string[];
};

type FormPayload = {
    id: number;
    title: string;
    description: string | null;
    schema: { fields: FieldShape[] };
    schema_version: number;
};

defineProps<{ form: FormPayload }>();

defineOptions({ layout: null });
</script>

<template>
    <Head :title="form.title" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-10">
        <Card class="w-full max-w-2xl">
            <CardHeader>
                <CardTitle>{{ form.title }}</CardTitle>
                <CardDescription v-if="form.description">
                    {{ form.description }}
                </CardDescription>
            </CardHeader>

            <Form
                v-bind="PublicFormController.submit.form(form.id)"
                v-slot="{ errors, processing }"
            >
                <CardContent class="space-y-5">
                    <div
                        v-for="field in form.schema.fields"
                        :key="field.key"
                        class="grid gap-2"
                    >
                        <Label :for="`field-${field.key}`">
                            {{ field.label }}
                            <span v-if="field.required" class="text-destructive">*</span>
                        </Label>

                        <Input
                            v-if="['text', 'number', 'date'].includes(field.type)"
                            :id="`field-${field.key}`"
                            :name="`data[${field.key}]`"
                            :type="field.type"
                            :required="field.required"
                            :placeholder="field.placeholder ?? ''"
                            autocomplete="off"
                        />

                        <textarea
                            v-else-if="field.type === 'textarea'"
                            :id="`field-${field.key}`"
                            :name="`data[${field.key}]`"
                            :required="field.required"
                            :placeholder="field.placeholder ?? ''"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none"
                        ></textarea>

                        <select
                            v-else-if="field.type === 'select'"
                            :id="`field-${field.key}`"
                            :name="`data[${field.key}]`"
                            :required="field.required"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:outline-none"
                        >
                            <option value="" :disabled="field.required">
                                Pick one
                            </option>
                            <option
                                v-for="option in field.options"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </option>
                        </select>

                        <label
                            v-else-if="field.type === 'checkbox'"
                            class="inline-flex items-center gap-2 text-sm"
                            :for="`field-${field.key}`"
                        >
                            <input
                                :id="`field-${field.key}`"
                                :name="`data[${field.key}]`"
                                type="checkbox"
                                value="1"
                                class="h-4 w-4 rounded border-input"
                            />
                            <span>{{ field.placeholder ?? 'Yes' }}</span>
                        </label>

                        <InputError :message="errors[`data.${field.key}`]" />
                    </div>
                </CardContent>

                <CardFooter class="justify-end">
                    <Button type="submit" :disabled="processing">
                        Submit response
                    </Button>
                </CardFooter>
            </Form>
        </Card>
    </div>
</template>
