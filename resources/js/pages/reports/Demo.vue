<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BarChart, { type BarSeries } from '../../components/reports/BarChart.vue';
import ChartCard from '../../components/reports/ChartCard.vue';
import LineChart, { type LineSeries } from '../../components/reports/LineChart.vue';
import { seriesColors } from '../../components/reports/chart';
import AppLayout from '../../layouts/AppLayout.vue';

/**
 * Local-only showcase of the report chart components, filled with made-up numbers.
 * Used to review the look of the charts before they are wired to real data.
 */
defineOptions({ layout: AppLayout });

const months = ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05'];

/** Realized amounts only exist up to the current month; the future months are zero. */
const realizedRevenues = [1_200_000, 1_350_000, 1_280_000, 1_410_000, 1_500_000, 820_000, 0, 0, 0, 0, 0, 0];
const forecastRevenues = [1_300_000, 1_400_000, 1_350_000, 1_450_000, 1_550_000, 1_600_000, 1_650_000, 1_700_000, 1_680_000, 1_720_000, 1_760_000, 1_800_000];
const realizedExpenses = [780_000, 810_000, 950_000, 790_000, 820_000, 410_000, 0, 0, 0, 0, 0, 0];
const forecastExpenses = [800_000, 800_000, 900_000, 800_000, 830_000, 840_000, 980_000, 850_000, 850_000, 860_000, 860_000, 870_000];

const revenueSeries: BarSeries[] = [
    { label: 'Realizado', values: realizedRevenues, color: seriesColors.revenue },
    { label: 'Previsto', values: forecastRevenues, color: seriesColors.revenue, isForecast: true },
];

const expenseSeries: BarSeries[] = [
    { label: 'Realizado', values: realizedExpenses, color: seriesColors.expense },
    { label: 'Previsto', values: forecastExpenses, color: seriesColors.expense, isForecast: true },
];

const revenueVersusExpenseSeries: BarSeries[] = [
    { label: 'Receitas previstas', values: forecastRevenues, color: seriesColors.revenue, isForecast: true },
    { label: 'Despesas previstas', values: forecastExpenses, color: seriesColors.expense, isForecast: true },
];

/** Index of the current month (`2026-11`): the realized line ends here and the forecast line starts here. */
const currentMonthIndex = 5;

/**
 * Running total of `revenues - expenses`, month by month, starting from `startingBalance`.
 */
function runningBalance(revenues: number[], expenses: number[], startingBalance = 0): number[] {
    let balance = startingBalance;

    return revenues.map((revenue, index) => (balance += revenue - expenses[index]));
}

const realizedBalance = runningBalance(realizedRevenues.slice(0, currentMonthIndex + 1), realizedExpenses.slice(0, currentMonthIndex + 1));
const forecastBalance = runningBalance(
    forecastRevenues.slice(currentMonthIndex + 1),
    forecastExpenses.slice(currentMonthIndex + 1),
    realizedBalance[currentMonthIndex],
);

/**
 * The realized line stops at the current month and the forecast picks up from that same point, so the two read as
 * one line that turns dashed. Months outside each part are `null` (blank).
 */
const balanceSeries: LineSeries[] = [
    {
        label: 'Saldo realizado',
        values: [...realizedBalance, ...forecastBalance.map(() => null)],
        color: seriesColors.balance,
    },
    {
        label: 'Saldo previsto',
        values: [...realizedBalance.map((balance, index) => (index === currentMonthIndex ? balance : null)), ...forecastBalance],
        color: seriesColors.balance,
        isForecast: true,
    },
];

const revenueVersusExpenseLineSeries: LineSeries[] = [
    { label: 'Receitas previstas', values: forecastRevenues, color: seriesColors.revenue, isForecast: true },
    { label: 'Despesas previstas', values: forecastExpenses, color: seriesColors.expense, isForecast: true },
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Relatórios (demo)" />

        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold">Relatórios (demo)</h1>
            <p class="text-sm text-gray-600">Valores fictícios, só para ver como os gráficos ficam. Esta página existe apenas no ambiente local.</p>
        </header>

        <ChartCard title="Receitas × despesas previstas" description="Comparação mês a mês, base do futuro fluxo de caixa" :is-loading="false">
            <BarChart accessible-label="Receitas e despesas previstas por mês" :months="months" :series="revenueVersusExpenseSeries" />
        </ChartCard>

        <div class="grid gap-6 lg:grid-cols-2">
            <ChartCard title="Receitas" description="Realizado × previsto por mês" :is-loading="false">
                <BarChart accessible-label="Receitas realizadas e previstas por mês" :months="months" :series="revenueSeries" />
            </ChartCard>

            <ChartCard title="Despesas" description="Realizado × previsto por mês" :is-loading="false">
                <BarChart accessible-label="Despesas realizadas e previstas por mês" :months="months" :series="expenseSeries" />
            </ChartCard>
        </div>

        <ChartCard title="Saldo acumulado" description="Linha contínua até hoje, tracejada no previsto" :is-loading="false">
            <LineChart accessible-label="Saldo acumulado realizado e previsto por mês" :months="months" :series="balanceSeries" />
        </ChartCard>

        <ChartCard title="Receitas × despesas previstas (linhas)" description="As mesmas séries do primeiro gráfico, em linha" :is-loading="false">
            <LineChart accessible-label="Receitas e despesas previstas por mês, em linha" :months="months" :series="revenueVersusExpenseLineSeries" />
        </ChartCard>

        <div class="grid gap-6 lg:grid-cols-2">
            <ChartCard title="Carregando" description="Como o card aparece enquanto os dados chegam" :is-loading="true" />

            <ChartCard title="Sem dados" description="Como o card aparece quando o período está vazio" :is-loading="false" :is-empty="true" />
        </div>
    </div>
</template>
