<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import ChartDataTable from './ChartDataTable.vue';
import { gridColor } from './chart';

/**
 * One horizontal bar per category (e.g. one per plan), for counts rather than money over time.
 *
 * Horizontal bars keep long names readable and stay legible with many categories, where a pie would not. The chart
 * grows with the number of bars, so each one keeps the same thickness. A hidden table carries the same numbers.
 */
const props = defineProps<{
    /** One label per bar, in display order (top to bottom). */
    labels: string[];
    values: number[];
    color: string;
    /** Name of what is counted, used in the hidden table header (e.g. "Matrículas ativas"). */
    valueLabel: string;
    /** Formats one value in the tooltip (e.g. `3` → `3 matrículas`). */
    formatValue: (value: number) => string;
    /** Title of the category column in the hidden table (e.g. "Plano"). */
    categoryLabel: string;
    /** Describes the chart for screen readers. */
    accessibleLabel: string;
}>();

/** Height of each bar row, in pixels, plus room for the axis. */
const rowHeightInPixels = 44;
const axisHeightInPixels = 40;

const chartHeight = computed((): string => `${props.labels.length * rowHeightInPixels + axisHeightInPixels}px`);

const chartData = computed(
    (): ChartData<'bar'> => ({
        labels: props.labels,
        datasets: [
            {
                label: props.valueLabel,
                data: props.values,
                backgroundColor: props.color,
                borderRadius: 4,
                borderSkipped: 'start',
                maxBarThickness: 24,
            },
        ],
    }),
);

const chartOptions = computed(
    (): ChartOptions<'bar'> => ({
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => props.formatValue(context.parsed.x ?? 0),
                },
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: gridColor },
                /** Counts are whole numbers: no 0,5 on the axis. */
                ticks: { precision: 0 },
            },
            y: { grid: { display: false } },
        },
    }),
);
</script>

<template>
    <div class="relative" :style="{ height: chartHeight }">
        <Bar :data="chartData" :options="chartOptions" :aria-label="accessibleLabel" role="img" />

        <ChartDataTable
            :caption="accessibleLabel"
            :row-labels="labels"
            :row-header="categoryLabel"
            :series="[{ label: valueLabel, values }]"
            :format-value="(value: number) => String(value)"
        />
    </div>
</template>
