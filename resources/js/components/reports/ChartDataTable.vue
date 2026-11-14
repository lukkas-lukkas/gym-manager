<script setup lang="ts">
import { formatCents } from './chart';

/**
 * The numbers behind a chart as a table, visible only to screen readers, so the data never depends on seeing it.
 * A month without a value (`null`) is shown as a dash.
 *
 * `sr-only` sits on a wrapper `div`, not on the table: a table ignores the 1px height, keeps its full size
 * and would stretch the page with blank space.
 */
defineProps<{
    caption: string;
    /** Month labels already formatted for display, in order. */
    monthLabels: string[];
    series: { label: string; values: (number | null)[] }[];
}>();
</script>

<template>
    <div class="sr-only">
        <table>
            <caption>{{ caption }}</caption>
            <thead>
                <tr>
                    <th scope="col">Mês</th>
                    <th v-for="item in series" :key="item.label" scope="col">{{ item.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(month, monthIndex) in monthLabels" :key="month">
                    <th scope="row">{{ month }}</th>
                    <td v-for="item in series" :key="item.label">
                        {{ item.values[monthIndex] == null ? '—' : formatCents(item.values[monthIndex] as number) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
