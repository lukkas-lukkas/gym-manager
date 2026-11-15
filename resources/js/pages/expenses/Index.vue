<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Expense, Paginated } from '../../types/models';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** Month being shown, as `YYYY-MM`. */
    month: string;
    previousMonth: string;
    nextMonth: string;
    expenses: Paginated<Expense>;
}>();

/** ID of the expense whose payment is being sent, so only its button shows the loading state. */
const payingExpenseId = ref<number | null>(null);

/**
 * Registers the payment of an expense after the user confirms. The server redirects back here,
 * keeping the current month and page.
 */
function markAsPaid(expense: Expense): void {
    if (!window.confirm(`Registrar o pagamento de ${formatAmount(expense.amount_in_cents)} de ${expense.description}?`)) {
        return;
    }

    router.post(`/expenses/${expense.id}/pay`, {}, {
        preserveScroll: true,
        onStart: () => (payingExpenseId.value = expense.id),
        onFinish: () => (payingExpenseId.value = null),
    });
}

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' });

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

/**
 * The month as a title, e.g. `2026-11` → "Novembro de 2026".
 */
const monthLabel = computed((): string => {
    const [year, month] = props.month.split('-').map(Number);
    const label = monthFormatter.format(new Date(year, month - 1, 1));

    return label.charAt(0).toUpperCase() + label.slice(1);
});
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

        <nav class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-2" aria-label="Navegação por mês">
            <Link
                :href="`/expenses?month=${previousMonth}`"
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                aria-label="Mês anterior"
            >
                &larr; <span class="hidden sm:inline">Anterior</span>
            </Link>
            <span class="font-semibold text-gray-900" aria-live="polite">{{ monthLabel }}</span>
            <Link
                :href="`/expenses?month=${nextMonth}`"
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                aria-label="Próximo mês"
            >
                <span class="hidden sm:inline">Próximo</span> &rarr;
            </Link>
        </nav>

        <div v-if="expenses.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Nenhuma despesa neste mês.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="expense in expenses.data" :key="expense.id" class="flex items-start justify-between gap-4 p-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="font-medium text-gray-900">{{ expense.description }}</span>
                        <span class="text-sm text-gray-600">Vence em {{ formatDate(expense.due_on) }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="font-medium text-gray-900 tabular-nums">{{ formatAmount(expense.amount_in_cents) }}</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="expense.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                        >
                            {{ expense.status_label }}
                        </span>
                        <button
                            v-if="expense.status === 'pending'"
                            type="button"
                            :disabled="payingExpenseId === expense.id"
                            class="mt-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                            @click="markAsPaid(expense)"
                        >
                            Marcar como pago
                        </button>
                        <Link
                            :href="`/expenses/${expense.id}/edit`"
                            class="mt-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            Editar
                        </Link>
                    </div>
                </li>
            </ul>

            <!-- Table from tablet up; scrolls sideways instead of cutting the actions off when the screen is narrow -->
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">Descrição</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Vencimento</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Valor</th>
                            <th scope="col" class="px-6 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ expense.description }}</td>
                            <td class="px-6 py-4 text-gray-600 tabular-nums">{{ formatDate(expense.due_on) }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="expense.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ expense.status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-900 tabular-nums">{{ formatAmount(expense.amount_in_cents) }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-4">
                                    <button
                                        v-if="expense.status === 'pending'"
                                        type="button"
                                        :disabled="payingExpenseId === expense.id"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold whitespace-nowrap text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                                        :aria-label="`Marcar ${expense.description} como pago`"
                                        @click="markAsPaid(expense)"
                                    >
                                        Marcar como pago
                                    </button>
                                    <Link
                                        :href="`/expenses/${expense.id}/edit`"
                                        class="font-medium text-gray-700 hover:text-gray-900 hover:underline"
                                        :aria-label="`Editar ${expense.description}`"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="expenses.last_page > 1"
                class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm sm:px-6"
                aria-label="Paginação"
            >
                <span class="text-gray-600">{{ expenses.from }}–{{ expenses.to }} de {{ expenses.total }}</span>

                <div class="flex gap-2">
                    <Link
                        v-if="expenses.prev_page_url"
                        :href="expenses.prev_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="expenses.next_page_url"
                        :href="expenses.next_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Próxima
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
