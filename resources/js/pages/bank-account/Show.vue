<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { BankAccount } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    /** `null` while the gym has not registered its account yet. */
    account: BankAccount | null;
}>();

const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' });

/**
 * Formats an amount in cents as Brazilian currency (e.g. `9990` → `R$ 99,90`).
 */
function formatAmount(cents: number): string {
    return currencyFormatter.format(cents / 100);
}

/**
 * Formats an ISO timestamp as Brasília date and time (e.g. `14/11/2026, 13:26`).
 */
function formatDateTime(isoTimestamp: string): string {
    return dateTimeFormatter.format(new Date(isoTimestamp));
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Conta bancária" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Conta bancária</h1>
            <Link
                v-if="account"
                href="/bank-account/edit"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100"
            >
                Editar conta
            </Link>
        </div>

        <div v-if="!account" class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Cadastre a conta bancária da academia para acompanhar o saldo.</p>
            <Link href="/bank-account/create" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                Cadastrar conta
            </Link>
        </div>

        <section v-else class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6" aria-labelledby="account-name">
            <h2 id="account-name" class="text-lg font-semibold text-gray-900">{{ account.name }}</h2>

            <div class="flex flex-col gap-1">
                <span class="text-sm text-gray-600">Saldo atual</span>
                <span class="text-3xl font-semibold tabular-nums" :class="account.current_balance_in_cents < 0 ? 'text-red-700' : 'text-gray-900'">
                    {{ formatAmount(account.current_balance_in_cents) }}
                </span>
            </div>

            <dl class="grid gap-4 border-t border-gray-200 pt-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-gray-600">Saldo inicial</dt>
                    <dd class="font-medium text-gray-900 tabular-nums">{{ formatAmount(account.opening_balance_in_cents) }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm text-gray-600">Saldo inicial informado em</dt>
                    <dd class="font-medium text-gray-900 tabular-nums">{{ formatDateTime(account.opened_at) }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
