<script setup lang="ts">
import { formatCents } from './chart';

/**
 * The numbers behind a chart as a table, visible only to screen readers, so the data never depends on seeing it.
 * One row per label (a month by default) and one column per series; a missing value (`null`) is shown as a dash.
 *
 * `sr-only` sits on a wrapper `div`, not on the table: a table ignores the 1px height, keeps its full size
 * and would stretch the page with blank space.
 */
withDefaults(
    defineProps<{
        caption: string;
        /** Row labels already formatted for display, in order (months, plan names...). */
        rowLabels: string[];
        series: { label: string; values: (number | null)[] }[];
        /** Title of the first column, the one with the row labels. */
        rowHeader?: string;
        /** Formats one value; money in cents by default. */
        formatValue?: (value: number) => string;
    }>(),
    {
        rowHeader: 'Mês',
        formatValue: formatCents,
    },
);
</script>

<template>
    <div class="sr-only">
        <table>
            <caption>{{ caption }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ rowHeader }}</th>
                    <th v-for="item in series" :key="item.label" scope="col">{{ item.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(rowLabel, rowIndex) in rowLabels" :key="rowLabel">
                    <th scope="row">{{ rowLabel }}</th>
                    <td v-for="item in series" :key="item.label">
                        {{ item.values[rowIndex] == null ? '—' : formatValue(item.values[rowIndex] as number) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
