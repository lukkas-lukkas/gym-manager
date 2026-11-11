<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import type { Paginated, Member } from '../../types/models';

defineOptions({ layout: AppLayout });

type MemberStatus = 'active' | 'inactive';

const props = defineProps<{
    members: Paginated<Member>;
    filters: {
        search: string | null;
        status: MemberStatus;
    };
}>();

const tabs: { status: MemberStatus; label: string }[] = [
    { status: 'active', label: 'Ativos' },
    { status: 'inactive', label: 'Inativos' },
];

const search = ref(props.filters.search ?? '');

/**
 * Builds the list URL, leaving out defaults so URLs stay short (`/members` instead of `/members?status=active`).
 */
function listUrl(status: MemberStatus, term: string): string {
    const params = new URLSearchParams();

    if (status !== 'active') {
        params.set('status', status);
    }

    if (term.trim()) {
        params.set('search', term.trim());
    }

    const query = params.toString();

    return query ? `/members?${query}` : '/members';
}

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

/**
 * Reloads the list a moment after the user stops typing, so we don't hit the server on every keystroke.
 */
watch(search, (term) => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(listUrl(props.filters.status, term), {}, {
            only: ['members', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

onUnmounted(() => clearTimeout(searchTimeout));

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

        <nav class="flex gap-6 border-b border-gray-200" aria-label="Status dos alunos">
            <Link
                v-for="tab in tabs"
                :key="tab.status"
                :href="listUrl(tab.status, search)"
                :only="['members', 'filters']"
                preserve-state
                :aria-current="filters.status === tab.status ? 'page' : undefined"
                :class="
                    filters.status === tab.status
                        ? 'border-gray-900 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                "
                class="-mb-px border-b-2 px-1 pb-3 text-sm font-semibold"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <div class="relative max-w-md">
            <label for="search" class="sr-only">Buscar alunos</label>
            <svg
                class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="m21 21-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16" />
            </svg>
            <input
                id="search"
                v-model="search"
                type="search"
                maxlength="100"
                placeholder="Buscar por ID, nome ou e-mail"
                autocomplete="off"
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-3 pl-10 text-gray-900 focus:border-gray-900 focus:outline-none"
            />
        </div>

        <div v-if="members.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p v-if="filters.search" class="text-gray-600">Nenhum aluno encontrado para "{{ filters.search }}".</p>
            <p v-else-if="filters.status === 'inactive'" class="text-gray-600">Nenhum aluno inativo.</p>
            <p v-else class="text-gray-600">Nenhum aluno ativo.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <!-- Cards on mobile -->
            <ul class="divide-y divide-gray-200 md:hidden">
                <li v-for="member in members.data" :key="member.id" class="flex items-start justify-between gap-4 p-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <Link :href="`/members/${member.id}`" class="font-medium text-gray-900 hover:underline">
                            <span class="mr-1 font-normal text-gray-500">#{{ member.id }}</span>
                            {{ member.name }}
                        </Link>
                        <span class="truncate text-sm text-gray-600">{{ member.email }}</span>
                        <span class="text-sm text-gray-500">Nascimento: {{ formatDate(member.birth_date) }}</span>
                    </div>
                    <Link
                        :href="`/members/${member.id}/edit`"
                        class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Editar
                    </Link>
                </li>
            </ul>

            <!-- Table from tablet up -->
            <table class="hidden w-full text-left text-sm md:table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="w-24 px-6 py-3 font-semibold">ID</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Nome</th>
                        <th scope="col" class="px-6 py-3 font-semibold">E-mail</th>
                        <th scope="col" class="px-6 py-3 font-semibold">Data de nascimento</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="member in members.data" :key="member.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-gray-500 tabular-nums">#{{ member.id }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">
                            <Link :href="`/members/${member.id}`" class="hover:underline">{{ member.name }}</Link>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ member.email }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ formatDate(member.birth_date) }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link
                                :href="`/members/${member.id}/edit`"
                                class="font-medium text-gray-700 hover:text-gray-900 hover:underline"
                                :aria-label="`Editar ${member.name}`"
                            >
                                Editar
                            </Link>
                        </td>
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
