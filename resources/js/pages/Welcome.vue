<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    laravelVersion: string;
}>();

const page = usePage();
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900">
        <Head title="Welcome" />

        <div class="flex flex-col items-center gap-4 text-center">
            <h1 class="text-3xl font-semibold">{{ page.props.name }}</h1>
            <p class="text-gray-600">Inertia + Vue 3 is running on Laravel {{ laravelVersion }}.</p>
            <Link
                href="/logout"
                method="post"
                as="button"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
            >
                Sair
            </Link>
        </div>
    </main>
</template>
