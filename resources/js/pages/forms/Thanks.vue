<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    form: { id: number; title: string };
}>();

defineOptions({ layout: null });
</script>

<template>
    <Head title="Thanks!" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-10">
        <Card class="w-full max-w-lg">
            <CardHeader>
                <CardTitle>Thanks!</CardTitle>
                <CardDescription>
                    Your response to <strong>{{ form.title }}</strong> was recorded.
                </CardDescription>
            </CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                You can close this tab now.
            </CardContent>
        </Card>
    </div>
</template>
