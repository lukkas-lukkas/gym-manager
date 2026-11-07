<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Paginated, Member } from '../../types/models';

defineOptions({ layout: AppLayout });

defineProps<{
    members: Paginated<Member>;
}>();

/**
 * Formats a `YYYY-MM-DD` date as `DD/MM/YYYY` without going through `Date`, avoiding timezone shifts.
 */
function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Alunos" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Alunos</h1>

            <Link
                href="/members/create"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
            >
                Novo aluno
            </Link>
        </div>

        <div v-if="members.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-gray-600">Nenhum aluno cadastrado ainda.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="member in members.data" :key="member.id" class="flex flex-col gap-1 p-4">
                    <span class="font-medium text-gray-900">{{ member.name }}</span>
                    <span class="truncate text-sm text-gray-600">{{ member.email }}</span>
                    <span class="text-sm text-gray-500">Nascimento: {{ formatDate(member.birth_date) }}</span>
                </li>
            </ul>

            <!-- Table from tablet up -->
            <table class="hidden w-full text-left text-sm md:table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">Nome</th>
                        <th scope="col" class="px-6 py-3 font-semibold">E-mail</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Data de nascimento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="member in members.data" :key="member.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ member.name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ member.email }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ formatDate(member.birth_date) }}</td>
                    </tr>
                </tbody>
            </table>

            <nav
                v-if="members.last_page > 1"
                class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 text-sm sm:px-6"
                aria-label="Paginação"
            >
                <span class="text-gray-600">{{ members.from }}–{{ members.to }} de {{ members.total }}</span>

                <div class="flex gap-2">
                    <Link
                        v-if="members.prev_page_url"
                        :href="members.prev_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Anterior
                    </Link>
                    <Link
                        v-if="members.next_page_url"
                        :href="members.next_page_url"
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Próxima
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
