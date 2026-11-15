<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import type { DashboardSummary } from '../types/reports';

defineOptions({ layout: AppLayout });

defineProps<{
    summary: DashboardSummary;
    /** How many days ahead the "contas a pagar" list looks. */
    upcomingExpenseDays: number;
}>();

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Formats an amount in cents as Brazilian currency (e.g. `9990` → `R$ 99,90`).
 */
function formatAmount(cents: number): string {
    return currencyFormatter.format(cents / 100);
}

/**
 * Formats a `YYYY-MM-DD` date as `DD/MM/YYYY` without going through `Date`, so the timezone can't shift the day.
 */
function formatDate(date: string): string {
    return date.split('-').reverse().join('/');
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Dashboard" />

        <h1 class="text-2xl font-semibold">Dashboard</h1>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo">
            <div class="flex flex-col gap-1 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <span class="text-sm font-medium text-gray-600">Saldo da conta</span>
                <template v-if="summary.balanceInCents !== null">
                    <span class="text-2xl font-semibold tabular-nums" :class="summary.balanceInCents < 0 ? 'text-red-600' : 'text-gray-900'">
                        {{ formatAmount(summary.balanceInCents) }}
                    </span>
                </template>
                <Link v-else href="/bank-account" class="text-sm font-medium text-gray-900 underline">Cadastre a conta bancária</Link>
            </div>

            <Link href="/members" class="flex flex-col gap-1 rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 sm:p-6">
                <span class="text-sm font-medium text-gray-600">Alunos ativos</span>
                <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ summary.activeMembers }}</span>
            </Link>

            <Link href="/revenues" class="flex flex-col gap-1 rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 sm:p-6">
                <span class="text-sm font-medium text-gray-600">Recebido no mês</span>
                <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ formatAmount(summary.month.receivedInCents) }}</span>
                <span class="text-sm text-gray-600 tabular-nums">A receber: {{ formatAmount(summary.month.toReceiveInCents) }}</span>
            </Link>

            <Link href="/expenses" class="flex flex-col gap-1 rounded-2xl border border-gray-200 bg-white p-4 hover:bg-gray-50 sm:p-6">
                <span class="text-sm font-medium text-gray-600">Pago no mês</span>
                <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ formatAmount(summary.month.paidInCents) }}</span>
                <span class="text-sm text-gray-600 tabular-nums">A pagar: {{ formatAmount(summary.month.toPayInCents) }}</span>
            </Link>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" aria-labelledby="overdue-revenues-title">
                <header class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 id="overdue-revenues-title" class="text-lg font-semibold text-gray-900">Mensalidades atrasadas</h2>
                        <p v-if="summary.overdueRevenues.count > 0" class="text-sm text-gray-600">
                            {{ summary.overdueRevenues.count }} {{ summary.overdueRevenues.count === 1 ? 'mensalidade' : 'mensalidades' }} ·
                            <span class="font-medium text-red-600 tabular-nums">{{ formatAmount(summary.overdueRevenues.totalInCents) }}</span>
                        </p>
                    </div>
                    <Link href="/revenues" class="shrink-0 text-sm font-medium text-gray-900 hover:underline">Ver receitas</Link>
                </header>

                <p v-if="summary.overdueRevenues.count === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-600">
                    Nenhuma mensalidade atrasada.
                </p>

                <ul v-else class="divide-y divide-gray-200">
                    <li v-for="revenue in summary.overdueRevenues.oldest" :key="revenue.id" class="flex items-center justify-between gap-4 py-3">
                        <div class="flex min-w-0 flex-col">
                            <Link :href="`/members/${revenue.member_id}`" class="truncate font-medium text-gray-900 hover:underline">
                                {{ revenue.member_name }}
                            </Link>
                            <span class="text-sm text-gray-600 tabular-nums">Venceu em {{ formatDate(revenue.due_on) }}</span>
                        </div>
                        <span class="shrink-0 font-medium text-gray-900 tabular-nums">{{ formatAmount(revenue.price_in_cents) }}</span>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" aria-labelledby="upcoming-expenses-title">
                <header class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 id="upcoming-expenses-title" class="text-lg font-semibold text-gray-900">Contas a pagar</h2>
                        <p class="text-sm text-gray-600">Atrasadas e as que vencem nos próximos {{ upcomingExpenseDays }} dias.</p>
                    </div>
                    <Link href="/expenses" class="shrink-0 text-sm font-medium text-gray-900 hover:underline">Ver despesas</Link>
                </header>

                <p v-if="summary.upcomingExpenses.length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-600">
                    Nenhuma conta para os próximos dias.
                </p>

                <ul v-else class="divide-y divide-gray-200">
                    <li v-for="expense in summary.upcomingExpenses" :key="expense.id" class="flex items-center justify-between gap-4 py-3">
                        <div class="flex min-w-0 flex-col">
                            <span class="truncate font-medium text-gray-900">{{ expense.description }}</span>
                            <span class="text-sm tabular-nums" :class="expense.is_overdue ? 'font-medium text-red-600' : 'text-gray-600'">
                                {{ expense.is_overdue ? 'Venceu em' : 'Vence em' }} {{ formatDate(expense.due_on) }}
                            </span>
                        </div>
                        <span class="shrink-0 font-medium text-gray-900 tabular-nums">{{ formatAmount(expense.amount_in_cents) }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
