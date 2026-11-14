<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import type { CashFlow } from '../../types/reports';
import ChartDataTable from './ChartDataTable.vue';
import { forecastAlpha, formatCents, formatCentsCompact, formatMonth, gridColor, seriesColors } from './chart';

/**
 * Inflow and outflow bars per month, with the balance at the end of each month as a line on the same axis.
 *
 * From the current month on everything is a forecast: the bars get lighter and the balance line turns dashed.
 * The zero line is drawn darker, so a negative balance stands out. A hidden table carries the same numbers.
 */
const props = defineProps<{
    /** Months as `YYYY-MM`, in order. */
    months: string[];
    cashFlow: CashFlow;
    /** Describes the chart for screen readers. */
    accessibleLabel: string;
}>();

const monthLabels = computed((): string[] => props.months.map(formatMonth));

/**
 * Solid for the months already closed, lighter from the current month on (forecast).
 */
function barColors(color: string): string[] {
    return props.months.map((month) => (month < props.cashFlow.currentMonth ? color : `${color}${forecastAlpha}`));
}

const chartData = computed(
    () =>
        ({
            labels: monthLabels.value,
            datasets: [
                {
                    type: 'line',
                    label: 'Saldo',
                    data: props.cashFlow.realizedBalance,
                    borderColor: seriesColors.balance,
                    backgroundColor: seriesColors.balance,
                    borderWidth: 2,
                    pointRadius: 4,
                    order: 0,
                },
                {
                    type: 'line',
                    label: 'Saldo previsto',
                    data: props.cashFlow.forecastBalance,
                    borderColor: seriesColors.balance,
                    backgroundColor: '#ffffff',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 4,
                    order: 0,
                },
                {
                    type: 'bar',
                    label: 'Entradas',
                    data: props.cashFlow.inflow,
                    backgroundColor: barColors(seriesColors.revenue),
                    borderColor: seriesColors.revenue,
                    borderRadius: 4,
                    maxBarThickness: 28,
                    barPercentage: 0.9,
                    order: 1,
                },
                {
                    type: 'bar',
                    label: 'Saídas',
                    data: props.cashFlow.outflow,
                    backgroundColor: barColors(seriesColors.expense),
                    borderColor: seriesColors.expense,
                    borderRadius: 4,
                    maxBarThickness: 28,
                    barPercentage: 0.9,
                    order: 1,
                },
            ],
        }) as unknown as ChartData<'bar'>,
);

/** Index of the real and of the forecast balance lines in the datasets above. */
const realizedBalanceIndex = 0;
const forecastBalanceIndex = 1;

/**
 * At the end of last month the forecast line repeats the real point (that is where they join): only the real one is listed.
 */
function isRepeatedForecastBalance(item: TooltipItem<'bar'>, items: TooltipItem<'bar'>[]): boolean {
    return item.datasetIndex === forecastBalanceIndex && items.some((other) => other.dataIndex === item.dataIndex && other.datasetIndex === realizedBalanceIndex);
}

const chartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
        tooltip: {
            filter: (item, _index, items) => item.parsed.y !== null && !isRepeatedForecastBalance(item, items),
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

const tableSeries = computed(() => [
    { label: 'Entradas', values: props.cashFlow.inflow },
    { label: 'Saídas', values: props.cashFlow.outflow },
    { label: 'Saldo', values: props.cashFlow.realizedBalance },
    { label: 'Saldo previsto', values: props.cashFlow.forecastBalance },
]);
</script>

<template>
    <div class="relative h-80">
        <Bar :data="chartData" :options="chartOptions" :aria-label="accessibleLabel" role="img" />

        <ChartDataTable :caption="accessibleLabel" :row-labels="monthLabels" :series="tableSeries" />
    </div>
</template>
