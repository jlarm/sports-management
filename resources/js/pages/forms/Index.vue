<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormsController from '@/actions/App/Http/Controllers/Forms/FormsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as formsIndex, edit as formEdit } from '@/routes/forms';

type FormSummary = {
    id: number;
    title: string;
    description: string | null;
    status: 'draft' | 'published' | 'closed';
    status_label: string;
    schema: { fields: Array<{ key: string }> };
    schema_version: number;
};

defineProps<{ forms: FormSummary[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Forms', href: formsIndex() }],
    },
});

const dialogOpen = ref(false);

const statusVariant: Record<
    FormSummary['status'],
    'default' | 'secondary' | 'outline'
> = {
    draft: 'outline',
    published: 'default',
    closed: 'secondary',
};
</script>

<template>
    <Head title="Forms" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Registration forms"
                description="Build dynamic intake forms. Draft to design, publish to collect submissions, close when done."
            />
            <Button type="button" @click="dialogOpen = true" data-test="create-form">
                New form
            </Button>
        </div>

        <div
            v-if="forms.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="forms-empty"
        >
            No forms yet. Create one to start collecting registrations.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="form in forms"
                :key="form.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`form-row-${form.id}`"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ form.title }}</span>
                        <Badge :variant="statusVariant[form.status]">
                            {{ form.status_label }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            v{{ form.schema_version }} ·
                            {{ form.schema.fields.length }} fields
                        </span>
                    </div>
                    <p v-if="form.description" class="text-xs text-muted-foreground">
                        {{ form.description }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="ghost">
                        <Link :href="formEdit(form.id)">Edit</Link>
                    </Button>
                    <Form
                        v-if="form.status === 'draft'"
                        v-bind="FormsController.publish.form(form.id)"
                        class="inline"
                        v-slot="{ processing }"
                    >
                        <Button type="submit" variant="secondary" :disabled="processing">
                            Publish
                        </Button>
                    </Form>
                    <Form
                        v-if="form.status === 'published'"
                        v-bind="FormsController.close.form(form.id)"
                        class="inline"
                        v-slot="{ processing }"
                    >
                        <Button type="submit" variant="secondary" :disabled="processing">
                            Close
                        </Button>
                    </Form>
                    <Form
                        v-bind="FormsController.destroy.form(form.id)"
                        class="inline"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="processing"
                        >
                            Archive
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New form</DialogTitle>
                    <DialogDescription>
                        Forms start as drafts. You can add fields after creating.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="FormsController.store.form()"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <input type="hidden" name="schema[fields][0][key]" value="first_name" />
                    <input type="hidden" name="schema[fields][0][label]" value="First name" />
                    <input type="hidden" name="schema[fields][0][type]" value="text" />
                    <input type="hidden" name="schema[fields][0][required]" value="1" />
                    <input type="hidden" name="schema[fields][1][key]" value="last_name" />
                    <input type="hidden" name="schema[fields][1][label]" value="Last name" />
                    <input type="hidden" name="schema[fields][1][type]" value="text" />
                    <input type="hidden" name="schema[fields][1][required]" value="1" />

                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            required
                            autocomplete="off"
                            placeholder="2026 Spring Registration"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus:outline-none"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="dialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Create draft
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
