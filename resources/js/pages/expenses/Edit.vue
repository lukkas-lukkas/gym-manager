<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MoneyInput from '../../components/MoneyInput.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Expense, ExpenseStatusOption } from '../../types/models';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    expense: Expense;
    statuses: ExpenseStatusOption[];
}>();

/**
 * The list on the month this expense is due, so "back" returns to where the user came from.
 */
const listUrl = computed(() => `/expenses?month=${props.expense.due_on.slice(0, 7)}`);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Editar despesa" />

        <div class="flex flex-col gap-1">
            <Link :href="listUrl" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para despesas</Link>
            <h1 class="text-2xl font-semibold">Editar despesa</h1>
        </div>

        <Form
            :action="`/expenses/${expense.id}`"
            method="put"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-4 text-base font-semibold text-gray-900">Dados da despesa</legend>

                <div class="flex flex-col gap-1.5">
                    <label for="description" class="text-sm font-medium text-gray-700">Descrição</label>
                    <input
                        id="description"
                        type="text"
                        name="description"
                        :value="expense.description"
                        required
                        autofocus
                        maxlength="255"
                        autocomplete="off"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.description" class="text-sm text-red-600">{{ errors.description }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="amount" class="text-sm font-medium text-gray-700">Valor</label>
                        <MoneyInput id="amount" name="amount_in_cents" :initial-cents="expense.amount_in_cents" required />
                        <p v-if="errors.amount_in_cents" class="text-sm text-red-600">{{ errors.amount_in_cents }}</p>
                        <p v-else-if="expense.status === 'paid'" class="text-sm text-gray-600">Já paga: para mudar o valor, salve primeiro como pendente.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="due_on" class="text-sm font-medium text-gray-700">Vencimento</label>
                        <input
                            id="due_on"
                            type="date"
                            name="due_on"
                            :value="expense.due_on"
                            required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                        />
                        <p v-if="errors.due_on" class="text-sm text-red-600">{{ errors.due_on }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-xs">
                    <label for="status" class="text-sm font-medium text-gray-700">Status</label>
                    <select
                        id="status"
                        name="status"
                        required
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    >
                        <option v-for="status in statuses" :key="status.value" :value="status.value" :selected="status.value === expense.status">
                            {{ status.label }}
                        </option>
                    </select>
                    <p v-if="errors.status" class="text-sm text-red-600">{{ errors.status }}</p>
                </div>

                <p class="text-sm text-gray-600">A alteração vale só para esta despesa, não para as outras parcelas repetidas.</p>
            </fieldset>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="listUrl"
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
