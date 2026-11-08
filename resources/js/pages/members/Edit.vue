<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MemberFormFields from '../../components/members/MemberFormFields.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Member } from '../../types/models';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    member: Member;
}>();

const isChangingStatus = ref(false);

/**
 * Deactivates or reactivates the member after the user confirms.
 */
function toggleActive(): void {
    const isActive = props.member.deactivated_at === null;
    const confirmation = isActive
        ? `Desativar ${props.member.name}? O cadastro continua salvo e pode ser reativado depois.`
        : `Reativar ${props.member.name}?`;

    if (!window.confirm(confirmation)) {
        return;
    }

    router.post(`/members/${props.member.id}/${isActive ? 'deactivate' : 'activate'}`, {}, {
        preserveScroll: true,
        onStart: () => (isChangingStatus.value = true),
        onFinish: () => (isChangingStatus.value = false),
    });
}

function formatDateTime(isoDateTime: string): string {
    return new Date(isoDateTime).toLocaleDateString('pt-BR');
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Editar aluno" />

        <div class="flex flex-col gap-1">
            <Link href="/members" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para alunos</Link>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">Editar aluno</h1>
                <span
                    v-if="member.deactivated_at"
                    class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                >
                    Inativo
                </span>
            </div>
        </div>

        <Form
            :action="`/members/${member.id}`"
            method="put"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <MemberFormFields :errors="errors" :member="member" />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    href="/members"
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
                <h2 class="text-base font-semibold text-gray-900">Status do aluno</h2>
                <p v-if="member.deactivated_at" class="text-sm text-gray-600">
                    Desativado desde {{ formatDateTime(member.deactivated_at) }}.
                </p>
                <p v-else class="text-sm text-gray-600">Ativo. Ao desativar, o cadastro continua salvo e pode ser reativado.</p>
            </div>

            <button
                v-if="member.deactivated_at"
                type="button"
                :disabled="isChangingStatus"
                class="shrink-0 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                @click="toggleActive"
            >
                Reativar aluno
            </button>
            <button
                v-else
                type="button"
                :disabled="isChangingStatus"
                class="shrink-0 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
                @click="toggleActive"
            >
                Desativar aluno
            </button>
        </section>
    </div>
</template>
