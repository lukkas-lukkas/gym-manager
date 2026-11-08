<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MemberFormFields from '../../components/members/MemberFormFields.vue';
import AppLayout from '../../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Novo aluno" />

        <div class="flex flex-col gap-1">
            <Link href="/members" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para alunos</Link>
            <h1 class="text-2xl font-semibold">Novo aluno</h1>
        </div>

        <Form
            action="/members"
            method="post"
            class="flex max-w-2xl flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6"
            #default="{ errors, processing }"
        >
            <MemberFormFields :errors="errors" />

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
                    {{ processing ? 'Salvando...' : 'Salvar aluno' }}
                </button>
            </div>
        </Form>
    </div>
</template>
