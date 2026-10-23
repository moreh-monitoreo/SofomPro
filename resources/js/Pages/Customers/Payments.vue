<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import Checkout from '@/Components/stripe/Checkout.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head } from '@inertiajs/vue3';
import { CircleCheckBig, FileDown } from 'lucide-vue-next';

const props = defineProps({
    customer: Object,
    loan: Object,
    summary: Object,
    isStaff: { type: Boolean, default: false },
});

const details = props.loan.payment?.details ?? [];
const todayIso = new Date().toISOString().slice(0, 10);

const longDate = (iso) => (iso ? new Date(`${String(iso).slice(0, 10)}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'medium' }) : '-');

const state = (payment) => {
    if (payment.status === 'S') return { label: 'Pagado', class: 'bg-green-100 text-green-800' };
    const due = payment.due_date ?? `${payment.year}-${String(payment.month).padStart(2, '0')}-28`;
    if (due < todayIso) return { label: 'Vencido', class: 'bg-red-100 text-red-800' };
    return { label: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' };
};

const vat = (payment) => (payment.monthly_payment - payment.principal_payment - payment.interest_payment).toFixed(2);
const nextId = props.summary.next_installment?.id;
const methodLabel = { STRIPE: 'Tarjeta', TRANSFERENCIA: 'Transferencia', DEPOSITO: 'Depósito', EFECTIVO: 'Efectivo', OTRO: 'Otro' };
</script>

<template>
    <Head title="Pagos - Préstamo" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <Card class="mt-5">
                <CardHeader class="flex flex-col p-4">
                    <CardTitle class="text-2xl">Pagos y recibos</CardTitle>
                    <CardDescription>
                        {{ customer.full_name }} · préstamo de $ {{ currency(loan.amount) }} a {{ loan.term }} meses ·
                        <strong>{{ summary.customer_stage_label }}</strong>
                    </CardDescription>
                </CardHeader>

                <CardContent class="w-full space-y-6 p-4">
                    <p v-if="summary.is_overdue" role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                        <strong>{{ summary.overdue_installments }} cuota(s) vencida(s)</strong> por $ {{ currency(summary.overdue_amount) }},
                        con {{ summary.days_overdue }} días de atraso.
                    </p>

                    <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Saldo pendiente</dt>
                            <dd class="text-xl font-extrabold text-gray-900">$ {{ currency(summary.pending_amount) }}</dd>
                            <dd class="text-xs text-gray-500">Cuotas por pagar con intereses e IVA</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Total pagado</dt>
                            <dd class="text-xl font-extrabold text-green-700">$ {{ currency(summary.paid_amount) }}</dd>
                            <dd class="text-xs text-gray-500">{{ summary.installments_paid }} de {{ summary.installments }} cuotas</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Saldo de capital</dt>
                            <dd class="text-xl font-extrabold text-gray-900">$ {{ currency(summary.principal_balance) }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Próximo pago</dt>
                            <template v-if="summary.next_installment">
                                <dd class="text-xl font-extrabold text-gray-900">$ {{ currency(summary.next_installment.amount) }}</dd>
                                <dd class="text-xs text-gray-500">Límite: {{ longDate(summary.next_installment.due_date) }}</dd>
                            </template>
                            <dd v-else class="font-semibold text-emerald-700">Liquidado</dd>
                        </div>
                    </dl>

                    <p v-if="details.length === 0" class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                        Tu plan de pagos todavía no está disponible. Aparecerá aquí en cuanto se genere.
                    </p>

                    <Table v-else>
                        <TableCaption>Historial y calendario de pagos del préstamo</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Cuota</TableHead>
                                <TableHead>Fecha límite</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-right">Capital</TableHead>
                                <TableHead class="text-right">Interés</TableHead>
                                <TableHead class="text-right">IVA</TableHead>
                                <TableHead class="text-right">Cuota</TableHead>
                                <TableHead>Pagada el</TableHead>
                                <TableHead class="text-right">Acción</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="payment in details" :key="`payment-${payment.id}`" :class="payment.id === nextId ? 'bg-orange-50' : ''">
                                <TableCell class="font-medium">{{ payment.number }}</TableCell>
                                <TableCell>{{ longDate(payment.due_date ?? `${payment.year}-${payment.month}-28`) }}</TableCell>
                                <TableCell>
                                    <span :class="['rounded px-2.5 py-0.5 text-xs font-medium', state(payment).class]">{{ state(payment).label }}</span>
                                </TableCell>
                                <TableCell class="text-right">{{ currency(payment.principal_payment) }}</TableCell>
                                <TableCell class="text-right">{{ currency(payment.interest_payment) }}</TableCell>
                                <TableCell class="text-right">{{ currency(vat(payment)) }}</TableCell>
                                <TableCell class="text-right font-semibold">$ {{ currency(payment.monthly_payment) }}</TableCell>
                                <TableCell>
                                    <template v-if="payment.status === 'S'">
                                        {{ longDate(payment.payed_at) }}
                                        <span class="block text-xs text-gray-500">{{ methodLabel[payment.payment_method] ?? payment.payment_method }}</span>
                                    </template>
                                    <span v-else>-</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <a
                                        v-if="payment.status === 'S'"
                                        :href="route('loans.payments.receipt', { loan: loan.id, detail: payment.id })"
                                        class="inline-flex items-center gap-1 text-sm font-medium text-vermilion-600 hover:underline"
                                    >
                                        <CircleCheckBig :size="16" class="text-green-600" />
                                        <FileDown :size="16" />
                                        Recibo PDF
                                    </a>
                                    <a
                                        v-else-if="isStaff"
                                        :href="route('loans.payments.index', loan.id)"
                                        class="text-sm font-medium text-blue-600 hover:underline"
                                    >
                                        Registrar pago
                                    </a>
                                    <Checkout v-else :payload="{ payment_id: payment.id }">Pagar $ {{ currency(payment.monthly_payment) }}</Checkout>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
