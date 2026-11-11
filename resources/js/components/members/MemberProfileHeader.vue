<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Member } from '../../types/models';

/**
 * Header shared by the tabs of the member page: name, status, edit button and the tab bar.
 * Each tab is its own URL, so a tab only loads its own data and can be linked to directly.
 */
const props = defineProps<{
    member: Pick<Member, 'id' | 'name' | 'deactivated_at'>;
    activeTab: 'details' | 'enrollments';
}>();

const tabs = [
    { key: 'details', label: 'Dados', href: `/members/${props.member.id}` },
    { key: 'enrollments', label: 'Planos', href: `/members/${props.member.id}/enrollments` },
] as const;
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <Link href="/members" class="text-sm text-gray-600 hover:text-gray-900">&larr; Voltar para alunos</Link>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">
                        <span class="mr-1 font-normal text-gray-500">#{{ member.id }}</span>
                        {{ member.name }}
                    </h1>
                    <span
                        v-if="member.deactivated_at"
                        class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700"
                    >
                        Inativo
                    </span>
                </div>

                <Link
                    :href="`/members/${member.id}/edit`"
                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
                >
                    Editar
                </Link>
            </div>
        </div>

        <nav class="flex gap-6 border-b border-gray-200" aria-label="Seções do aluno">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                preserve-scroll
                :aria-current="activeTab === tab.key ? 'page' : undefined"
                :class="
                    activeTab === tab.key
                        ? 'border-gray-900 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                "
                class="-mb-px border-b-2 px-1 pb-3 text-sm font-semibold"
            >
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>
