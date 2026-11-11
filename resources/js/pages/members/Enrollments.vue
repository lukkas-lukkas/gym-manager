<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MemberProfileHeader from '../../components/members/MemberProfileHeader.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Member, MemberEnrollment, Paginated } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    member: Pick<Member, 'id' | 'name' | 'deactivated_at'>;
    enrollments: Paginated<MemberEnrollment>;
}>();

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Formats an amount in cents as Brazilian currency (e.g. `9990` → `R$ 99,90`).
 */
function formatPrice(cents: number): string {
    return currencyFormatter.format(cents / 100);
}

/**
 * Formats a `YYYY-MM-DD` date as `DD/MM/YYYY` without going through `Date`, avoiding timezone shifts.
 */
function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="`${member.name} - Planos`" />

        <MemberProfileHeader :member="member" active-tab="enrollments" />

        <div class="flex justify-end">
            <Link
                :href="`/members/${member.id}/enrollments/create`"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
            >
                Matricular em novo plano
            </Link>
        </div>

        <div v-if="enrollments.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Este aluno ainda não foi matriculado em nenhum plano.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="enrollment in enrollments.data" :key="enrollment.id" class="flex flex-col gap-1 p-4">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ enrollment.plan_name }}</span>
                        <span v-if="enrollment.status === 'active'" class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Atual</span>
                    </div>
                    <span class="text-sm text-gray-600">
                        {{ formatPrice(enrollment.price_in_cents) }} · {{ enrollment.billing_period_label }}
                    </span>
                    <span class="text-sm text-gray-500">
                        {{ formatDate(enrollment.starts_on) }} até {{ formatDate(enrollment.ends_on) }}
                    </span>
                </li>
            </ul>

            <!-- Table from tablet up -->
            <table class="hidden w-full text-left text-sm md:table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">Plano</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Período</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">Preço</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Início</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Fim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="enrollment in enrollments.data" :key="enrollment.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            <div class="flex items-center gap-2">
                                {{ enrollment.plan_name }}
                                <span
                                    v-if="enrollment.status === 'active'"
                                    class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800"
                                >
                                    Atual
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ enrollment.billing_period_label }}</td>
                        <td class="px-6 py-4 text-right text-gray-900 tabular-nums">{{ formatPrice(enrollment.price_in_cents) }}</td>
                        <td class="px-6 py-4 text-gray-600 tabular-nums">{{ formatDate(enrollment.starts_on) }}</td>
                        <td class="px-6 py-4 text-gray-600 tabular-nums">{{ formatDate(enrollment.ends_on) }}</td>
                    </tr>
                </tbody>
            </table>

            <nav
                v-if="enrollments.last_page > 1"
                class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm sm:px-6"
                aria-label="Paginação"
            >
                <span class="text-gray-600">{{ enrollments.from }}–{{ enrollments.to }} de {{ enrollments.total }}</span>

                <div class="flex gap-2">
                    <Link
                        v-if="enrollments.prev_page_url"
                        :href="enrollments.prev_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="enrollments.next_page_url"
                        :href="enrollments.next_page_url"
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
