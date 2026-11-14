<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import ChartDataTable from './ChartDataTable.vue';
import { formatCents, formatCentsCompact, formatMonth, gridColor } from './chart';

/**
 * One line of money over the months. `values` has one amount in cents per month, in the same order as
 * `months`; `null` leaves the month blank, so a realized line can stop where its forecast continues.
 */
export type LineSeries = {
    label: string;
    values: (number | null)[];
    color: string;
    /** Forecast lines are dashed, so realized × forecast reads at a glance even without color. */
    isForecast?: boolean;
};

/**
 * Lines of money per month on a single axis. The zero line is drawn darker, so a negative balance stands out.
 * A hidden table carries the same numbers for screen readers.
 */
const props = defineProps<{
    /** Months as `YYYY-MM`, in order. */
    months: string[];
    series: LineSeries[];
    /** Describes the chart for screen readers. */
    accessibleLabel: string;
}>();

const monthLabels = computed((): string[] => props.months.map(formatMonth));

const chartData = computed(
    (): ChartData<'line'> => ({
        labels: monthLabels.value,
        datasets: props.series.map((series) => ({
            label: series.label,
            data: series.values,
            borderColor: series.color,
            backgroundColor: series.color,
            borderWidth: 2,
            borderDash: series.isForecast ? [6, 4] : [],
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: series.isForecast ? '#ffffff' : series.color,
            tension: 0,
        })),
    }),
);

/**
 * Where a realized line hands over to its forecast, both have a point on the same month. Only the realized one
 * is listed in the tooltip, so the month isn't shown twice.
 */
function isRepeatedForecast(item: TooltipItem<'line'>, items: TooltipItem<'line'>[]): boolean {
    if (!props.series[item.datasetIndex]?.isForecast) {
        return false;
    }

    return items.some((other) => other.dataIndex === item.dataIndex && !props.series[other.datasetIndex]?.isForecast);
}

const chartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 2 } },
        tooltip: {
            filter: (item, _index, items) => item.parsed.y !== null && !isRepeatedForecast(item, items),
            callbacks: {
                label: (context) => `${context.dataset.label}: ${formatCents(context.parsed.y ?? 0)}`,
            },
        },
    },
    scales: {
        /** On narrow screens, months are skipped instead of tilted, so the labels stay readable. */
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
        y: {
            border: { display: false },
            grid: { color: (context) => (context.tick.value === 0 ? '#9ca3af' : gridColor) },
            ticks: { callback: (value) => formatCentsCompact(Number(value)) },
        },
    },
};
</script>

<template>
    <div class="relative h-72">
        <Line :data="chartData" :options="chartOptions" :aria-label="accessibleLabel" role="img" />

        <ChartDataTable :caption="accessibleLabel" :row-labels="monthLabels" :series="series" />
    </div>
</template>
