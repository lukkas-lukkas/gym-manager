<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

defineProps<{
    laravelVersion: string;
}>();

const page = usePage();
</script>

<template>
    <Head title="Welcome" />

    <main class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900">
        <div class="text-center">
            <h1 class="text-3xl font-semibold">{{ page.props.name }}</h1>
            <p class="mt-2 text-gray-600">Inertia + Vue 3 is running on Laravel {{ laravelVersion }}.</p>
        </div>
    </main>
</template>
