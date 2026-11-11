<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MoneyInput from '../../components/MoneyInput.vue';
import AppLayout from '../../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    maxMonths: number;
}>();

/**
 * Today's date in the user's timezone, as `YYYY-MM-DD` (what `<input type="date">` expects).
 */
function todayAsDateString(): string {
    const today = new Date();

    return [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
}

const dueOn = ref(todayAsDateString());
const months = ref(1);

/**
 * Due date of the last repeated expense, using the same rule as the server: count from the first date
 * and clamp to the month's last day (Jan 31 + 1 month = Feb 28).
 */
const lastDueOn = computed((): string | null => {
    if (!dueOn.value || months.value <= 1) {
        return null;
    }

    const [year, month, day] = dueOn.value.split('-').map(Number);
    const targetMonthIndex = month - 1 + months.value - 1;
    const lastDayOfTargetMonth = new Date(year, targetMonthIndex + 1, 0).getDate();

    return new Date(year, targetMonthIndex, Math.min(day, lastDayOfTargetMonth)).toLocaleDateString('pt-BR');
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Nova despesa" />

        <div class="flex flex-col gap-1">
            <Link href="/expenses" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para despesas</Link>
            <h1 class="text-2xl font-semibold">Nova despesa</h1>
        </div>

        <Form
            action="/expenses"
            method="post"
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
                        <MoneyInput id="amount" name="amount_in_cents" required />
                        <p v-if="errors.amount_in_cents" class="text-sm text-red-600">{{ errors.amount_in_cents }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="due_on" class="text-sm font-medium text-gray-700">Vencimento</label>
                        <input
                            id="due_on"
                            v-model="dueOn"
                            type="date"
                            name="due_on"
                            required
                            class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                        />
                        <p v-if="errors.due_on" class="text-sm text-red-600">{{ errors.due_on }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5 sm:max-w-xs">
                    <label for="months" class="text-sm font-medium text-gray-700">Repetir por quantos meses</label>
                    <input
                        id="months"
                        v-model.number="months"
                        type="number"
                        name="months"
                        required
                        min="1"
                        :max="maxMonths"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 tabular-nums focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.months" class="text-sm text-red-600">{{ errors.months }}</p>
                    <p v-else-if="lastDueOn" class="text-sm text-gray-600">
                        Mensalmente até {{ lastDueOn }}.
                    </p>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    href="/expenses"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-100"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {{ processing ? 'Salvando...' : 'Salvar despesa' }}
                </button>
            </div>
        </Form>
    </div>
</template>
