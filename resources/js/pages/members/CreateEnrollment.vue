<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Member, MembershipPlan } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    member: Pick<Member, 'id' | 'name'>;
    /** The member's active or upcoming plan that ends last (the new plan starts after it), or `null` when there is none. */
    previousEnrollment: { plan_name: string; ends_on: string } | null;
    /** Date in `YYYY-MM-DD` format: the day after `previousEnrollment` ends, or today when there is none. */
    startsOn: string;
    plans: MembershipPlan[];
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
        <Head :title="`${member.name} - Novo plano`" />

        <div class="flex flex-col gap-1">
            <Link :href="`/members/${member.id}/enrollments`" class="text-sm text-gray-600 hover:text-gray-900">
                &larr; Voltar para os planos de {{ member.name }}
            </Link>
            <h1 class="text-2xl font-semibold">Matricular em novo plano</h1>
        </div>

        <div class="flex max-w-2xl flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <p v-if="previousEnrollment" class="text-sm text-gray-600">
                Último plano agendado: <strong class="text-gray-900">{{ previousEnrollment.plan_name }}</strong>, até
                {{ formatDate(previousEnrollment.ends_on) }}.
            </p>
            <p v-else class="text-sm text-gray-600">Este aluno não tem um plano ativo.</p>

            <div class="flex flex-col gap-1">
                <span class="text-sm font-medium text-gray-700">Data de início do novo plano</span>
                <span class="text-lg font-semibold text-gray-900 tabular-nums">{{ formatDate(startsOn) }}</span>
            </div>
        </div>

        <div v-if="plans.length === 0" class="max-w-2xl rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Não há planos ativos.</p>
            <Link href="/membership-plans/create" class="mt-2 inline-block text-sm font-semibold text-gray-900 underline">Cadastrar plano</Link>
        </div>

        <Form
            v-else
            :action="`/members/${member.id}/enrollments`"
            method="post"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <fieldset class="flex flex-col gap-3">
                <legend class="mb-3 text-base font-semibold text-gray-900">Plano</legend>

                <label
                    v-for="plan in plans"
                    :key="plan.id"
                    class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-300 p-4 hover:bg-gray-50 has-checked:border-gray-900"
                >
                    <input type="radio" name="membership_plan_id" :value="plan.id" required class="size-4 accent-gray-900" />
                    <span class="flex flex-1 flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-gray-900">{{ plan.name }}</span>
                        <span class="text-sm text-gray-600 tabular-nums">{{ formatPrice(plan.price_in_cents) }}</span>
                    </span>
                </label>

                <p v-if="errors.membership_plan_id" class="text-sm text-red-600">{{ errors.membership_plan_id }}</p>
            </fieldset>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="`/members/${member.id}/enrollments`"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-100"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {{ processing ? 'Salvando...' : 'Confirmar matrícula' }}
                </button>
            </div>
        </Form>
    </div>
</template>
