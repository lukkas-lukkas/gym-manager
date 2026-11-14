<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarChart, { type BarSeries } from '../../components/reports/BarChart.vue';
import ChartCard from '../../components/reports/ChartCard.vue';
import { seriesColors } from '../../components/reports/chart';
import AppLayout from '../../layouts/AppLayout.vue';
import type { MonthlyTotals } from '../../types/reports';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** First month of the period, as `YYYY-MM`. */
    from: string;
    /** Last month of the period, as `YYYY-MM`. */
    to: string;
    /** Largest number of months the period can cover. */
    maxMonths: number;
    /** Every month of the period as `YYYY-MM`; each report has one value per month in this order. */
    months: string[];
    /** Deferred: `undefined` until it loads. */
    revenues?: MonthlyTotals;
    /** Deferred: renewals that don't exist yet, in cents, one value per month. `undefined` until it loads. */
    revenueProjection?: number[];
    /** Deferred: `undefined` until it loads. */
    expenses?: MonthlyTotals;
}>();

/**
 * Whether every month of a report is zero, so the card shows "no data" instead of an empty chart.
 */
function isAllZero(totals: MonthlyTotals): boolean {
    return [...totals.realized, ...totals.forecast].every((amount) => amount === 0);
}

/**
 * The realized and forecast bars of a report, side by side in the same color (forecast lighter).
 */
function realizedVersusForecast(totals: MonthlyTotals | undefined, color: string): BarSeries[] {
    if (!totals) {
        return [];
    }

    return [
        { label: 'Realizado', values: totals.realized, color },
        { label: 'Previsto', values: totals.forecast, color, isForecast: true },
    ];
}

const revenueSeries = computed((): BarSeries[] => realizedVersusForecast(props.revenues, seriesColors.revenue));

const expenseSeries = computed((): BarSeries[] => realizedVersusForecast(props.expenses, seriesColors.expense));

const revenueProjectionSeries = computed((): BarSeries[] =>
    props.revenueProjection ? [{ label: 'Renovações projetadas', values: props.revenueProjection, color: seriesColors.revenue, isForecast: true }] : [],
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Relatórios" />

        <h1 class="text-2xl font-semibold">Relatórios</h1>

        <Form
            action="/reports"
            method="get"
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-start"
            #default="{ errors, processing }"
        >
            <div class="flex flex-1 flex-col gap-1">
                <label for="from" class="text-sm font-medium text-gray-700">De</label>
                <input
                    id="from"
                    name="from"
                    type="month"
                    required
                    :value="from"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                />
                <p v-if="errors.from" class="text-sm text-red-600">{{ errors.from }}</p>
            </div>

            <div class="flex flex-1 flex-col gap-1">
                <label for="to" class="text-sm font-medium text-gray-700">Até</label>
                <input
                    id="to"
                    name="to"
                    type="month"
                    required
                    :value="to"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                />
                <p v-if="errors.to" class="text-sm text-red-600">{{ errors.to }}</p>
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 sm:mt-6"
            >
                {{ processing ? 'Carregando...' : 'Aplicar' }}
            </button>
        </Form>

        <p class="-mt-3 text-sm text-gray-600">Escolha até {{ maxMonths }} meses.</p>

        <section class="flex flex-col gap-4" aria-labelledby="financial-title">
            <h2 id="financial-title" class="text-lg font-semibold text-gray-900">Financeiro</h2>

            <ChartCard
                title="Receitas"
                description="Realizado: o que foi pago no mês. Previsto: o que vence no mês, pago ou não."
                :is-loading="revenues === undefined"
                :is-empty="revenues !== undefined && isAllZero(revenues)"
                empty-message="Nenhuma receita no período."
            >
                <BarChart accessible-label="Receitas realizadas e previstas por mês" :months="months" :series="revenueSeries" />
            </ChartCard>

            <ChartCard
                title="Projeção de renovações"
                description="O que os alunos ativos devem pagar ao renovar o plano atual, pelo preço de hoje. Ainda não lançado em Receitas."
                :is-loading="revenueProjection === undefined"
                :is-empty="revenueProjection !== undefined && revenueProjection.every((amount) => amount === 0)"
                empty-message="Nenhuma renovação no período."
            >
                <BarChart accessible-label="Renovações projetadas por mês" :months="months" :series="revenueProjectionSeries" />
            </ChartCard>

            <ChartCard
                title="Despesas"
                description="Realizado: o que foi pago no mês. Previsto: o que vence no mês, pago ou não."
                :is-loading="expenses === undefined"
                :is-empty="expenses !== undefined && isAllZero(expenses)"
                empty-message="Nenhuma despesa no período."
            >
                <BarChart accessible-label="Despesas realizadas e previstas por mês" :months="months" :series="expenseSeries" />
            </ChartCard>
        </section>
    </div>
</template>
