<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import ChartDataTable from './ChartDataTable.vue';
import { forecastAlpha, formatCents, formatCentsCompact, formatMonth, gridColor } from './chart';

/**
 * One group of bars: all of them share a color and a meaning (e.g. "Receitas realizadas").
 * `values` has one amount in cents per month, in the same order as `months`.
 */
export type BarSeries = {
    label: string;
    values: number[];
    color: string;
    /** Forecast bars use the same color, lighter, so realized × forecast reads at a glance. */
    isForecast?: boolean;
};

/**
 * Bars of money per month, one bar per series side by side. A hidden table carries the same numbers for
 * screen readers.
 */
const props = defineProps<{
    /** Months as `YYYY-MM`, in order. */
    months: string[];
    series: BarSeries[];
    /** Describes the chart for screen readers. */
    accessibleLabel: string;
}>();

const monthLabels = computed((): string[] => props.months.map(formatMonth));

const chartData = computed(
    (): ChartData<'bar'> => ({
        labels: monthLabels.value,
        datasets: props.series.map((series) => ({
            label: series.label,
            data: series.values,
            backgroundColor: series.isForecast ? `${series.color}${forecastAlpha}` : series.color,
            borderColor: series.color,
            borderWidth: series.isForecast ? 1 : 0,
            borderRadius: 4,
            borderSkipped: 'bottom',
            maxBarThickness: 28,
            /** Leaves a small gap between the bars of the same month, so realized and forecast don't merge. */
            barPercentage: 0.9,
        })),
    }),
);

/** A single series needs no legend: the card title already names it. */
const chartOptions = computed((): ChartOptions<'bar'> => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            display: props.series.length > 1,
            position: 'bottom',
            labels: { boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 2 },
        },
        tooltip: {
            callbacks: {
                label: (context) => `${context.dataset.label}: ${formatCents(context.parsed.y ?? 0)}`,
            },
        },
    },
    scales: {
        /** On narrow screens, months are skipped instead of tilted, so the labels stay readable. */
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: gridColor },
            ticks: { callback: (value) => formatCentsCompact(Number(value)) },
        },
    },
}));
</script>

<template>
    <div class="relative h-72">
        <Bar :data="chartData" :options="chartOptions" :aria-label="accessibleLabel" role="img" />

        <ChartDataTable :caption="accessibleLabel" :row-labels="monthLabels" :series="series" />
    </div>
</template>
