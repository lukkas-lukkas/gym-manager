<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MoneyInput from '../../components/MoneyInput.vue';
import AppLayout from '../../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    /** Largest opening balance accepted, in cents. */
    maxOpeningBalanceInCents: number;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Cadastrar conta bancária" />

        <div class="flex flex-col gap-1">
            <Link href="/bank-account" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para conta bancária</Link>
            <h1 class="text-2xl font-semibold">Cadastrar conta bancária</h1>
        </div>

        <Form
            action="/bank-account"
            method="post"
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
                        required
                        autofocus
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Ex.: Banco do Brasil"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</p>
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-xs">
                    <label for="opening_balance" class="text-sm font-medium text-gray-700">Saldo inicial</label>
                    <MoneyInput id="opening_balance" name="opening_balance_in_cents" :max-cents="maxOpeningBalanceInCents" required />
                    <p v-if="errors.opening_balance_in_cents" class="text-sm text-red-600">{{ errors.opening_balance_in_cents }}</p>
                </div>

                <p class="text-sm text-gray-600">
                    Informe quanto a conta tem agora. Receitas e despesas pagas antes deste momento já fazem parte desse valor.
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
                    {{ processing ? 'Salvando...' : 'Cadastrar conta' }}
                </button>
            </div>
        </Form>
    </div>
</template>
