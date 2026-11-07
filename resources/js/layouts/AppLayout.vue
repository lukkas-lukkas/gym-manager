<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * A sidebar menu entry. Items without `href` don't have a page yet and are shown as "em breve".
 */
type MenuItem = {
    label: string;
    href?: string;
    icon: string;
};

const menuItems: MenuItem[] = [
    { label: 'Dashboard', href: '/', icon: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10' },
    { label: 'Alunos', icon: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6M22 19v-1a4 4 0 0 0-3-3.87M16 4.13a3 3 0 0 1 0 5.74' },
    { label: 'Planos', icon: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 13h6M9 17h4' },
    { label: 'Receitas', icon: 'M12 19V5M5 12l7-7 7 7' },
    { label: 'Despesas', icon: 'M12 5v14M19 12l-7 7-7-7' },
    { label: 'Relatórios', icon: 'M3 3v18h18M7 15l4-4 3 3 5-6' },
];

const page = usePage();
const isMobileMenuOpen = ref(false);

function isActive(href: string): boolean {
    const currentPath = page.url.split('?')[0];

    return href === '/' ? currentPath === '/' : currentPath.startsWith(href);
}

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeMobileMenu();
    }
}

let removeNavigateListener: (() => void) | undefined;

onMounted(() => {
    removeNavigateListener = router.on('navigate', closeMobileMenu);
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    removeNavigateListener?.();
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <!-- Backdrop (tablet / mobile only) -->
        <div
            v-if="isMobileMenuOpen"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            aria-hidden="true"
            @click="closeMobileMenu"
        />

        <!-- Sidebar: fixed and always visible on desktop (lg+), off-canvas drawer below lg -->
        <aside
            id="app-sidebar"
            :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0"
        >
            <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-5">
                <Link href="/" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-gray-900 text-white" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6.5 6.5v11M17.5 6.5v11M3 9v6M21 9v6M6.5 12h11" />
                        </svg>
                    </span>
                    <span class="truncate font-semibold">{{ page.props.name }}</span>
                </Link>

                <button
                    type="button"
                    class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 lg:hidden"
                    aria-label="Fechar menu"
                    @click="closeMobileMenu"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto p-3" aria-label="Menu principal">
                <ul class="flex flex-col gap-1">
                    <li v-for="item in menuItems" :key="item.label">
                        <Link
                            v-if="item.href"
                            :href="item.href"
                            prefetch
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            :class="isActive(item.href) ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                        >
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path :d="item.icon" />
                            </svg>
                            {{ item.label }}
                        </Link>

                        <span v-else class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-400">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path :d="item.icon" />
                            </svg>
                            {{ item.label }}
                            <span class="ml-auto rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-500">Em breve</span>
                        </span>
                    </li>
                </ul>
            </nav>

            <div class="border-t border-gray-200 p-3">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                >
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    Sair
                </Link>
            </div>
        </aside>

        <!-- Content area: pushed right on desktop to make room for the sidebar -->
        <div class="flex min-h-screen flex-col lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-gray-700 hover:bg-gray-100"
                    aria-label="Abrir menu"
                    aria-controls="app-sidebar"
                    :aria-expanded="isMobileMenuOpen"
                    @click="isMobileMenuOpen = true"
                >
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="truncate font-semibold">{{ page.props.name }}</span>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
