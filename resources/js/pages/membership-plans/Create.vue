<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MoneyInput from '../../components/MoneyInput.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { BillingPeriodOption } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    billingPeriods: BillingPeriodOption[];
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Novo plano" />

        <div class="flex flex-col gap-1">
            <Link href="/membership-plans" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para planos</Link>
            <h1 class="text-2xl font-semibold">Novo plano</h1>
        </div>

        <Form
            action="/membership-plans"
            method="post"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-4 text-base font-semibold text-gray-900">Dados do plano</legend>

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
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="price" class="text-sm font-medium text-gray-700">Preço</label>
                        <MoneyInput id="price" name="price_in_cents" required />
                        <p v-if="errors.price_in_cents" class="text-sm text-red-600">{{ errors.price_in_cents }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="billing_period" class="text-sm font-medium text-gray-700">Período de cobrança</label>
                        <select
                            id="billing_period"
                            name="billing_period"
                            required
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                        >
                            <option v-for="period in billingPeriods" :key="period.value" :value="period.value">
                                {{ period.label }}
                            </option>
                        </select>
                        <p v-if="errors.billing_period" class="text-sm text-red-600">{{ errors.billing_period }}</p>
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    href="/membership-plans"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-100"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {{ processing ? 'Salvando...' : 'Salvar plano' }}
                </button>
            </div>
        </Form>
    </div>
</template>
