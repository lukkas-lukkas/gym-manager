<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const today = new Date().toLocaleDateString('en-CA');
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
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-4 text-base font-semibold text-gray-900">Dados pessoais</legend>

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
                        <label for="email" class="text-sm font-medium text-gray-700">E-mail</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            required
                            maxlength="255"
                            autocomplete="off"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                        />
                        <p v-if="errors.email" class="text-sm text-red-600">{{ errors.email }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="birth_date" class="text-sm font-medium text-gray-700">Data de nascimento</label>
                        <input
                            id="birth_date"
                            type="date"
                            name="birth_date"
                            required
                            :max="today"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                        />
                        <p v-if="errors.birth_date" class="text-sm text-red-600">{{ errors.birth_date }}</p>
                    </div>
                </div>
            </fieldset>

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
