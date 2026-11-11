<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { MembershipPlan, Paginated } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    plans: Paginated<MembershipPlan>;
}>();

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Formats an amount in cents as Brazilian currency (e.g. `9990` → `R$ 99,90`).
 */
function formatPrice(cents: number): string {
    return currencyFormatter.format(cents / 100);
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Planos" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Planos</h1>

            <Link
                href="/membership-plans/create"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
            >
                Novo plano
            </Link>
        </div>

        <div v-if="plans.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Nenhum plano cadastrado.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="plan in plans.data" :key="plan.id" class="flex items-start justify-between gap-4 p-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="font-medium text-gray-900">{{ plan.name }}</span>
                        <span class="text-sm text-gray-600">
                            {{ formatPrice(plan.price_in_cents) }} · {{ plan.billing_period_label }}
                        </span>
                    </div>
                    <Link
                        :href="`/membership-plans/${plan.id}/edit`"
                        class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Editar
                    </Link>
                </li>
            </ul>

            <!-- Table from tablet up -->
            <table class="hidden w-full text-left text-sm md:table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">Nome</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Período</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">Preço</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="plan in plans.data" :key="plan.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ plan.name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ plan.billing_period_label }}</td>
                        <td class="px-6 py-4 text-right text-gray-900 tabular-nums">{{ formatPrice(plan.price_in_cents) }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link
                                :href="`/membership-plans/${plan.id}/edit`"
                                class="font-medium text-gray-700 hover:text-gray-900 hover:underline"
                                :aria-label="`Editar ${plan.name}`"
                            >
                                Editar
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <nav
                v-if="plans.last_page > 1"
                class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm sm:px-6"
                aria-label="Paginação"
            >
                <span class="text-gray-600">{{ plans.from }}–{{ plans.to }} de {{ plans.total }}</span>

                <div class="flex gap-2">
                    <Link
                        v-if="plans.prev_page_url"
                        :href="plans.prev_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="plans.next_page_url"
                        :href="plans.next_page_url"
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
