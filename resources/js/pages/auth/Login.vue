<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <Head title="Entrar" />

        <div class="flex w-full max-w-sm flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <div class="flex flex-col items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-xl bg-gray-900 text-white" aria-hidden="true">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6.5 6.5v11M17.5 6.5v11M3 9v6M21 9v6M6.5 12h11" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">Entrar</h1>
            </div>

            <Form action="/login" method="post" :reset-on-error="['password']" class="flex flex-col gap-4" #default="{ errors, processing }">
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-sm font-medium text-gray-700">E-mail</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        autocomplete="email"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.email" class="text-sm text-red-600">{{ errors.email }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-sm font-medium text-gray-700">Senha</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                    />
                    <p v-if="errors.password" class="text-sm text-red-600">{{ errors.password }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    {{ processing ? 'Entrando...' : 'Entrar' }}
                </button>
            </Form>
        </div>
    </div>
</template>
