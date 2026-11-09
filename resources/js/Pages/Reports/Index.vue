<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import { computed, reactive, ref } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, required: true },
    buckets: { type: Object, required: true },
});

const form = reactive({ ...props.filters });
const apply = () => router.get(route('reports.index'), form, { preserveScroll: true, replace: true });

const money = (value) => Number(value || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 });
const percent = (value) => (value === null || value === undefined ? '—' : `${Number(value).toLocaleString('es-MX', { maximumFractionDigits: 1 })}%`);

// Colores por función: serie 1 azul, serie 2 naranja; rejilla y ejes discretos
const SERIES_1 = '#2a78d6';
const SERIES_2 = '#eb6834';
const GRID = '#e1e0d9';
const MUTED = '#898781';

const baseOptions = (single) => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: !single, position: 'top', align: 'start', labels: { boxWidth: 12, boxHeight: 12, color: '#52514e' } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: MUTED }, border: { color: '#c3c2b7' } },
        y: {
            beginAtZero: true,
            grid: { color: GRID },
            border: { display: false },
            ticks: { color: MUTED, callback: (v) => money(v) },
        },
    },
});

const bar = (label, data, color) => ({
    label,
    data,
    backgroundColor: color,
    borderRadius: { topLeft: 4, topRight: 4 },
    borderSkipped: 'bottom',
    borderColor: '#fcfcfb',
    borderWidth: { left: 1, right: 1 },
    maxBarThickness: 36,
});

const placement = computed(() => props.report.placement);
const collections = computed(() => props.report.collections);
const delinquency = computed(() => props.report.delinquency);

const placementChart = computed(() => ({
    labels: placement.value.months.map((m) => m.label),
    datasets: [bar('Monto colocado', placement.value.months.map((m) => m.amount), SERIES_1)],
}));

const collectionsChart = computed(() => ({
    labels: collections.value.months.map((m) => m.label),
    datasets: [
        bar('Esperado', collections.value.months.map((m) => m.expected), SERIES_1),
        bar('Cobrado', collections.value.months.map((m) => m.collected), SERIES_2),
    ],
}));

const agingMax = computed(() => Math.max(...props.report.aging.map((b) => b.balance), 1));
const showTables = ref(false);
</script>

<template>
    <Head title="Reportes" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 pt-0">
            <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Reportes</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Colocación y cobranza del periodo elegido. La cartera por antigüedad y la morosidad son a hoy.
                    </p>
                </div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                    <div>
                        <label for="report-from" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
                        <input id="report-from" v-model="form.from" type="month" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm" />
                    </div>
                    <div>
                        <label for="report-to" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
                        <input id="report-to" v-model="form.to" type="month" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm" />
                    </div>
                    <button type="submit" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Aplicar</button>
                </form>
            </div>

            <!-- Cifras principales -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Colocado en el periodo</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ money(placement.amount) }}</p>
                    <p class="text-xs text-gray-500">{{ placement.count }} crédito(s) · promedio {{ money(placement.average) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Cartera vigente (saldo de capital)</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ money(delinquency.portfolio_balance) }}</p>
                    <p class="text-xs text-gray-500">{{ delinquency.loans }} crédito(s) activos</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Índice de morosidad</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ percent(delinquency.index) }}</p>
                    <p class="text-xs text-gray-500">
                        {{ money(delinquency.overdue_balance) }} con atraso · más de 90 días: {{ percent(delinquency.index_90) }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Cobranza real contra esperada</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ percent(collections.efficiency) }}</p>
                    <p class="text-xs text-gray-500">{{ money(collections.collected) }} de {{ money(collections.expected) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-gray-900">Colocación por mes</h2>
                    <p class="text-xs text-gray-500">Monto desembolsado (o aprobado, en créditos anteriores al registro de desembolsos).</p>
                    <div class="mt-4 h-72">
                        <Bar :data="placementChart" :options="baseOptions(true)" aria-label="Monto colocado por mes" />
                    </div>
                    <div v-if="placement.by_product.length" class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600">
                        <span v-for="product in placement.by_product" :key="product.type">
                            {{ product.type === 'FUNERARIO' ? 'Con garantía (plan funerario)' : 'Personal' }}:
                            <strong class="text-gray-900">{{ product.count }}</strong> · {{ money(product.amount) }}
                        </span>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-gray-900">Cobranza esperada contra real</h2>
                    <p class="text-xs text-gray-500">Esperado: cuotas que vencían en el mes. Cobrado: pagos registrados en el mes (sin los revertidos).</p>
                    <div class="mt-4 h-72">
                        <Bar :data="collectionsChart" :options="baseOptions(false)" aria-label="Cobranza esperada y cobrada por mes" />
                    </div>
                </section>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Cartera por antigüedad</h2>
                        <p class="text-xs text-gray-500">
                            Saldo de capital de los créditos activos según los días de atraso de su cuota más antigua. Dentro de los días de gracia cuenta como al corriente.
                        </p>
                    </div>
                    <a :href="route('reports.aging.export')" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Descargar detalle (CSV)
                    </a>
                </div>
                <table class="mt-4 w-full text-left text-sm text-gray-700">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Tramo</th>
                            <th class="py-2 pr-4 text-right">Créditos</th>
                            <th class="py-2 pr-4 text-right">Saldo de capital</th>
                            <th class="py-2 pr-4 text-right">Monto vencido</th>
                            <th class="hidden w-1/3 py-2 md:table-cell"><span class="sr-only">Proporción</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="bucket in report.aging" :key="bucket.key" class="border-t border-gray-100">
                            <td class="py-2 pr-4 font-medium text-gray-900">{{ bucket.label }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ bucket.count }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ money(bucket.balance) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ money(bucket.overdue_amount) }}</td>
                            <td class="hidden py-2 md:table-cell">
                                <div class="h-3 rounded-r" :style="{ width: `${(bucket.balance / agingMax) * 100}%`, minWidth: bucket.balance > 0 ? '4px' : '0', background: SERIES_1 }"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <button type="button" class="text-sm font-medium text-blue-700 hover:underline" @click="showTables = !showTables">
                    {{ showTables ? 'Ocultar' : 'Ver' }} las cifras por mes en tabla
                </button>
                <div v-if="showTables" class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Mes</th>
                                <th class="py-2 pr-4 text-right">Créditos colocados</th>
                                <th class="py-2 pr-4 text-right">Monto colocado</th>
                                <th class="py-2 pr-4 text-right">Cobranza esperada</th>
                                <th class="py-2 pr-4 text-right">Cobrado</th>
                                <th class="py-2 text-right">Cobrado / esperado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(month, index) in placement.months" :key="month.month" class="border-t border-gray-100">
                                <td class="py-2 pr-4">{{ month.label }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ month.count }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ money(month.amount) }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ money(collections.months[index]?.expected) }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ money(collections.months[index]?.collected) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ percent(collections.months[index]?.efficiency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
