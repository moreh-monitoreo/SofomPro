<script setup>
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head, router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loans: { type: Array, default: () => [] },
    staff: { type: Object, default: null },
    message: { type: String, default: null },
});

const toast = useToast();
if (props.message) {
    toast.info(props.message);
}

const stageStyle = {
    'En revisión': 'bg-blue-100 text-blue-800 hover:bg-blue-100',
    Aprobada: 'bg-green-100 text-green-800 hover:bg-green-100',
    Desembolsada: 'bg-teal-100 text-teal-800 hover:bg-teal-100',
    Liquidada: 'bg-emerald-100 text-emerald-900 hover:bg-emerald-100',
    Rechazada: 'bg-red-100 text-red-800 hover:bg-red-100',
};

const longDate = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'long' });

const dueText = (next) => {
    if (next.days_until_due < 0) return `venció el ${longDate(next.due_date)}`;
    if (next.days_until_due === 0) return 'vence hoy';
    if (next.days_until_due === 1) return 'vence mañana';
    return `vence el ${longDate(next.due_date)}`;
};

const progress = (summary) => (summary.installments ? Math.round((summary.installments_paid / summary.installments) * 100) : 0);

const goToCredit = () => window.location.assign(route('site.requestcredit'));
const goToPayments = (loan) => router.visit(route('loans.show.payments', { customer: loan.customer_id, loan: loan.id }));
</script>

<template>
    <Head title="Inicio" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <section v-if="staff" class="mt-5">
                <h2 class="mb-3 text-xl font-bold text-gray-900">Resumen del equipo</h2>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <a :href="route('pipeline.index', { stage: 'received' })" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:shadow-md">
                        <p class="text-sm text-gray-500">Solicitudes por atender</p>
                        <p class="text-3xl font-extrabold text-gray-900">{{ staff.received }}</p>
                        <p v-if="staff.incomplete" class="mt-1 text-xs text-gray-500">{{ staff.incomplete }} más sin terminar (incompletas)</p>
                    </a>
                    <a :href="route('pipeline.index', { stage: 'in_validation' })" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:shadow-md">
                        <p class="text-sm text-gray-500">En validación</p>
                        <p class="text-3xl font-extrabold text-blue-700">{{ staff.in_validation }}</p>
                        <p v-if="staff.validated" class="mt-1 text-xs text-indigo-700">{{ staff.validated }} validadas, listas para aprobar</p>
                    </a>
                    <a :href="route('pipeline.index', { stage: 'approved' })" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:shadow-md">
                        <p class="text-sm text-gray-500">Aprobadas sin desembolsar</p>
                        <p class="text-3xl font-extrabold text-green-700">{{ staff.approved }}</p>
                    </a>
                    <a
                        :href="route('portfolio.overdue')"
                        :class="['rounded-xl border p-4 transition hover:shadow-md', staff.overdue_clients ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white']"
                    >
                        <p class="text-sm text-gray-500">Clientes en mora</p>
                        <p :class="['text-3xl font-extrabold', staff.overdue_clients ? 'text-red-700' : 'text-green-700']">{{ staff.overdue_clients }}</p>
                        <p v-if="staff.overdue_clients" class="text-sm font-medium text-red-700">$ {{ currency(staff.overdue_amount) }} vencidos</p>
                    </a>
                </div>
            </section>

            <Card v-if="!staff" class="mt-5">
                <CardHeader class="flex flex-col p-4">
                    <CardTitle class="text-2xl">Mis préstamos</CardTitle>
                    <CardDescription>Estado de tus solicitudes, saldo y próximos pagos</CardDescription>
                </CardHeader>
                <CardContent class="w-full p-4">
                    <div v-if="loans.length === 0" class="rounded-xl border border-dashed border-gray-300 p-8 text-center">
                        <p class="text-lg font-semibold text-gray-900">Aún no tienes préstamos</p>
                        <p class="mt-1 text-sm text-gray-600">Cuando solicites uno, aquí verás su avance, tu saldo y tus pagos.</p>
                        <Button class="mt-5" @click="goToCredit">Solicitar préstamo</Button>
                    </div>

                    <div v-else class="grid gap-6 xl:grid-cols-2">
                        <article v-for="loan in loans" :key="`loan-${loan.id}`" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <header class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-lg font-bold text-gray-900">$ {{ currency(loan.amount) }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ loan.folio }} · {{ loan.type === 'FUNERARIO' ? 'Con garantía' : 'Personal' }} · {{ loan.term }} meses
                                    </p>
                                </div>
                                <Badge :class="stageStyle[loan.summary.customer_stage_label]">{{ loan.summary.customer_stage_label }}</Badge>
                            </header>

                            <p v-if="loan.summary.customer_stage_label === 'En revisión'" class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                                Estamos revisando tu solicitud y tus documentos. Te contactaremos en cuanto tengamos una respuesta.
                            </p>
                            <p v-else-if="loan.summary.customer_stage_label === 'Rechazada'" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-900">
                                Por ahora no pudimos aprobar esta solicitud. Si tienes dudas, comunícate con nosotros.
                            </p>
                            <p v-else-if="!loan.summary.has_plan" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-900">
                                ¡Tu préstamo fue aprobado! Estamos preparando tu plan de pagos.
                            </p>

                            <template v-else>
                                <p v-if="loan.summary.is_overdue" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">
                                    <strong>Tienes {{ loan.summary.overdue_installments }} cuota(s) vencida(s)</strong> por $ {{ currency(loan.summary.overdue_amount) }}
                                    ({{ loan.summary.days_overdue }} días de atraso). Págalas para ponerte al corriente.
                                </p>

                                <dl class="mt-4 grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-xs uppercase tracking-wide text-gray-500">Saldo pendiente</dt>
                                        <dd class="text-xl font-extrabold text-gray-900">$ {{ currency(loan.summary.pending_amount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs uppercase tracking-wide text-gray-500">Total pagado</dt>
                                        <dd class="text-xl font-extrabold text-green-700">$ {{ currency(loan.summary.paid_amount) }}</dd>
                                    </div>
                                    <div v-if="loan.summary.next_installment" class="col-span-2 rounded-lg bg-gray-50 p-3">
                                        <dt class="text-xs uppercase tracking-wide text-gray-500">Próximo pago (cuota {{ loan.summary.next_installment.number }})</dt>
                                        <dd class="font-semibold text-gray-900">
                                            $ {{ currency(loan.summary.next_installment.amount) }} · {{ dueText(loan.summary.next_installment) }}
                                        </dd>
                                    </div>
                                    <div v-else class="col-span-2 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-900">
                                        ¡Liquidaste este préstamo! Gracias por tu puntualidad.
                                    </div>
                                </dl>

                                <div class="mt-4">
                                    <div class="mb-1 flex justify-between text-xs text-gray-500">
                                        <span>{{ loan.summary.installments_paid }} de {{ loan.summary.installments }} cuotas pagadas</span>
                                        <span>{{ progress(loan.summary) }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-200" role="progressbar" :aria-valuenow="progress(loan.summary)" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full bg-vermilion-500 transition-all" :style="{ width: `${progress(loan.summary)}%` }"></div>
                                    </div>
                                </div>

                                <Button class="mt-5 w-full" @click="goToPayments(loan)">Ver pagos y recibos</Button>
                            </template>
                        </article>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
