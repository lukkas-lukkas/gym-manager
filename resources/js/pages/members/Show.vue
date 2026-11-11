<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MemberProfileHeader from '../../components/members/MemberProfileHeader.vue';
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

/**
 * Formats a `YYYY-MM-DD` date as `DD/MM/YYYY` without going through `Date`, avoiding timezone shifts.
 */
function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');

    return `${day}/${month}/${year}`;
}

function formatDateTime(isoDateTime: string): string {
    return new Date(isoDateTime).toLocaleDateString('pt-BR');
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="member.name" />

        <MemberProfileHeader :member="member" active-tab="details" />

        <dl class="grid max-w-2xl gap-6 rounded-2xl border border-gray-200 bg-white p-6 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <dt class="text-sm font-medium text-gray-500">Nome</dt>
                <dd class="text-gray-900">{{ member.name }}</dd>
            </div>

            <div class="flex flex-col gap-1">
                <dt class="text-sm font-medium text-gray-500">E-mail</dt>
                <dd class="break-all text-gray-900">{{ member.email }}</dd>
            </div>

            <div class="flex flex-col gap-1">
                <dt class="text-sm font-medium text-gray-500">Data de nascimento</dt>
                <dd class="text-gray-900 tabular-nums">{{ formatDate(member.birth_date) }}</dd>
            </div>

            <div class="flex flex-col gap-1">
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd v-if="member.deactivated_at" class="text-gray-900">Inativo desde {{ formatDateTime(member.deactivated_at) }}</dd>
                <dd v-else class="text-gray-900">Ativo</dd>
            </div>
        </dl>

        <section class="flex max-w-2xl flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold text-gray-900">Status do aluno</h2>
                <p v-if="member.deactivated_at" class="text-sm text-gray-600">Ao reativar, o aluno volta a aparecer na lista de ativos.</p>
                <p v-else class="text-sm text-gray-600">Ao desativar, o cadastro continua salvo e pode ser reativado.</p>
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
