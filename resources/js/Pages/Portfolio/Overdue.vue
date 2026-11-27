<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    graceDays: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ mine: false }) },
    onlyMine: { type: Boolean, default: false },
    canAssign: { type: Boolean, default: false },
    collectors: { type: Array, default: () => [] },
    outcomes: { type: Object, default: () => ({}) },
});

const toast = useToast();
const severity = (days) => (days > 30 ? 'bg-red-600 text-white' : days > 7 ? 'bg-orange-500 text-white' : 'bg-yellow-200 text-yellow-900');
const shortDate = (value) => (value ? new Date(`${value}T12:00:00`).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '');

const toggleMine = (event) => router.get(route('portfolio.overdue'), event.target.checked ? { mine: 1 } : {}, { preserveScroll: true, replace: true });
const exportUrl = computed(() => route('portfolio.overdue.export', props.filters.mine ? { mine: 1 } : {}));

// ---- Asignación de gestor (Gerente y Administrador) ---------------------------------------
const selected = ref([]);
const collectorId = ref('');
const assigning = ref(false);
const allSelected = computed({
    get: () => props.rows.length > 0 && selected.value.length === props.rows.length,
    set: (value) => (selected.value = value ? props.rows.map((r) => r.loan_id) : []),
});

async function assign() {
    if (!selected.value.length) return;
    assigning.value = true;
    try {
        const { data } = await axios.post(route('portfolio.overdue.assign'), { loan_ids: selected.value, collector_id: collectorId.value || null });
        toast.success(data.message);
        selected.value = [];
        router.reload({ preserveScroll: true });
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo asignar.');
    } finally {
        assigning.value = false;
    }
}
</script>

<template>
    <Head title="Cartera vencida" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <Card class="mt-5">
                <CardHeader class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle class="text-2xl">Cartera vencida</CardTitle>
                            <CardDescription>
                                Clientes con cuotas pasadas de su fecha límite sin pagar<span v-if="graceDays.NORMAL > 0 || graceDays.FUNERARIO > 0"> (días de gracia: personal {{ graceDays.NORMAL ?? 0 }}, con garantía {{ graceDays.FUNERARIO ?? 0 }})</span>.
                                <template v-if="onlyMine"> Se muestran solo los préstamos que tienes asignados.</template>
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <label v-if="!onlyMine" class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" :checked="filters.mine" @change="toggleMine" />
                                Solo mis asignados
                            </label>
                            <a
                                :href="exportUrl"
                                class="rounded-lg border border-vermilion-500 px-4 py-2 text-sm font-medium text-vermilion-600 hover:bg-vermilion-50"
                            >
                                Descargar CSV
                            </a>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 p-4 pt-0">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Clientes en mora</dt>
                            <dd class="text-2xl font-bold text-red-700">{{ totals.clients }}</dd>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Cuotas vencidas</dt>
                            <dd class="text-2xl font-bold text-red-700">{{ totals.installments }}</dd>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Monto vencido</dt>
                            <dd class="text-2xl font-bold text-red-700">$ {{ currency(totals.amount) }}</dd>
                        </div>
                    </dl>

                    <form v-if="canAssign && rows.length" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3" @submit.prevent="assign">
                        <div>
                            <label for="collector" class="mb-1 block text-sm font-medium text-gray-700">Asignar gestor a los seleccionados</label>
                            <select id="collector" v-model="collectorId" class="rounded-lg border border-gray-300 bg-white p-2 text-sm">
                                <option value="">Sin gestor</option>
                                <option v-for="c in collectors" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="!selected.length || assigning" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50">
                            Asignar{{ selected.length ? ` (${selected.length})` : '' }}
                        </button>
                    </form>

                    <div class="relative overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 uppercase text-gray-700">
                                <tr>
                                    <th v-if="canAssign" class="px-4 py-3">
                                        <input v-if="rows.length" v-model="allSelected" type="checkbox" aria-label="Seleccionar todos" />
                                    </th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Contacto</th>
                                    <th class="px-4 py-3">Cuotas vencidas</th>
                                    <th class="px-4 py-3">Monto vencido</th>
                                    <th class="px-4 py-3">Atraso</th>
                                    <th class="px-4 py-3">Desde</th>
                                    <th class="px-4 py-3">Gestor</th>
                                    <th class="px-4 py-3">Seguimiento</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="rows.length === 0">
                                    <td :colspan="canAssign ? 10 : 9" class="px-4 py-10 text-center text-green-700">
                                        {{ filters.mine ? 'No tienes préstamos vencidos asignados.' : 'No hay clientes en mora. ¡Cartera al corriente!' }}
                                    </td>
                                </tr>
                                <tr v-for="row in rows" :key="row.loan_id" class="border-t bg-white hover:bg-gray-50">
                                    <td v-if="canAssign" class="px-4 py-3">
                                        <input v-model="selected" :value="row.loan_id" type="checkbox" :aria-label="`Seleccionar ${row.customer}`" />
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a :href="route('customers.show', row.customer_id)" class="hover:underline">{{ row.customer }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a v-if="row.phone" :href="`tel:${row.phone}`" class="block text-blue-600 hover:underline">{{ row.phone }}</a>
                                        <a v-if="row.email" :href="`mailto:${row.email}`" class="block text-xs text-blue-600 hover:underline">{{ row.email }}</a>
                                    </td>
                                    <td class="px-4 py-3">{{ row.overdue_installments }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        $ {{ currency(row.overdue_amount) }}
                                        <span v-if="row.moratory_amount > 0" class="block text-xs font-normal text-red-700">+ $ {{ currency(row.moratory_amount) }} de moratorios con IVA</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['rounded px-2.5 py-0.5 text-xs font-semibold', severity(row.days_overdue)]">{{ row.days_overdue }} días</span>
                                    </td>
                                    <td class="px-4 py-3">{{ row.oldest_due_date }}</td>
                                    <td class="px-4 py-3">{{ row.collector?.name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        <span v-if="row.promise" class="block font-medium text-blue-700">Promete $ {{ currency(row.promise.amount) }} el {{ shortDate(row.promise.date) }}</span>
                                        <span v-if="row.last_action" class="block text-gray-600">{{ shortDate(row.last_action.date) }}: {{ outcomes[row.last_action.outcome] ?? row.last_action.outcome }}</span>
                                        <span v-if="!row.promise && !row.last_action" class="text-orange-700">Sin gestiones</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a :href="route('loans.payments.index', row.loan_id)" class="font-medium text-vermilion-600 hover:underline">Gestionar</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
