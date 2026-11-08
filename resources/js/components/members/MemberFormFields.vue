<script setup lang="ts">
import type { Member } from '../../types/models';

/**
 * Personal data fields shared by the create and edit member forms.
 * Must be rendered inside an Inertia `<Form>`, which reads the inputs by `name`.
 */
defineProps<{
    errors: Partial<Record<string, string>>;
    member?: Member;
}>();

const today = new Date().toLocaleDateString('en-CA');
</script>

<template>
    <fieldset class="flex flex-col gap-4">
        <legend class="mb-4 text-base font-semibold text-gray-900">Dados pessoais</legend>

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-medium text-gray-700">Nome</label>
            <input
                id="name"
                type="text"
                name="name"
                :value="member?.name"
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
                    :value="member?.email"
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
                    :value="member?.birth_date"
                    required
                    :max="today"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900 focus:border-gray-900 focus:outline-none"
                />
                <p v-if="errors.birth_date" class="text-sm text-red-600">{{ errors.birth_date }}</p>
            </div>
        </div>
    </fieldset>
</template>
