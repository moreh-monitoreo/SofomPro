<script setup>
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { currency } from '@/lib/formaters';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loan: { type: Object, required: true },
    summary: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

const toast = useToast();
const summary = ref(props.summary);
const details = ref([...(props.loan.payment?.details ?? [])].sort((a, b) => a.number - b.number));
const approved = computed(() => Boolean(props.loan.approved_at));

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

const state = (d) => {
    if (d.status === 'S') return { label: 'Pagado', class: 'bg-green-100 text-green-800' };
    const days = Math.floor((new Date(`${today}T12:00:00`) - new Date(`${dueIso(d)}T12:00:00`)) / 86400000);
    if (days > 0) return { label: `Vencido ${days} d`, class: 'bg-red-100 text-red-800' };
    return { label: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' };
};

const open = ref(false);
const saving = ref(false);
const target = ref(null);
const errors = ref({});
const form = ref({ method: 'TRANSFERENCIA', reference: '', paid_at: today, amount: '', notes: '' });

const needsReference = computed(() => ['TRANSFERENCIA', 'DEPOSITO'].includes(form.value.method));

function openDialog(detail) {
    target.value = detail;
    errors.value = {};
    form.value = { method: 'TRANSFERENCIA', reference: '', paid_at: today, amount: detail.monthly_payment, notes: '' };
    open.value = true;
}

function applyResult(data) {
    const index = details.value.findIndex((d) => d.id === data.detail.id);
    if (index !== -1) details.value[index] = { ...details.value[index], ...data.detail };
    summary.value = data.summary;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await axios.post(route('loans.payments.register', [props.loan.id, target.value.id]), {
            ...form.value,
            reference: form.value.reference || null,
            amount: form.value.amount || null,
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

async function revert(detail) {
    const result = await Swal.fire({
        title: '¿Revertir este pago?',
        text: `La cuota ${detail.number} volverá a quedar pendiente y se borrará su referencia. Úsalo solo para corregir un error de captura.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, revertir',
    });
    if (!result.isConfirmed) return;

    try {
        const { data } = await axios.post(route('admin.payments.revert', detail.id));
        applyResult(data.data);
        toast.success(data.message);
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo revertir el pago.');
    }
}
</script>

<template>
    <section class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900">Cartera y pagos</h3>

        <p v-if="summary.is_overdue" role="alert" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <strong>En mora:</strong> {{ summary.overdue_installments }} cuota(s) vencida(s) por $ {{ currency(summary.overdue_amount) }},
            {{ summary.days_overdue }} días de atraso.
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
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3 text-right">Acciones</th>
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
                        <td class="px-3 py-2">
                            <template v-if="d.status === 'S'">
                                {{ longDate(d.payed_at) }} · {{ methodLabel[d.payment_method] ?? d.payment_method }}
                                <span v-if="d.payment_reference" class="block text-xs text-gray-500">Ref. {{ d.payment_reference }}</span>
                            </template>
                            <span v-else>-</span>
                        </td>
                        <td class="space-x-3 px-3 py-2 text-right">
                            <Button v-if="d.status !== 'S' && approved" size="sm" @click="openDialog(d)">Registrar pago</Button>
                            <a
                                v-if="d.status === 'S'"
                                :href="route('loans.payments.receipt', { loan: loan.id, detail: d.id })"
                                class="font-medium text-vermilion-600 hover:underline"
                            >
                                Recibo PDF
                            </a>
                            <button
                                v-if="d.status === 'S' && isAdmin && d.payment_method !== 'STRIPE'"
                                type="button"
                                class="font-medium text-red-600 hover:underline"
                                @click="revert(d)"
                            >
                                Revertir
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar pago de la cuota {{ target?.number }}</DialogTitle>
                    <DialogDescription>
                        Cuota de $ {{ currency(target?.monthly_payment) }}. El saldo del cliente y la mora se actualizan al guardar.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="save">
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
                            <input id="pay-amount" v-model="form.amount" type="number" step="0.01" min="0.01" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                            <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                        </div>
                    </div>
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
