<script setup>
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { currency } from '@/lib/formaters';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loan: { type: Object, required: true },
    summary: { type: Object, required: true },
    movements: { type: Array, default: () => [] },
    kinds: { type: Object, default: () => ({}) },
    isAdmin: { type: Boolean, default: false },
});

const toast = useToast();
const summary = ref(props.summary);
const details = ref([...(props.loan.payment?.details ?? [])].sort((a, b) => a.number - b.number));
const movements = ref([...props.movements]);
const approved = computed(() => Boolean(props.loan.approved_at));
const settled = computed(() => details.value.every((d) => d.status === 'S'));

const today = new Date().toISOString().slice(0, 10);
const methods = [
    { value: 'TRANSFERENCIA', label: 'Transferencia bancaria' },
    { value: 'DEPOSITO', label: 'Depósito bancario' },
    { value: 'EFECTIVO', label: 'Efectivo' },
    { value: 'OTRO', label: 'Otro' },
];
const methodLabel = { STRIPE: 'Tarjeta (Stripe)', ...Object.fromEntries(methods.map((m) => [m.value, m.label])) };

const longDate = (value) => (value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'medium' }) : '-');
const dueIso = (d) => d.due_date ?? `${d.year}-${String(d.month).padStart(2, '0')}-28`;
const paidOf = (d) => Number(d.iva_paid || 0) + Number(d.interest_paid || 0) + Number(d.principal_paid || 0);

const state = (d) => {
    if (d.status === 'S') return { label: 'Pagado', class: 'bg-green-100 text-green-800' };
    const days = Math.floor((new Date(`${today}T12:00:00`) - new Date(`${dueIso(d)}T12:00:00`)) / 86400000);
    if (days > 0) return { label: `Vencido ${days} d`, class: 'bg-red-100 text-red-800' };
    if (paidOf(d) > 0) return { label: 'Abono parcial', class: 'bg-blue-100 text-blue-800' };
    return { label: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' };
};

// ---- Registrar pago -------------------------------------------------------------------------
const open = ref(false);
const saving = ref(false);
const errors = ref({});
const payoff = ref(null);
const form = ref({ kind: 'adelantado', method: 'TRANSFERENCIA', reference: '', paid_at: today, amount: '', notes: '' });

const needsReference = computed(() => ['TRANSFERENCIA', 'DEPOSITO'].includes(form.value.method));
const kindHelp = {
    adelantado: 'Cubre primero lo vencido (moratorios, IVA, intereses y capital) y el resto se aplica a las cuotas siguientes.',
    anticipado: 'Cubre lo vencido y el resto reduce el capital; las cuotas restantes se recalculan. Requiere estar al corriente y al menos una parcialidad.',
    liquidacion: 'Liquida el crédito a la fecha: lo vencido, el interés del periodo en curso y el capital insoluto (más penalización si aplica).',
};

function openDialog(kind = 'adelantado') {
    errors.value = {};
    payoff.value = null;
    form.value = {
        kind,
        method: 'TRANSFERENCIA',
        reference: '',
        paid_at: today,
        amount: summary.value.amount_due_today > 0 ? summary.value.amount_due_today : summary.value.next_installment?.amount ?? '',
        notes: '',
    };
    open.value = true;
    if (kind === 'liquidacion') loadPayoff();
}

async function loadPayoff() {
    try {
        const { data } = await axios.get(route('loans.payoff', props.loan.id), { params: { date: form.value.paid_at } });
        payoff.value = data.data;
        form.value.amount = data.data.total;
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo calcular la liquidación.');
    }
}

watch(() => [form.value.kind, form.value.paid_at], () => {
    if (open.value && form.value.kind === 'liquidacion') loadPayoff();
    else payoff.value = null;
});

function applyResult(data) {
    if (data.details) details.value = [...data.details].sort((a, b) => a.number - b.number);
    if (data.movement) {
        const index = movements.value.findIndex((m) => m.id === data.movement.id);
        if (index === -1) movements.value.unshift(data.movement);
        else movements.value[index] = data.movement;
    }
    summary.value = data.summary;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await axios.post(route('loans.payments.receive', props.loan.id), {
            ...form.value,
            reference: form.value.reference || null,
            notes: form.value.notes || null,
        });
        applyResult(data.data);
        open.value = false;
        toast.success(data.message);
    } catch (e) {
        const list = e.response?.data?.errors;
        if (list) {
            errors.value = Object.fromEntries(Object.entries(list).map(([k, v]) => [k, v[0]]));
        } else {
            toast.error(e.response?.data?.message || 'No se pudo registrar el pago.');
        }
    } finally {
        saving.value = false;
    }
}

// ---- Revertir (solo administrador, el pago más reciente) -----------------------------------
const latestActive = computed(() => movements.value.find((m) => !m.reverted_at));

async function revert(movement) {
    const result = await Swal.fire({
        title: '¿Revertir este pago?',
        text: `Las cuotas vuelven a como estaban antes del pago de $ ${currency(movement.amount)}. Úsalo solo para corregir un error de captura.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, revertir',
    });
    if (!result.isConfirmed) return;

    try {
        const { data } = await axios.post(route('admin.movements.revert', movement.id));
        applyResult(data.data);
        toast.success(data.message);
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo revertir el pago.');
    }
}
</script>

<template>
    <section class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-900">Cartera y pagos</h3>
            <div v-if="approved && !settled" class="flex flex-wrap gap-2">
                <Button size="sm" @click="openDialog('adelantado')">Registrar pago</Button>
                <Button size="sm" variant="outline" @click="openDialog('anticipado')">Pago anticipado a capital</Button>
                <Button size="sm" variant="outline" @click="openDialog('liquidacion')">Liquidar</Button>
            </div>
        </div>

        <p v-if="summary.is_overdue" role="alert" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <strong>En mora:</strong> {{ summary.overdue_installments }} cuota(s) vencida(s) por $ {{ currency(summary.overdue_amount) }}
            más $ {{ currency(summary.moratory_amount) }} de moratorios con IVA; {{ summary.days_overdue }} días de atraso.
            Para ponerse al corriente hoy: <strong>$ {{ currency(summary.amount_due_today) }}</strong>.
        </p>

        <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3">
                <dt class="text-xs uppercase text-gray-500">Saldo pendiente</dt>
                <dd class="text-lg font-bold text-gray-900">$ {{ currency(summary.pending_amount) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <dt class="text-xs uppercase text-gray-500">Total pagado</dt>
                <dd class="text-lg font-bold text-green-700">$ {{ currency(summary.paid_amount) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <dt class="text-xs uppercase text-gray-500">Saldo de capital</dt>
                <dd class="text-lg font-bold text-gray-900">$ {{ currency(summary.principal_balance) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <dt class="text-xs uppercase text-gray-500">Etapa</dt>
                <dd class="text-lg font-bold text-gray-900">{{ summary.stage_label }}</dd>
            </div>
        </dl>

        <p v-if="!approved" class="mt-4 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-900">
            El préstamo aún no está aprobado; los pagos se pueden registrar hasta que lo esté.
        </p>

        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                    <tr>
                        <th class="px-3 py-3">Cuota</th>
                        <th class="px-3 py-3">Fecha límite</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3 text-right">Cuota</th>
                        <th class="px-3 py-3 text-right">Abonado</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3 text-right">Recibo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in details" :key="d.id" class="border-t bg-white">
                        <td class="px-3 py-2 font-medium text-gray-900">{{ d.number }}</td>
                        <td class="px-3 py-2">{{ longDate(dueIso(d)) }}</td>
                        <td class="px-3 py-2">
                            <span :class="['rounded px-2.5 py-0.5 text-xs font-medium', state(d).class]">{{ state(d).label }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900">$ {{ currency(d.monthly_payment) }}</td>
                        <td class="px-3 py-2 text-right">
                            $ {{ currency(paidOf(d)) }}
                            <span v-if="Number(d.moratory_paid) > 0" class="block text-xs text-red-700">
                                + $ {{ currency(Number(d.moratory_paid) + Number(d.moratory_iva_paid)) }} moratorios
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <template v-if="d.status === 'S'">
                                {{ longDate(d.payed_at) }} · {{ methodLabel[d.payment_method] ?? d.payment_method }}
                                <span v-if="d.payment_reference" class="block text-xs text-gray-500">Ref. {{ d.payment_reference }}</span>
                                <span v-if="d.notes" class="block text-xs text-gray-500">{{ d.notes }}</span>
                            </template>
                            <span v-else>-</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a
                                v-if="d.status === 'S'"
                                :href="route('loans.payments.receipt', { loan: loan.id, detail: d.id })"
                                class="font-medium text-vermilion-600 hover:underline"
                            >
                                PDF
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="movements.length" class="mt-6">
            <h4 class="text-base font-semibold text-gray-900">Historial de pagos</h4>
            <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                        <tr>
                            <th class="px-3 py-3">Fecha</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Forma de pago</th>
                            <th class="px-3 py-3 text-right">Importe</th>
                            <th class="px-3 py-3">Aplicado a</th>
                            <th class="px-3 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in movements" :key="m.id" :class="['border-t', m.reverted_at ? 'bg-gray-50 text-gray-400 line-through' : 'bg-white']">
                            <td class="px-3 py-2">{{ longDate(m.paid_at) }}</td>
                            <td class="px-3 py-2">{{ kinds[m.kind] ?? m.kind }}</td>
                            <td class="px-3 py-2">
                                {{ methodLabel[m.method] ?? m.method }}
                                <span v-if="m.reference" class="block text-xs">Ref. {{ m.reference }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">$ {{ currency(m.amount) }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span v-for="(a, id) in m.allocations.details" :key="id" class="mr-2 inline-block">cuota {{ a.number }}</span>
                                <span v-if="m.allocations.principal_prepayment > 0" class="inline-block">capital $ {{ currency(m.allocations.principal_prepayment) }}</span>
                                <span v-if="m.allocations.penalty > 0" class="inline-block">penalización $ {{ currency(m.allocations.penalty + m.allocations.penalty_iva) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    v-if="isAdmin && latestActive?.id === m.id && m.method !== 'STRIPE'"
                                    type="button"
                                    class="font-medium text-red-600 hover:underline"
                                    @click="revert(m)"
                                >
                                    Revertir
                                </button>
                                <span v-else-if="m.reverted_at" class="text-xs no-underline">Revertido</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ kinds[form.kind] ?? 'Registrar pago' }}</DialogTitle>
                    <DialogDescription>{{ kindHelp[form.kind] }}</DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="save">
                    <div>
                        <label for="pay-kind" class="mb-1 block text-sm font-medium text-gray-700">Tipo de pago</label>
                        <select id="pay-kind" v-model="form.kind" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm">
                            <option v-for="(label, value) in kinds" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <p v-if="errors.kind" class="mt-1 text-sm text-red-600">{{ errors.kind }}</p>
                    </div>
                    <div>
                        <label for="pay-method" class="mb-1 block text-sm font-medium text-gray-700">Forma de pago</label>
                        <select id="pay-method" v-model="form.method" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm">
                            <option v-for="m in methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <p v-if="errors.method" class="mt-1 text-sm text-red-600">{{ errors.method }}</p>
                    </div>
                    <div>
                        <label for="pay-ref" class="mb-1 block text-sm font-medium text-gray-700">
                            Referencia o clave de rastreo <span v-if="needsReference" class="text-red-500">*</span>
                        </label>
                        <input id="pay-ref" v-model="form.reference" type="text" maxlength="120" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                        <p v-if="errors.reference" class="mt-1 text-sm text-red-600">{{ errors.reference }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="pay-date" class="mb-1 block text-sm font-medium text-gray-700">Fecha del pago</label>
                            <input id="pay-date" v-model="form.paid_at" type="date" :max="today" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                            <p v-if="errors.paid_at" class="mt-1 text-sm text-red-600">{{ errors.paid_at }}</p>
                        </div>
                        <div>
                            <label for="pay-amount" class="mb-1 block text-sm font-medium text-gray-700">Importe recibido</label>
                            <input
                                id="pay-amount"
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :readonly="form.kind === 'liquidacion'"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm"
                            />
                            <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                        </div>
                    </div>
                    <dl v-if="payoff" class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-gray-50 p-3 text-sm">
                        <dt class="text-gray-500">Vencido (con moratorios)</dt><dd class="text-right">$ {{ currency(payoff.exigible) }}</dd>
                        <dt class="text-gray-500">Interés del periodo en curso + IVA</dt><dd class="text-right">$ {{ currency(payoff.accrued_interest + payoff.accrued_iva) }}</dd>
                        <dt class="text-gray-500">Capital insoluto</dt><dd class="text-right">$ {{ currency(payoff.principal) }}</dd>
                        <template v-if="payoff.penalty > 0">
                            <dt class="text-gray-500">Penalización + IVA</dt><dd class="text-right">$ {{ currency(payoff.penalty + payoff.penalty_iva) }}</dd>
                        </template>
                        <dt class="font-semibold text-gray-900">Total para liquidar</dt><dd class="text-right font-semibold">$ {{ currency(payoff.total) }}</dd>
                    </dl>
                    <div>
                        <label for="pay-notes" class="mb-1 block text-sm font-medium text-gray-700">Notas</label>
                        <input id="pay-notes" v-model="form.notes" type="text" maxlength="255" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="open = false">Cancelar</Button>
                        <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : 'Registrar pago' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
