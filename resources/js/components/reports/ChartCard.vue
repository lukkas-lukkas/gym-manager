<script setup lang="ts">
/**
 * Frame of every report chart: title, a pulsing skeleton while the deferred data loads, and a message when
 * there is nothing to show. The chart itself goes in the default slot.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /** True while the deferred prop has not arrived yet. */
        isLoading: boolean;
        /** True when the data arrived but every value is zero. */
        isEmpty?: boolean;
        emptyMessage?: string;
    }>(),
    {
        description: undefined,
        isEmpty: false,
        emptyMessage: 'Sem dados no período.',
    },
);

/** Heights of the skeleton bars, in percent, so the placeholder already looks like a chart. */
const skeletonBarHeights = [45, 70, 55, 85, 60, 75, 40, 65];
</script>

<template>
    <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" :aria-busy="isLoading">
        <header class="flex flex-col gap-1">
            <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
            <p v-if="description" class="text-sm text-gray-600">{{ description }}</p>
        </header>

        <div v-if="isLoading" class="flex h-72 animate-pulse items-end gap-3" aria-label="Carregando gráfico">
            <div v-for="(height, index) in skeletonBarHeights" :key="index" class="flex-1 rounded-t bg-gray-200" :style="{ height: `${height}%` }" />
        </div>

        <div v-else-if="isEmpty" class="flex h-72 items-center justify-center rounded-xl border border-dashed border-gray-300">
            <p class="text-gray-600">{{ emptyMessage }}</p>
        </div>

        <slot v-else />
    </section>
</template>
