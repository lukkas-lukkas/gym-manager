<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MoneyInput from '../../components/MoneyInput.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { BankAccount } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    account: BankAccount;
    /** Largest opening balance accepted, in cents. */
    maxOpeningBalanceInCents: number;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Editar conta bancária" />

        <div class="flex flex-col gap-1">
            <Link href="/bank-account" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para conta bancária</Link>
            <h1 class="text-2xl font-semibold">Editar conta bancária</h1>
        </div>

        <Form
            action="/bank-account"
            method="put"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-4 text-base font-semibold text-gray-900">Dados da conta</legend>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-sm font-medium text-gray-700">Nome</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        :value="account.name"
                        required
                        autofocus
                        maxlength="255"
                        autocomplete="off"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</p>
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-xs">
                    <label for="opening_balance" class="text-sm font-medium text-gray-700">Saldo inicial</label>
                    <MoneyInput
                        id="opening_balance"
                        name="opening_balance_in_cents"
                        :initial-cents="account.opening_balance_in_cents"
                        :max-cents="maxOpeningBalanceInCents"
                        required
                    />
                    <p v-if="errors.opening_balance_in_cents" class="text-sm text-red-600">{{ errors.opening_balance_in_cents }}</p>
                </div>

                <p class="text-sm text-gray-600">
                    Corrigir o saldo inicial muda o saldo atual na mesma diferença. Por exemplo, aumentar o saldo inicial em R$ 200,00
                    também aumenta o saldo atual em R$ 200,00.
                </p>
            </fieldset>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    href="/bank-account"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-100"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {{ processing ? 'Salvando...' : 'Salvar alterações' }}
                </button>
            </div>
        </Form>
    </div>
</template>
