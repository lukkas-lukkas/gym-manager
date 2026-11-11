<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MemberFormFields from '../../components/members/MemberFormFields.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Member } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    member: Member;
}>();
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
    </div>
</template>
