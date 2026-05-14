<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import LocationsController from '@/actions/App/Http/Controllers/Settings/LocationsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { index as locationsIndex } from '@/routes/locations';

type Location = {
    id: number;
    name: string;
    address: string | null;
    maps_link: string | null;
};

defineProps<{ locations: Location[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Locations',
                href: locationsIndex(),
            },
        ],
    },
});

const dialogOpen = ref(false);
const editing = ref<Location | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(location: Location) {
    editing.value = location;
    dialogOpen.value = true;
}

function dialogTitle() {
    return editing.value ? `Edit ${editing.value.name}` : 'New location';
}
</script>

<template>
    <Head title="Locations" />

    <div class="flex flex-col space-y-6 px-4 py-6 md:px-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Locations"
                description="Fields, parks, and facilities your teams play and practice at."
            />
            <Button
                type="button"
                @click="openCreate"
                data-test="create-location"
            >
                New location
            </Button>
        </div>

        <div
            v-if="locations.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            data-test="locations-empty"
        >
            No locations yet. Add one before you schedule games or practices.
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="location in locations"
                :key="location.id"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                :data-test="`location-row-${location.id}`"
            >
                <div class="space-y-1">
                    <span class="font-medium">{{ location.name }}</span>
                    <p
                        v-if="location.address"
                        class="text-xs text-muted-foreground"
                    >
                        {{ location.address }}
                    </p>
                    <a
                        v-if="location.maps_link"
                        :href="location.maps_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-muted-foreground underline hover:text-foreground"
                    >
                        View on map
                    </a>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="openEdit(location)"
                    >
                        Edit
                    </Button>
                    <Form
                        v-bind="LocationsController.destroy.form(location.id)"
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
                    <DialogTitle>{{ dialogTitle() }}</DialogTitle>
                    <DialogDescription>
                        Locations persist across seasons. Schedule games and
                        practices to a known field.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="
                        editing
                            ? LocationsController.update.form(editing.id)
                            : LocationsController.store.form()
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="dialogOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="editing?.name ?? ''"
                            required
                            autocomplete="off"
                            placeholder="Main Park Field 1"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            name="address"
                            :default-value="editing?.address ?? ''"
                            autocomplete="off"
                            placeholder="123 Main St, Cary, NC"
                        />
                        <InputError :message="errors.address" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="maps_link">Map link</Label>
                        <Input
                            id="maps_link"
                            type="url"
                            name="maps_link"
                            :default-value="editing?.maps_link ?? ''"
                            autocomplete="off"
                            placeholder="https://maps.google.com/?q=..."
                        />
                        <InputError :message="errors.maps_link" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" as-child>
                            <Link
                                :href="locationsIndex()"
                                @click.prevent="dialogOpen = false"
                            >
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ editing ? 'Save changes' : 'Create location' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
