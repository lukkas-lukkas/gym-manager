<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Paginated, Revenue } from '../../types/models';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** Month being shown, as `YYYY-MM`. */
    month: string;
    previousMonth: string;
    nextMonth: string;
    revenues: Paginated<Revenue>;
}>();

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
 * Label and colors of the status badge. "Atrasado" is a pending revenue past its due date.
 */
function statusBadge(revenue: Revenue): { label: string; classes: string } {
    if (revenue.status === 'paid') {
        return { label: revenue.status_label, classes: 'bg-green-100 text-green-800' };
    }

    if (revenue.is_overdue) {
        return { label: 'Atrasado', classes: 'bg-red-100 text-red-800' };
    }

    return { label: revenue.status_label, classes: 'bg-amber-100 text-amber-800' };
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
        <Head title="Receitas" />

        <h1 class="text-2xl font-semibold">Receitas</h1>

        <nav class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-2" aria-label="Navegação por mês">
            <Link
                :href="`/revenues?month=${previousMonth}`"
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                aria-label="Mês anterior"
            >
                &larr; <span class="hidden sm:inline">Anterior</span>
            </Link>
            <span class="font-semibold text-gray-900" aria-live="polite">{{ monthLabel }}</span>
            <Link
                :href="`/revenues?month=${nextMonth}`"
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                aria-label="Próximo mês"
            >
                <span class="hidden sm:inline">Próximo</span> &rarr;
            </Link>
        </nav>

        <div v-if="revenues.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Nenhuma receita neste mês.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="revenue in revenues.data" :key="revenue.id" class="flex items-start justify-between gap-4 p-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="font-medium text-gray-900">{{ revenue.member_name }}</span>
                        <span class="text-sm text-gray-600">{{ revenue.plan_name }}</span>
                        <span class="text-sm text-gray-600">Vence em {{ formatDate(revenue.due_on) }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="font-medium text-gray-900 tabular-nums">{{ formatAmount(revenue.price_in_cents) }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusBadge(revenue).classes">
                            {{ statusBadge(revenue).label }}
                        </span>
                        <Link
                            :href="`/members/${revenue.member_id}/enrollments`"
                            class="mt-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            Ver aluno
                        </Link>
                    </div>
                </li>
            </ul>

            <!-- Table from tablet up -->
            <table class="hidden w-full text-left text-sm md:table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">Aluno</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Plano</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Vencimento</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">Valor</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="revenue in revenues.data" :key="revenue.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ revenue.member_name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ revenue.plan_name }}</td>
                        <td class="px-6 py-4 text-gray-600 tabular-nums">{{ formatDate(revenue.due_on) }}</td>
                        <td class="px-6 py-4">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusBadge(revenue).classes">
                                {{ statusBadge(revenue).label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-gray-900 tabular-nums">{{ formatAmount(revenue.price_in_cents) }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link
                                :href="`/members/${revenue.member_id}/enrollments`"
                                class="font-medium text-gray-700 hover:text-gray-900 hover:underline"
                                :aria-label="`Ver planos de ${revenue.member_name}`"
                            >
                                Ver aluno
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <nav
                v-if="revenues.last_page > 1"
                class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm sm:px-6"
                aria-label="Paginação"
            >
                <span class="text-gray-600">{{ revenues.from }}–{{ revenues.to }} de {{ revenues.total }}</span>

                <div class="flex gap-2">
                    <Link
                        v-if="revenues.prev_page_url"
                        :href="revenues.prev_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="revenues.next_page_url"
                        :href="revenues.next_page_url"
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
