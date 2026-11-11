<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Despesas" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Despesas</h1>

            <Link href="/expenses/create" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                Nova despesa
            </Link>
        </div>

        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">A lista de despesas estará disponível em breve.</p>
        </div>
    </div>
</template>
