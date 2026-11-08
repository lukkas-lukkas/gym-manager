<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <Head title="Dashboard" />

        <h1 class="text-2xl font-semibold">Dashboard</h1>
    </div>
</template>
