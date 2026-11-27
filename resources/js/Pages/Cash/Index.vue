<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    filters: { type: Object, required: true },
    methods: { type: Object, required: true },
    movements: { type: Array, required: true },
    totals: { type: Object, required: true },
    byUser: { type: Array, required: true },
    cut: { type: Object, default: null },
    canClose: { type: Boolean, default: false },
    pendingReconciliation: { type: Number, default: 0 },
});

const toast = useToast();
const form = reactive({ ...props.filters });
const apply = () => router.get(route('cash.index'), form, { preserveScroll: true, replace: true });
const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const showToday = () => {
    Object.assign(form, { from: today(), to: today(), status: 'all' });
    apply();
};
const showPending = () => {
    Object.assign(form, { status: 'pending' });
    apply();
};

const money = (value) => Number(value || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const dateTime = (value) => new Date(value).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
const longDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'long' });
const singleDay = computed(() => props.filters.from === props.filters.to);
const exportUrl = computed(() => route('cash.export', props.filters));

// ---- Cierre del día -----------------------------------------------------------------------
const cashExpected = computed(() => props.totals.by_method?.EFECTIVO?.amount ?? 0);
const closing = reactive({ cash_counted: '', notes: '' });
const closeErrors = ref({});
const closingBusy = ref(false);
const difference = computed(() => (closing.cash_counted === '' ? null : Math.round((Number(closing.cash_counted) - cashExpected.value) * 100) / 100));

async function closeDay() {
    closingBusy.value = true;
    closeErrors.value = {};
    try {
        const { data } = await axios.post(route('cash.close'), { date: props.filters.from, ...closing });
        toast.success(data.message, { timeout: 8000 });
        router.reload({ preserveScroll: true });
    } catch (error) {
        const errors = error.response?.data?.errors;
        if (errors) closeErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
        else toast.error(error.response?.data?.message || 'No se pudo cerrar el corte.');
    } finally {
        closingBusy.value = false;
    }
}

// ---- Conciliación -------------------------------------------------------------------------
const selected = ref([]);
const bankReference = ref('');
const reconciling = ref(false);
const reconcilable = computed(() => props.movements.filter((m) => m.needs_reconciliation));
const selectedTotal = computed(() => props.movements.filter((m) => selected.value.includes(m.id)).reduce((sum, m) => sum + m.amount, 0));
const selectedNet = computed(() =>
    props.movements.filter((m) => selected.value.includes(m.id)).reduce((sum, m) => sum + (m.processor_net ?? m.amount), 0),
);
const allSelected = computed({
    get: () => reconcilable.value.length > 0 && selected.value.length === reconcilable.value.length,
    set: (value) => (selected.value = value ? reconcilable.value.map((m) => m.id) : []),
});

async function reconcile() {
    if (!selected.value.length) return;
    if (!bankReference.value.trim()) {
        toast.error('Captura la referencia del movimiento bancario o del depósito de Stripe.');
        return;
    }
    reconciling.value = true;
    try {
        const { data } = await axios.post(route('cash.reconcile'), { ids: selected.value, bank_reference: bankReference.value.trim() });
        toast.success(data.message, { timeout: 8000 });
        selected.value = [];
        bankReference.value = '';
        router.reload({ preserveScroll: true });
    } catch (error) {
        const errors = error.response?.data?.errors;
        toast.error((errors && Object.values(errors)[0]?.[0]) || error.response?.data?.message || 'No se pudo conciliar.');
    } finally {
        reconciling.value = false;
    }
}
</script>

<template>
    <Head title="Corte de caja" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 pt-0">
            <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Corte de caja y conciliación</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Pagos aplicados por fecha de pago, sin los revertidos. El efectivo se cuadra al cerrar el día; lo demás se concilia contra el estado de cuenta.
                    </p>
                </div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                    <div>
                        <label for="cash-from" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
                        <input id="cash-from" v-model="form.from" type="date" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm" />
                    </div>
                    <div>
                        <label for="cash-to" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
                        <input id="cash-to" v-model="form.to" type="date" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm" />
                    </div>
                    <div>
                        <label for="cash-status" class="mb-1 block text-sm font-medium text-gray-700">Mostrar</label>
                        <select id="cash-status" v-model="form.status" class="rounded-lg border border-gray-300 bg-gray-50 p-2 text-sm">
                            <option value="all">Todos los pagos</option>
                            <option value="pending">Por conciliar</option>
                            <option value="reconciled">Conciliados</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Aplicar</button>
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showToday">Hoy</button>
                    <a :href="exportUrl" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Exportar CSV</a>
                </form>
            </div>

            <p v-if="pendingReconciliation" class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                Hay {{ pendingReconciliation }} pago(s) por transferencia, depósito o tarjeta sin conciliar en total.
                <button type="button" class="ml-1 font-medium underline" @click="showPending">Ver los de este periodo</button>
            </p>

            <!-- Totales por forma de pago -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">Total cobrado</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ money(totals.total) }}</p>
                    <p class="text-xs text-gray-500">{{ totals.count }} pago(s)</p>
                </div>
                <div v-for="(row, method) in totals.by_method" :key="method" class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-sm text-gray-600">{{ methods[method] ?? method }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ money(row.amount) }}</p>
                    <p class="text-xs text-gray-500">
                        {{ row.count }} pago(s)<template v-if="row.fees"> · comisión Stripe {{ money(row.fees) }}</template>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Cierre del día -->
                <section class="rounded-lg border border-gray-200 bg-white p-5 xl:col-span-2">
                    <h2 class="text-lg font-semibold text-gray-900">Cierre del día</h2>
                    <p v-if="!singleDay" class="mt-1 text-sm text-gray-600">Elige un solo día (Desde y Hasta iguales) para ver o cerrar su corte.</p>

                    <template v-else-if="cut">
                        <p class="mt-1 text-sm text-gray-600">
                            Cerrado el {{ dateTime(cut.created_at) }} por {{ cut.closed_by?.name ?? '—' }}. Los cortes cerrados no se pueden modificar.
                        </p>
                        <dl class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs uppercase text-gray-500">Total del día</dt><dd class="font-bold text-gray-900">{{ money(cut.total) }}</dd></div>
                            <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs uppercase text-gray-500">Efectivo registrado</dt><dd class="font-bold text-gray-900">{{ money(cut.cash_expected) }}</dd></div>
                            <div class="rounded-lg bg-gray-50 p-3"><dt class="text-xs uppercase text-gray-500">Efectivo contado</dt><dd class="font-bold text-gray-900">{{ money(cut.cash_counted) }}</dd></div>
                            <div :class="['rounded-lg p-3', cut.cash_difference === 0 ? 'bg-green-50' : 'bg-red-50']">
                                <dt class="text-xs uppercase text-gray-500">Diferencia</dt>
                                <dd :class="['font-bold', cut.cash_difference === 0 ? 'text-green-800' : 'text-red-800']">
                                    {{ cut.cash_difference === 0 ? 'Cuadra' : money(cut.cash_difference) }}
                                </dd>
                            </div>
                        </dl>
                        <p v-if="cut.notes" class="mt-2 text-sm text-gray-700"><span class="font-medium">Notas:</span> {{ cut.notes }}</p>
                        <p v-if="movements.some((m) => m.after_cut)" class="mt-2 text-sm text-orange-700">
                            Hay pagos de este día registrados después del cierre (marcados en la tabla); no están en los totales del corte.
                        </p>
                    </template>

                    <form v-else-if="canClose" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3" @submit.prevent="closeDay">
                        <div>
                            <p class="text-sm text-gray-600">Efectivo registrado en el sistema</p>
                            <p class="text-xl font-semibold text-gray-900">{{ money(cashExpected) }}</p>
                        </div>
                        <div>
                            <label for="cash-counted" class="mb-1 block text-sm font-medium text-gray-900">Efectivo contado en caja</label>
                            <input id="cash-counted" v-model="closing.cash_counted" type="number" min="0" step="0.01" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm" />
                            <p v-if="closeErrors.cash_counted" class="mt-1 text-sm text-red-600">{{ closeErrors.cash_counted }}</p>
                            <p v-else-if="difference !== null" :class="['mt-1 text-sm', difference === 0 ? 'text-green-700' : 'text-red-700']">
                                {{ difference === 0 ? 'Cuadra con lo registrado.' : `Diferencia de ${money(difference)}.` }}
                            </p>
                        </div>
                        <div>
                            <label for="cash-notes" class="mb-1 block text-sm font-medium text-gray-900">Notas (opcional)</label>
                            <input id="cash-notes" v-model="closing.notes" type="text" maxlength="500" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm" placeholder="Ej. faltante por cambio" />
                        </div>
                        <div class="md:col-span-3 flex items-center justify-between gap-4">
                            <p class="text-xs text-gray-500">Al cerrar se guardan los totales de {{ longDate(filters.from) }} y ya no se pueden cambiar.</p>
                            <button type="submit" :disabled="closingBusy" class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60">
                                Cerrar el día
                            </button>
                        </div>
                    </form>
                    <p v-else class="mt-1 text-sm text-gray-600">No se puede cerrar un día que aún no llega.</p>
                </section>

                <!-- Por persona -->
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="text-lg font-semibold text-gray-900">Por quién registró</h2>
                    <p v-if="!byUser.length" class="mt-2 text-sm text-gray-600">Sin pagos en el periodo.</p>
                    <table v-else class="mt-3 w-full text-left text-sm text-gray-700">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr><th class="py-2 pr-4">Persona</th><th class="py-2 pr-4 text-right">Pagos</th><th class="py-2 text-right">Importe</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in byUser" :key="row.name" class="border-t border-gray-100">
                                <td class="py-2 pr-4">{{ row.name }}</td>
                                <td class="py-2 pr-4 text-right">{{ row.count }}</td>
                                <td class="py-2 text-right">{{ money(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- Pagos -->
            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Pagos</h2>
                        <p class="text-sm text-gray-600">Selecciona los pagos que encontraste en el estado de cuenta (o en un depósito de Stripe) y concílialos con su referencia.</p>
                    </div>
                    <form v-if="reconcilable.length" class="flex flex-wrap items-end gap-3" @submit.prevent="reconcile">
                        <div>
                            <label for="bank-reference" class="mb-1 block text-sm font-medium text-gray-700">Referencia bancaria</label>
                            <input id="bank-reference" v-model="bankReference" type="text" maxlength="120" class="rounded-lg border border-gray-300 p-2 text-sm" placeholder="Ej. SPEI 1234 o po_…" />
                        </div>
                        <button type="submit" :disabled="!selected.length || reconciling" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 disabled:opacity-50">
                            Conciliar {{ selected.length ? `${selected.length} · ${money(selectedTotal)}` : '' }}
                        </button>
                        <p v-if="selected.length && selectedNet !== selectedTotal" class="w-full text-right text-xs text-gray-500">
                            Neto esperado en banco (después de comisiones de Stripe): {{ money(selectedNet) }}
                        </p>
                    </form>
                </div>

                <p v-if="!movements.length" class="mt-4 text-sm text-gray-600">No hay pagos en el periodo.</p>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">
                                    <input v-if="reconcilable.length" v-model="allSelected" type="checkbox" aria-label="Seleccionar todos los pagos por conciliar" />
                                </th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Préstamo</th>
                                <th class="py-2 pr-4">Forma de pago</th>
                                <th class="py-2 pr-4">Referencia</th>
                                <th class="py-2 pr-4 text-right">Importe</th>
                                <th class="py-2 pr-4">Registró</th>
                                <th class="py-2">Conciliación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in movements" :key="m.id" class="border-t border-gray-100">
                                <td class="py-2 pr-3">
                                    <input v-if="m.needs_reconciliation" v-model="selected" :value="m.id" type="checkbox" :aria-label="`Seleccionar pago ${m.id}`" />
                                </td>
                                <td class="whitespace-nowrap py-2 pr-4">
                                    {{ dateTime(m.paid_at) }}
                                    <span v-if="m.after_cut" class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-xs text-orange-800">después del corte</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <a :href="route('loans.payments.index', m.loan_id)" class="text-blue-600 underline">{{ m.folio }}</a>
                                    <span class="block text-xs text-gray-500">{{ m.customer }}</span>
                                </td>
                                <td class="py-2 pr-4">{{ methods[m.method] ?? m.method }}</td>
                                <td class="py-2 pr-4">{{ m.reference ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right">
                                    {{ money(m.amount) }}
                                    <span v-if="m.processor_fee !== null" class="block text-xs text-gray-500">comisión {{ money(m.processor_fee) }}</span>
                                </td>
                                <td class="py-2 pr-4">{{ m.registered_by ?? '—' }}</td>
                                <td class="py-2">
                                    <span v-if="m.method === 'EFECTIVO'" class="text-gray-500">En el corte</span>
                                    <span v-else-if="m.reconciled_at" class="text-green-700">✓ {{ m.bank_reference }}</span>
                                    <span v-else class="text-yellow-800">Pendiente</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
