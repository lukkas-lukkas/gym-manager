<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Currency input in Brazilian reais, typed like a card machine: digits fill in from the right
 * (`9` → `0,09`, `9990` → `99,90`). The user sees the formatted value, while a hidden input named
 * `name` carries the amount in cents, so it works inside an Inertia `<Form>`.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        initialCents?: number | null;
        /** Largest amount accepted, in cents. Extra keystrokes beyond it are ignored. */
        maxCents?: number;
        required?: boolean;
    }>(),
    {
        initialCents: null,
        maxCents: 9_999_999,
        required: false,
    },
);

const cents = ref<number | null>(props.initialCents);

const formatter = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formattedValue = computed(() => (cents.value === null ? '' : formatter.format(cents.value / 100)));

/**
 * Reads only the digits typed and treats them as cents. A keystroke that would go past
 * `maxCents` is dropped, keeping the previous value.
 */
function updateFromInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '');

    if (digits === '') {
        cents.value = null;
    } else if (Number(digits) <= props.maxCents) {
        cents.value = Number(digits);
    }

    // Vue skips re-rendering when the value didn't change (e.g. a rejected keystroke), so sync the field by hand.
    input.value = formattedValue.value;
}
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-500" aria-hidden="true">R$</span>
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="0,00"
            :required="required"
            :value="formattedValue"
            class="w-full rounded-lg border border-gray-300 py-2 pr-3 pl-10 text-gray-900 tabular-nums focus:border-gray-900 focus:outline-none"
            @input="updateFromInput"
        />
        <input type="hidden" :name="name" :value="cents ?? ''" />
    </div>
</template>
