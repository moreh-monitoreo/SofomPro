<script setup>
import PaymentsLedger from '@/Components/payments/PaymentsLedger.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loan: Object,
    summary: Object,
    movements: { type: Array, default: () => [] },
    kinds: { type: Object, default: () => ({}) },
    product: { type: Object, default: () => ({}) },
    isAdmin: { type: Boolean, default: false },
    canGeneratePlan: { type: Boolean, default: false },
});
const toast = useToast();
const generating = ref(false);

const hasPlan = Boolean(props.loan.payment?.details?.length);
const hasPayments = props.movements.some((m) => !m.reverted_at) || (props.loan.payment?.details ?? []).some((d) => d.status !== 'N');

// El plan sale de las condiciones aprobadas (monto y plazo) y de la tasa, comisión e IVA del producto
async function generatePlan() {
    if (hasPlan) {
        const result = await Swal.fire({
            title: '¿Generar de nuevo el plan?',
            text: 'Se reemplazará la tabla actual con las condiciones vigentes del producto y se actualizará la documentación.',
            icon: 'question',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Sí, generar',
        });
        if (!result.isConfirmed) return;
    }
    generating.value = true;
    try {
        const { data } = await axios.post(route('loans.payments.store', props.loan.id), { willSave: true });
        toast.success(data.message);
        router.reload();
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo generar el plan de pagos.');
    } finally {
        generating.value = false;
    }
}
</script>

<template>
    <Head title="Pagos" />

    <DashboardLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Pagos</h2>
        </template>
        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow sm:p-6 md:p-8 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h5 class="text-xl font-medium text-gray-900 dark:text-white">Plan de pagos de {{ loan.customer?.full_name }}</h5>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ Number(loan.amount).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }) }} a {{ loan.term }} meses ·
                                tasa anual {{ product.annual_rate }}% · comisión por apertura {{ product.opening_fee }}% · IVA {{ product.iva }}%
                            </p>
                            <p class="text-xs text-gray-500">
                                Moratorios: {{ product.moratory_rate }}% anual más IVA, después de {{ product.grace_days }} día(s) de gracia.
                                <template v-if="product.min_forced_months > 0">
                                    Liquidar antes de {{ product.min_forced_months }} meses tiene penalización de {{ product.early_payoff_penalty }}%.
                                </template>
                            </p>
                        </div>
                        <button
                            v-if="canGeneratePlan && loan.approved_at && !hasPayments"
                            type="button"
                            :disabled="generating"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                            @click="generatePlan"
                        >
                            {{ hasPlan ? 'Generar de nuevo el plan' : 'Generar plan con las condiciones aprobadas' }}
                        </button>
                    </div>

                    <p v-if="!hasPlan" class="mt-4 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-900">
                        <template v-if="loan.approved_at">Este préstamo todavía no tiene plan de pagos.</template>
                        <template v-else>El plan de pagos se genera automáticamente al aprobar la solicitud.</template>
                    </p>

                    <PaymentsLedger
                        v-if="hasPlan"
                        :key="`ledger-${loan.payment.id}`"
                        :loan="loan"
                        :summary="summary"
                        :movements="movements"
                        :kinds="kinds"
                        :is-admin="isAdmin"
                    ></PaymentsLedger>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
