<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { MembershipPlan } from '../../types/models';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plan: MembershipPlan;
}>();

const isDeactivating = ref(false);

/**
 * Deactivates the plan after the user confirms.
 */
function deactivate(): void {
    if (!window.confirm(`Desativar o plano ${props.plan.name}? Ele não poderá mais ser usado em novas matrículas.`)) {
        return;
    }

    router.post(`/membership-plans/${props.plan.id}/deactivate`, {}, {
        onStart: () => (isDeactivating.value = true),
        onFinish: () => (isDeactivating.value = false),
    });
}

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
        <Head title="Editar plano" />

        <div class="flex flex-col gap-1">
            <Link href="/membership-plans" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para planos</Link>
            <h1 class="text-2xl font-semibold">Editar plano</h1>
        </div>

        <Form
            :action="`/membership-plans/${plan.id}`"
            method="put"
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
                        :value="plan.name"
                        required
                        autofocus
                        maxlength="255"
                        autocomplete="off"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</p>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm font-medium text-gray-700">Preço</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600 tabular-nums">
                            {{ formatPrice(plan.price_in_cents) }}
                        </dd>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm font-medium text-gray-700">Período de cobrança</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">
                            {{ plan.billing_period_label }}
                        </dd>
                    </div>
                </dl>

                <p class="flex gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 16v-4M12 8h.01" />
                    </svg>
                    <span>
                        O preço e o período não podem ser alterados, porque são as condições que os alunos aceitaram ao entrar
                        neste plano. Para oferecer outro valor ou período, crie um novo plano.
                    </span>
                </p>
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
                    {{ processing ? 'Salvando...' : 'Salvar alterações' }}
                </button>
            </div>
        </Form>

        <section class="flex max-w-2xl flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold text-gray-900">Status do plano</h2>
                <p class="text-sm text-gray-600">
                    Ao desativar, o plano deixa de aparecer para novas matrículas. Os alunos já matriculados continuam nele.
                </p>
            </div>

            <button
                type="button"
                :disabled="isDeactivating"
                class="shrink-0 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                @click="deactivate"
            >
                Desativar plano
            </button>
        </section>
    </div>
</template>
