<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { useAgentLoanStore } from '@/stores/loans.store';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DocumentUpload from '@/Components/ui/document-upload.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

const toast = useToast();

const loanStore = useAgentLoanStore();
const props = defineProps({
    loan: Object,
    account: { type: Object, default: null },
    missingDocuments: { type: Array, default: () => [] },
    validator: { type: Object, default: null },
    disbursement: { type: Object, default: () => ({ methods: {}, opening_fee: 0, opening_fee_iva: 0 }) },
    pldResults: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});
const missing = ref(props.missingDocuments);
const step = ref(0);
const loan = ref(props.loan);
const documents = ref([]);

const account = ref(props.account);
const accountLoading = ref(false);

// Reenvía al cliente el correo para crear su contraseña (nadie del personal la conoce)
async function inviteAccount() {
    const result = await Swal.fire({
        title: '¿Enviar invitación?',
        text: 'El cliente recibirá un correo con un enlace para crear su contraseña.',
        icon: 'question',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, enviar',
    });
    if (!result.isConfirmed) return;
    accountLoading.value = true;
    try {
        const response = await axios.post(route('loans.account.invitation', loan.value.id));
        toast.success(response.data.message);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo enviar la invitación');
    } finally {
        accountLoading.value = false;
    }
}

const planOpen = ref(false);
const planLoading = ref(false);
const planError = ref('');
const plan = ref(null);

const money = (v) => (v === null || v === undefined ? '—' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v));
const day = (v) => (v ? new Date(v).toLocaleDateString('es-MX', { timeZone: 'UTC' }) : '—');

// Consulta en MOREH (tabla de contratos) el plan funerario que el cliente registró como garantía
async function viewContract() {
    planOpen.value = true;
    planLoading.value = true;
    planError.value = '';
    plan.value = null;
    try {
        const response = await axios.get(route('loans.plan-funerario', loan.value.id));
        plan.value = response.data.contract;
    } catch (error) {
        planError.value = error.response?.data?.message || 'No se pudo consultar el contrato.';
    } finally {
        planLoading.value = false;
    }
}

const changeStep = (newstep) => (step.value = newstep);

// ---- Etapa de la solicitud --------------------------------------------------------------
const stageStyle = {
    incomplete: 'bg-orange-50 text-orange-800',
    received: 'bg-gray-100 text-gray-800',
    in_validation: 'bg-blue-100 text-blue-800',
    validated: 'bg-indigo-100 text-indigo-800',
    approved: 'bg-green-100 text-green-800',
    disbursed: 'bg-teal-100 text-teal-800',
    paid: 'bg-emerald-100 text-emerald-900',
    rejected: 'bg-red-100 text-red-800',
};

async function startValidation() {
    try {
        const { data } = await axios.post(route('loans.validation', loan.value.id));
        loan.value = { ...loan.value, ...data.data };
        toast.success(data.message);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo cambiar la etapa.');
    }
}

const netAmount = computed(() => Number(loan.value.amount || 0) - props.disbursement.opening_fee - props.disbursement.opening_fee_iva);

async function disburse() {
    const options = Object.entries(props.disbursement.methods)
        .map(([value, label]) => `<option value="${value}">${label}</option>`)
        .join('');
    const result = await Swal.fire({
        title: 'Registrar el desembolso',
        html: `
            <p style="font-size:14px;margin-bottom:12px">Monto neto a entregar: <strong>${money(netAmount.value)}</strong>
            (${money(loan.value.amount)} menos comisión por apertura ${money(props.disbursement.opening_fee)} e IVA ${money(props.disbursement.opening_fee_iva)})</p>
            <label style="display:block;text-align:left;font-size:14px">Forma de pago</label>
            <select id="disb-method" class="swal2-select" style="width:100%;margin:4px 0 12px">${options}</select>
            <label style="display:block;text-align:left;font-size:14px">Referencia (clave de rastreo, núm. de cheque o recibo)</label>
            <input id="disb-reference" class="swal2-input" maxlength="120" style="width:100%;margin:4px 0 12px">
            <label style="display:block;text-align:left;font-size:14px">Fecha del desembolso</label>
            <input id="disb-date" type="date" class="swal2-input" style="width:100%;margin:4px 0" value="${new Date().toISOString().slice(0, 10)}">`,
        showCancelButton: true,
        confirmButtonColor: '#2ECC71',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Registrar',
        preConfirm: () => {
            const reference = document.getElementById('disb-reference').value.trim();
            if (!reference) {
                Swal.showValidationMessage('Captura la referencia del desembolso.');
                return false;
            }
            return {
                method: document.getElementById('disb-method').value,
                reference,
                disbursed_at: document.getElementById('disb-date').value || null,
            };
        },
    });
    if (!result.isConfirmed) return;

    try {
        const { data } = await axios.post(route('loans.disburse', loan.value.id), result.value);
        loan.value = { ...loan.value, ...data.data };
        toast.success(data.message);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo registrar el desembolso.');
    }
}

// ---- Validación: Buró de Crédito y cotejo PLD (analista) ---------------------------------
const validation = ref({
    bureau_folio: loan.value.bureau_folio || '',
    bureau_result: loan.value.bureau_result || '',
    pld_result: loan.value.pld_result || 'sin_coincidencias',
    pld_evidence: null,
});
const validationErrors = ref({});
const validating = ref(false);
const canEditValidation = computed(() => props.can.validate && ['received', 'in_validation', 'validated'].includes(loan.value.stage));
const pldEvidence = computed(() => (loan.value.documentations || []).filter((d) => d.type === 'Cotejo PLD').at(-1));

async function submitValidation() {
    validating.value = true;
    validationErrors.value = {};
    try {
        const form = new FormData();
        Object.entries(validation.value).forEach(([key, value]) => value !== null && form.append(key, value));
        const { data } = await axios.post(route('loans.validate', loan.value.id), form);
        loan.value = { ...loan.value, ...data.data.loan };
        missing.value = data.data.missingDocuments;
        validation.value.pld_evidence = null;
        toast.success(data.message);
    } catch (error) {
        const errors = error.response?.data?.errors;
        if (errors) {
            validationErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
        } else {
            toast.error(error.response?.data?.message || 'No se pudo guardar la validación.');
        }
    } finally {
        validating.value = false;
    }
}

// ---- Calculadora interna: condiciones a aprobar y capacidad de pago ------------------------
const processed = computed(() => Boolean(loan.value.approved_at || loan.value.reject_at));
const approval = ref({ amount: Number(loan.value.amount) || '', term: Number(loan.value.term) || '' });
const preview = ref(null);
const previewError = ref('');
let previewTimer;

async function recalc() {
    preview.value = null;
    previewError.value = '';
    if (!approval.value.amount || !approval.value.term) return;
    try {
        const { data } = await axios.post(route('loans.preview', loan.value.id), { amount: approval.value.amount, term: approval.value.term });
        preview.value = data.data;
    } catch (error) {
        const errors = error.response?.data?.errors;
        previewError.value = errors ? Object.values(errors).flat()[0] : 'No se pudo calcular.';
    }
}

watch(approval, () => {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(recalc, 400);
}, { deep: true });

if (!processed.value && approval.value.amount && approval.value.term) recalc();

const changedConditions = computed(
    () => Number(approval.value.amount) !== Number(loan.value.amount) || Number(approval.value.term) !== Number(loan.value.term),
);

const capacity = computed(() => {
    const job = loan.value.customer?.employment;
    if (!job || !preview.value) return null;
    const income = Number(job.monthly_income || 0) + Number(job.other_income || 0);
    const hasExpenses = job.monthly_expenses !== null && job.monthly_expenses !== undefined && job.monthly_expenses !== '';
    const expenses = hasExpenses ? Number(job.monthly_expenses) : null;
    const available = expenses === null ? income : income - expenses;
    const payment = preview.value.first_payment;
    if (income <= 0) return { income, expenses, available, ratio: null, level: 'unknown', text: 'No hay ingresos capturados para evaluar la capacidad de pago.' };
    if (available <= 0) return { income, expenses, available, ratio: null, level: 'high', text: 'Los egresos igualan o superan los ingresos: el cliente no tiene margen para una cuota.' };
    const ratio = payment / available;
    const level = ratio <= 0.3 ? 'ok' : ratio <= 0.5 ? 'warn' : 'high';
    const text = {
        ok: 'La cuota representa una parte cómoda del ingreso disponible.',
        warn: 'La cuota es una parte considerable del ingreso disponible; revisa con cuidado.',
        high: 'La cuota excede la mitad del ingreso disponible: riesgo alto de impago.',
    }[level];
    return { income, expenses, available, ratio, level, text, withoutExpenses: expenses === null };
});

const capacityStyle = {
    ok: 'border-green-300 bg-green-50 text-green-900',
    warn: 'border-yellow-300 bg-yellow-50 text-yellow-900',
    high: 'border-red-300 bg-red-50 text-red-900',
    unknown: 'border-gray-300 bg-gray-50 text-gray-700',
};

const handleDocumentsUpdate = (newDocuments) => {
    documents.value = newDocuments;
};

async function rejectLoan() {
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: 'La solicitud será rechazada. Puedes anotar el motivo para el expediente.',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'Motivo del rechazo (opcional)',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonColor: '#F39C12',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si! Rechazar',
    });
    if (result.isConfirmed) {
        try {
            await axios.put(`/loans/${loan.value.id}`, {
                status: 'rejected',
                reason: result.value || null,
            });
            toast.info(
                'Solicitud Rechazada. El solicitante recibirá la notificación vía email.',
            );
            router.visit(route('loans.index'));
        } catch (error) {
            toast.error(error);
        }
    }
}

async function validateLoan() {
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: changedConditions.value
            ? `Se aprobará ${money(approval.value.amount)} a ${approval.value.term} meses (el cliente pidió ${money(loan.value.amount)} a ${loan.value.term} meses).`
            : 'La solicitud será aceptada y se avisará al solicitante',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2ECC71',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si! Aceptar',
    });
    if (result.isConfirmed) {
        try {
            const response = await axios.put(`/loans/${loan.value.id}`, {
                status: 'accepted',
                amount: approval.value.amount,
                term: approval.value.term,
            });
            toast.success(response.data.message);
            router.visit(route('loans.index'));
        } catch (error) {
            const errors = error.response?.data?.errors;
            toast.error(errors ? Object.values(errors).flat()[0] : error.response?.data?.message || 'No se pudo aprobar la solicitud.');
        }
    }
}

async function deleteDocument(documentation) {
    const result = await Swal.fire({
        title: '¿Eliminar documento?',
        text: `Se eliminará "${documentation.name}" y no se podrá recuperar.`,
        icon: 'warning',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc2626',
    });
    if (!result.isConfirmed) return;
    try {
        const response = await axios.delete(route('loans.documents.destroy', [loan.value.id, documentation.id]));
        loan.value.documentations = loan.value.documentations.filter((doc) => doc.id !== documentation.id);
        missing.value = response.data.missingDocuments ?? missing.value;
        toast.success(response.data.message);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo eliminar el documento.');
    }
}

async function updateDocuments() {
    if(documents.value.length == 0) {
        changeStep(4);
        return;
    }
    const missing = documents.value.findIndex((doc) => !doc.file || !doc.type || !doc.name?.trim());
    if (missing !== -1) {
        const doc = documents.value[missing];
        const field = !doc.type ? 'el tipo' : !doc.name?.trim() ? 'el nombre' : 'el archivo';
        toast.error(`Falta ${field} del documento #${missing + 1}.`);
        return;
    }
    try {
        const formData = new FormData();
        documents.value.forEach((document) => {
            formData.append('documents[]', document.file);
            formData.append('documentsname[]', document.name);
            formData.append('documentstype[]', document.type);
        });
        const response = await axios.post(`/loans/${loan.value.id}/documents`, formData);
        toast.success(response.data.message);
        loan.value.documentations.push(...response.data.documents);
        missing.value = response.data.missingDocuments ?? missing.value;
        documents.value.splice(0); // mismo arreglo que usa DocumentUpload, para que también se limpie
        changeStep(4);
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat()[0]
            : error.response?.status === 413
              ? 'Los archivos pesan demasiado para el servidor.'
              : error.response?.data?.message || 'No se pudieron guardar los documentos.';
        toast.error(message);
    }
}
</script>

<template>
    <Head title="Profile" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Detalle de la Solicitud</CardTitle>
                        <CardDescription>Información completa del cliente y su solicitud de préstamo</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span :class="['rounded px-3 py-1 text-sm font-semibold', stageStyle[loan.stage]]">{{ loan.stage_label }}</span>
                                <span
                                    v-if="loan.requested_amount && (Number(loan.requested_amount) !== Number(loan.amount) || Number(loan.requested_term) !== Number(loan.term))"
                                    class="text-sm text-gray-600"
                                >
                                    El cliente pidió {{ money(loan.requested_amount ?? loan.amount) }} a {{ loan.requested_term ?? loan.term }} meses ·
                                    se aprobó {{ money(loan.amount) }} a {{ loan.term }} meses
                                </span>
                                <span v-if="loan.reject_reason" class="text-sm text-red-700">Motivo del rechazo: {{ loan.reject_reason }}</span>
                                <span v-if="loan.disbursement_method" class="text-sm text-gray-600">
                                    Desembolso: {{ disbursement.methods[loan.disbursement_method] ?? loan.disbursement_method }} · ref. {{ loan.disbursement_reference }}
                                </span>
                                <span v-if="loan.stage === 'incomplete'" class="text-sm text-orange-700">El cliente aún no termina su solicitud (faltan sus documentos).</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="loan.stage === 'received'"
                                    type="button"
                                    class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                                    @click="startValidation"
                                >
                                    Iniciar validación
                                </button>
                                <button
                                    v-if="loan.stage === 'approved' && can.disburse"
                                    type="button"
                                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                    @click="disburse"
                                >
                                    Registrar desembolso
                                </button>
                                <a
                                    v-if="['approved', 'disbursed', 'paid'].includes(loan.stage)"
                                    :href="route('loans.payments.index', loan.id)"
                                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cartera y pagos
                                </a>
                            </div>
                        </div>

                        <div class="relative flex gap-4 overflow-x-auto shadow-md sm:rounded-lg">
                            <div
                                class="w-1/4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <button aria-current="true" type="button"
                                    class="w-full cursor-pointer rounded-t-lg border-b border-gray-200 bg-blue-700 px-4 py-2 text-left font-medium text-white focus:outline-none rtl:text-right dark:border-gray-600 dark:bg-gray-800"
                                    @click="changeStep(0)">
                                    Perfil
                                </button>
                                <button type="button"
                                    class="w-full cursor-pointer border-b border-gray-200 px-4 py-2 text-left font-medium hover:bg-gray-100 hover:text-blue-700 focus:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 rtl:text-right dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:text-white dark:focus:ring-gray-500"
                                    @click="changeStep(1)">
                                    Dirección
                                </button>
                                <button type="button"
                                    class="w-full cursor-pointer border-b border-gray-200 px-4 py-2 text-left font-medium hover:bg-gray-100 hover:text-blue-700 focus:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 rtl:text-right dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:text-white dark:focus:ring-gray-500"
                                    @click="changeStep(2)">
                                    Trabajo
                                </button>
                                <button type="button"
                                    class="w-full cursor-pointer border-b border-gray-200 px-4 py-2 text-left font-medium hover:bg-gray-100 hover:text-blue-700 focus:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 rtl:text-right dark:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:text-white dark:focus:ring-gray-500"
                                    @click="changeStep(3)">
                                    Documentación
                                </button>
                                <button type="button"
                                    class="w-full cursor-pointer rounded-b-lg bg-gray-100 px-4 py-2 text-left font-medium rtl:text-right dark:bg-gray-600 dark:text-gray-400"
                                    @click="changeStep(4)">
                                    Préstamo
                                </button>
                            </div>

                            <div
                                class="flex-1 rounded-lg border border-gray-200 bg-white p-4 shadow sm:p-6 md:p-8 dark:border-gray-700 dark:bg-gray-800">
                                <form class="space-y-6" action="#" v-if="step == 0">
                                    <h5 class="text-xl font-medium text-gray-900 dark:text-white">
                                        Información del Perfil
                                    </h5>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="email"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Email</label>
                                            <input type="email" name="email" id="email" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="name@company.com" :value="loan?.customer?.email" />
                                        </div>
                                        <div>
                                            <label for="phone"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Teléfono</label>
                                            <input type="text" name="phone" id="phone" placeholder="9999999999"
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.phone" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="names"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Nombre(s)</label>
                                            <input type="text" name="names" id="names" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="name@company.com"
                                                :value="`${loan?.customer?.name} ${loan?.customer?.second_name}`" />
                                        </div>
                                        <div>
                                            <label for="last_names"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Apellido(s)</label>
                                            <input type="text" name="last_names" id="last_names" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.last_name} ${loan?.customer?.maiden_name}`" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="rfc"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">RFC</label>
                                            <input type="text" name="rfc" id="rfc" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.rfc" />
                                        </div>
                                        <div>
                                            <label for="curp"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">CURP</label>
                                            <input type="text" name="curp" id="curp" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.curp" />
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <h6 class="mb-2 text-sm font-semibold text-gray-900">Cuenta para depósito y referencias</h6>
                                        <p class="text-sm">
                                            <span class="text-gray-500">CLABE:</span>
                                            <span class="ml-1 font-mono font-medium">{{ loan?.customer?.clabe || 'No capturada' }}</span>
                                        </p>
                                        <ul v-if="loan?.customer?.references?.length" class="mt-2 space-y-1 text-sm">
                                            <li v-for="reference in loan.customer.references" :key="reference.id">
                                                <span class="font-medium">{{ reference.name }}</span>
                                                <span class="text-gray-500"> · {{ reference.relationship }} · </span>
                                                <a :href="`tel:${reference.phone}`" class="text-blue-600 hover:underline">{{ reference.phone }}</a>
                                            </li>
                                        </ul>
                                        <p v-else class="mt-2 text-sm text-gray-600">Sin referencias personales capturadas.</p>
                                    </div>

                                    <div v-if="loan?.approved_at" class="rounded-lg border border-green-200 bg-green-50 p-3">
                                        <p class="mb-2 text-sm font-semibold text-gray-900">Cuenta del cliente</p>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-900">Correo (usuario)</label>
                                                <input type="text" readonly :value="account?.email || loan?.customer?.email"
                                                    class="block w-64 rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                            </div>
                                            <button type="button" :disabled="accountLoading" @click="inviteAccount"
                                                class="rounded-lg bg-gray-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60">
                                                Reenviar invitación
                                            </button>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-600">El cliente crea su propia contraseña con el enlace que recibe por correo; el personal no la puede ver.</p>
                                    </div>

                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <h6 class="mb-2 text-sm font-semibold text-gray-900">Consentimientos del solicitante</h6>
                                        <dl v-if="loan?.customer?.privacy_accepted_at" class="grid grid-cols-1 gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                                            <div>
                                                <dt class="inline text-gray-500">Aviso de Privacidad aceptado:</dt>
                                                <dd class="ml-1 inline font-medium">{{ new Date(loan.customer.privacy_accepted_at).toLocaleString('es-MX') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="inline text-gray-500">Consulta en Sociedades de Información Crediticia autorizada:</dt>
                                                <dd class="ml-1 inline font-medium">{{ loan.customer.credit_check_authorized_at ? new Date(loan.customer.credit_check_authorized_at).toLocaleString('es-MX') : 'No' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="inline text-gray-500">Versión del texto:</dt>
                                                <dd class="ml-1 inline font-medium">{{ loan.customer.consent_version }}</dd>
                                            </div>
                                            <div>
                                                <dt class="inline text-gray-500">Desde la IP:</dt>
                                                <dd class="ml-1 inline font-medium">{{ loan.customer.consent_ip }}</dd>
                                            </div>
                                        </dl>
                                        <p v-else class="text-sm text-gray-600">Esta solicitud se capturó antes de que se registraran los consentimientos.</p>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit"
                                            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                            @click="changeStep(1)">
                                            Siguiente
                                        </button>
                                    </div>
                                </form>
                                <form class="space-y-6" action="#" v-if="step == 1">
                                    <h5 class="text-xl font-medium text-gray-900 dark:text-white">
                                        Dirección
                                    </h5>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="street"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Calle</label>
                                            <input type="text" name="street" id="street" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="street" :value="loan?.customer?.addresses[0]?.street
                                                    " />
                                        </div>
                                        <div>
                                            <label for="outdoor_number"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">No.
                                                Exterior</label>
                                            <input type="text" name="outdoor_number" id="outdoor_number" placeholder="999"
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.addresses[0]
                                                        ?.outdoor_number
                                                    " />
                                        </div>
                                        <div>
                                            <label for="interior_number"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">No.
                                                Interior</label>
                                            <input type="text" name="interior_number" id="interior_number" placeholder="999"
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.addresses[0]
                                                        ?.interior_number
                                                    " />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="suburb"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Colonia</label>
                                            <input type="text" name="suburb" id="suburb" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="name@company.com" :value="loan?.customer?.addresses[0]?.suburb
                                                    " />
                                        </div>
                                        <div>
                                            <label for="postal_code"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Código
                                                Postal</label>
                                            <input type="text" name="postal_code" id="postal_code" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.addresses[0]
                                                        ?.postal_code
                                                    " />
                                        </div>
                                        <div>
                                            <label for="municipality"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Municipio</label>
                                            <input type="text" id="municipality" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-500 dark:bg-gray-600 dark:text-white"
                                                :value="loan?.customer?.addresses[0]?.municipality ?? 'No capturado'" />
                                        </div>
                                        <div>
                                            <label for="address_state"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Estado</label>
                                            <input type="text" id="address_state" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-500 dark:bg-gray-600 dark:text-white"
                                                :value="loan?.customer?.addresses[0]?.state ?? 'No capturado'" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="type"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Tipo
                                                Vivienda</label>
                                            <input type="text" name="type" id="type" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.addresses[0]?.type
                                                    " />
                                        </div>
                                        <div>
                                            <label for="Tiempo viviendo"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Tiempo
                                                Viviendo</label>
                                            <input type="text" name="Tiempo viviendo" id="Tiempo viviendo" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.addresses[0]?.years_living} años`" />
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                            @click="changeStep(2)">
                                            Siguiente
                                        </button>
                                    </div>
                                </form>
                                <form class="space-y-6" action="#" v-if="step == 2">
                                    <h5 class="text-xl font-medium text-gray-900 dark:text-white">
                                        Datos de trabajo
                                    </h5>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="type"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Tipo
                                                trabajo</label>
                                            <input type="text" name="type" id="type" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.employment?.type
                                                    " />
                                        </div>
                                        <div>
                                            <label for="area"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Área</label>
                                            <input type="text" name="area" id="area" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.area}`" />
                                        </div>
                                        <div>
                                            <label for="main_activity"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Giro</label>
                                            <input type="text" id="main_activity" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-500 dark:bg-gray-600 dark:text-white"
                                                :value="loan?.customer?.employment?.main_activity" />
                                        </div>
                                        <div>
                                            <label for="pld_activity"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Sector PLD declarado</label>
                                            <input type="text" id="pld_activity" readonly
                                                :class="['block w-full rounded-lg border p-2.5 text-sm',
                                                    loan?.customer?.employment?.pld_activity && loan.customer.employment.pld_activity !== 'Ninguna de las anteriores'
                                                        ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-gray-300 bg-gray-50 text-gray-900']"
                                                :value="loan?.customer?.employment?.pld_activity ?? 'No capturado'" />
                                        </div>
                                        <div>
                                            <label for="company"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Empresa /
                                                Negocio</label>
                                            <input type="text" name="company" id="company" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.company}`" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="job"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Puesto</label>
                                            <input type="text" name="job" id="job" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.employment?.job" />
                                        </div>
                                        <div>
                                            <label for="seniority_working"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Años
                                                laborando</label>
                                            <input type="text" name="seniority_working" id="seniority_working" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.seniority_working}`" />
                                        </div>
                                        <div>
                                            <label for="phone"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Teléfono
                                                laboral</label>
                                            <input type="text" name="phone" id="phone" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.phone}`" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="imss"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">¿Afiliado a
                                                IMSS/ISSSTE/OTRO?</label>
                                            <input type="text" name="imss" id="imss" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.employment?.imss ==
                                                        1
                                                        ? 'Si'
                                                        : 'No'
                                                    " />
                                        </div>
                                        <div>
                                            <label for="insurance_major_medical_expenses"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">¿Tiene
                                                seguro médico de gastos
                                                mayores?</label>
                                            <input type="text" name="insurance_major_medical_expenses"
                                                id="insurance_major_medical_expenses" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.insurance_major_medical_expenses == 1 ? 'Si' : 'No'}`" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="monthly_income"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Ingreso
                                                mensual</label>
                                            <input type="text" name="monthly_income" id="monthly_income" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.customer?.employment
                                                        ?.monthly_income
                                                    " />
                                        </div>
                                        <div>
                                            <label for="other_income"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Otros
                                                ingresos</label>
                                            <input type="text" name="other_income" id="other_income" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="`${loan?.customer?.employment?.other_income}`" />
                                        </div>
                                        <div>
                                            <label for="monthly_expenses"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Egresos
                                                mensuales</label>
                                            <input type="text" name="monthly_expenses" id="monthly_expenses" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.customer?.employment?.monthly_expenses ?? 'No capturado'" />
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                            @click="changeStep(3)">
                                            Siguiente
                                        </button>
                                    </div>
                                </form>
                                <form class="space-y-6" action="#" v-if="step == 3">
                                    <h5 class="text-xl font-medium text-gray-900 dark:text-white">
                                        Documentación
                                    </h5>
                                    <p v-if="missing.length" role="alert" class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                                        Faltan documentos obligatorios para aprobar: <strong>{{ missing.join(', ') }}</strong>.
                                    </p>
                                    <div v-if="loan.documentations.length == 0">
                                        No hay documentos guardados
                                    </div>
                                    <ul v-else class="list-disc space-y-1 pl-5">
                                        <li v-for="documentation in loan.documentations" :key="documentation.id">
                                            <a :href="route('loans.documents.show', [loan.id, documentation.id])" target="_blank" class="cursor-pointer text-blue-600 underline">
                                                {{ documentation.name }} ({{ documentation.type }})
                                            </a>
                                            <button
                                                v-if="!loan.approved_at"
                                                type="button"
                                                class="ml-3 text-sm text-red-600 hover:text-red-800 hover:underline"
                                                @click="deleteDocument(documentation)">
                                                Eliminar
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="space-y-4">
                                        <div class="rounded-lg border p-4">
                                            <h6 class="mb-4 text-lg font-medium text-gray-900">Documentos Requeridos</h6>
                                            <DocumentUpload
                                                v-model="documents"
                                                @update:model-value="handleDocumentsUpdate"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                            @click="updateDocuments()"
                                        >
                                            {{ documents.length == 0 ? 'Continuar' : 'Guardar y continuar' }}
                                        </button>
                                    </div>
                                </form>
                                <form class="space-y-6" action="#" v-if="step == 4">
                                    <h5 class="text-xl font-medium text-gray-900 dark:text-white">
                                        Préstamo
                                    </h5>
                                    <div v-if="loan?.type === 'FUNERARIO'" class="flex items-end justify-start gap-4 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-900">Folio del Plan Funerario (garantía)</label>
                                            <input type="text" readonly :value="loan?.policy_folio"
                                                class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                        </div>
                                        <button type="button" @click="viewContract"
                                            class="rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300">
                                            Ver contrato
                                        </button>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="folio"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Folio</label>
                                            <input type="text" name="folio" id="folio" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="folio" :value="loan?.folio" />
                                        </div>
                                        <div>
                                            <label for="amount"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Monto</label>
                                            <input type="text" name="amount" id="amount" placeholder="999"
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.amount" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="type"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Meses de
                                                Pago</label>
                                            <input type="text" name="term" id="term" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="XXXX9999XXX" :value="loan?.term" />
                                        </div>
                                        <div>
                                            <label for="start_at"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Inicio de
                                                Pagos</label>
                                            <input type="text" name="start_at" id="start_at" placeholder=""
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.start_at" />
                                        </div>
                                    </div>
                                    <div class="flex justify-start gap-4">
                                        <div>
                                            <label for="purpose"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Proposito</label>
                                            <input type="text" name="purpose" id="purpose" readonly
                                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                placeholder="name@company.com" :value="loan?.purpose" />
                                        </div>
                                        <div class="flex-grow">
                                            <label for="purpose_description"
                                                class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">Descripción</label>
                                            <textarea type="text" name="purpose_description" id="purpose_description"
                                                placeholder=""
                                                class="block w-full flex-grow rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-600 dark:text-white dark:placeholder-gray-400"
                                                :value="loan?.purpose_description"></textarea>
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                                        <h6 class="text-base font-semibold text-gray-900">Validación: Buró de Crédito y PLD</h6>
                                        <dl v-if="loan.validated_at" class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                                            <div><dt class="inline text-gray-500">Folio de Buró:</dt> <dd class="inline font-medium">{{ loan.bureau_folio }}</dd></div>
                                            <div><dt class="inline text-gray-500">Resultado:</dt> <dd class="inline font-medium">{{ loan.bureau_result }}</dd></div>
                                            <div>
                                                <dt class="inline text-gray-500">Cotejo PLD:</dt>
                                                <dd :class="['inline font-medium', loan.pld_result === 'sin_coincidencias' ? 'text-green-700' : 'text-red-700']">{{ pldResults[loan.pld_result] }}</dd>
                                                <a v-if="pldEvidence" :href="route('loans.documents.show', [loan.id, pldEvidence.id])" target="_blank" class="ml-2 text-blue-600 underline">comprobante</a>
                                            </div>
                                            <div><dt class="inline text-gray-500">Validó:</dt> <dd class="inline font-medium">{{ validator?.name ?? '—' }} · {{ new Date(loan.validated_at).toLocaleString('es-MX') }}</dd></div>
                                        </dl>
                                        <p v-else class="mt-1 text-sm text-gray-600">
                                            Un analista de crédito debe capturar el folio y resultado de Buró y el cotejo contra listas (con comprobante) antes de que se pueda aprobar.
                                        </p>

                                        <form v-if="canEditValidation" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitValidation">
                                            <div>
                                                <label for="bureau-folio" class="mb-1 block text-sm font-medium text-gray-900">Folio de la consulta a Buró</label>
                                                <input id="bureau-folio" v-model="validation.bureau_folio" type="text" maxlength="60" class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                                <p v-if="validationErrors.bureau_folio" class="mt-1 text-sm text-red-600">{{ validationErrors.bureau_folio }}</p>
                                            </div>
                                            <div>
                                                <label for="bureau-result" class="mb-1 block text-sm font-medium text-gray-900">Resultado de Buró</label>
                                                <input id="bureau-result" v-model="validation.bureau_result" type="text" maxlength="255" placeholder="Ej. score 680, sin atrasos" class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                                <p v-if="validationErrors.bureau_result" class="mt-1 text-sm text-red-600">{{ validationErrors.bureau_result }}</p>
                                            </div>
                                            <div>
                                                <label for="pld-result" class="mb-1 block text-sm font-medium text-gray-900">Cotejo contra listas (PLD)</label>
                                                <select id="pld-result" v-model="validation.pld_result" class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900">
                                                    <option v-for="(label, value) in pldResults" :key="value" :value="value">{{ label }}</option>
                                                </select>
                                                <p v-if="validationErrors.pld_result" class="mt-1 text-sm text-red-600">{{ validationErrors.pld_result }}</p>
                                            </div>
                                            <div>
                                                <label for="pld-evidence" class="mb-1 block text-sm font-medium text-gray-900">Comprobante del cotejo (PDF o imagen)</label>
                                                <input id="pld-evidence" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm" @change="validation.pld_evidence = $event.target.files[0] ?? null" />
                                                <p v-if="validationErrors.pld_evidence" class="mt-1 text-sm text-red-600">{{ validationErrors.pld_evidence }}</p>
                                            </div>
                                            <div class="md:col-span-2 flex justify-end">
                                                <button type="submit" :disabled="validating" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800 disabled:opacity-60">
                                                    {{ loan.validated_at ? 'Actualizar validación' : 'Marcar como validada' }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <div v-if="!processed" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <h6 class="text-base font-semibold text-gray-900">Condiciones a aprobar</h6>
                                        <p class="text-sm text-gray-600">Puedes aprobar un monto o un plazo distinto al solicitado; la calculadora muestra el efecto antes de decidir.</p>

                                        <div class="mt-3 flex flex-wrap gap-4">
                                            <div>
                                                <label for="approve-amount" class="mb-1 block text-sm font-medium text-gray-900">Monto a aprobar</label>
                                                <input id="approve-amount" v-model.number="approval.amount" type="number" min="1" step="100"
                                                    class="block w-40 rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                            </div>
                                            <div>
                                                <label for="approve-term" class="mb-1 block text-sm font-medium text-gray-900">Plazo (meses)</label>
                                                <input id="approve-term" v-model.number="approval.term" type="number" min="1" step="1"
                                                    class="block w-32 rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                            </div>
                                        </div>

                                        <p v-if="changedConditions" class="mt-2 text-sm text-orange-700">
                                            Distinto a lo solicitado ({{ money(loan.amount) }} a {{ loan.term }} meses).
                                        </p>
                                        <p v-if="previewError" class="mt-2 text-sm text-red-600">{{ previewError }}</p>

                                        <dl v-if="preview" class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                                            <div class="rounded-lg bg-white p-3">
                                                <dt class="text-xs uppercase text-gray-500">Primer pago</dt>
                                                <dd class="font-bold text-gray-900">{{ money(preview.first_payment) }}</dd>
                                            </div>
                                            <div class="rounded-lg bg-white p-3">
                                                <dt class="text-xs uppercase text-gray-500">Total a pagar</dt>
                                                <dd class="font-bold text-gray-900">{{ money(preview.total_to_pay) }}</dd>
                                            </div>
                                            <div class="rounded-lg bg-white p-3">
                                                <dt class="text-xs uppercase text-gray-500">Tasa anual</dt>
                                                <dd class="font-bold text-gray-900">{{ preview.annual_rate }}%</dd>
                                            </div>
                                            <div class="rounded-lg bg-white p-3">
                                                <dt class="text-xs uppercase text-gray-500">CAT sin IVA</dt>
                                                <dd class="font-bold text-gray-900">{{ preview.cat_sin_iva }}%</dd>
                                            </div>
                                            <div class="rounded-lg bg-white p-3">
                                                <dt class="text-xs uppercase text-gray-500">CAT con IVA</dt>
                                                <dd class="font-bold text-gray-900">{{ preview.cat_con_iva }}%</dd>
                                            </div>
                                        </dl>

                                        <div v-if="capacity" :class="['mt-4 rounded-lg border p-3 text-sm', capacityStyle[capacity.level]]">
                                            <p class="font-semibold">Capacidad de pago</p>
                                            <p class="mt-1">
                                                Ingresos {{ money(capacity.income) }}
                                                <template v-if="capacity.expenses !== null"> · egresos {{ money(capacity.expenses) }} · disponible {{ money(capacity.available) }}</template>
                                                <template v-if="capacity.ratio !== null"> · la cuota es el <strong>{{ Math.round(capacity.ratio * 100) }}%</strong> del ingreso {{ capacity.withoutExpenses ? '' : 'disponible' }}</template>
                                            </p>
                                            <p class="mt-1">{{ capacity.text }}</p>
                                            <p v-if="capacity.withoutExpenses" class="mt-1 text-xs">Este expediente no tiene egresos capturados; el cálculo usa solo los ingresos.</p>
                                        </div>
                                    </div>

                                    <p v-if="!processed && can.approve && !loan.validated_at" class="text-sm text-gray-600">
                                        Para aprobar, primero la debe validar un analista de crédito.
                                    </p>
                                    <p v-else-if="!processed && loan.validated_at && !can.approve && validator?.id === $page.props.auth.user.id" class="text-sm text-gray-600">
                                        Tú validaste esta solicitud: la debe aprobar otra persona (Gerente o Administrador).
                                    </p>
                                    <p v-if="!processed && loan.validated_at && missing.length" class="text-sm text-yellow-800">
                                        Faltan documentos obligatorios: {{ missing.join(', ') }}.
                                    </p>
                                    <div v-if="!loan.approved_at && !loan.reject_at && (can.reject || can.approve)" class="flex justify-between">
                                        <button type="button"
                                            class="rounded-lg bg-yellow-500 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-yellow-600 focus:outline-none focus:ring-4 focus:ring-yellow-300 disabled:bg-orange-300 disabled:opacity-75 dark:bg-yellow-300 dark:hover:bg-yellow-400 dark:focus:ring-yellow-800"
                                            @click="rejectLoan" :disabled="loan.approved_at || loan.reject_at || !can.reject
                                                ">
                                            Rechazar
                                        </button>
                                        <button type="button"
                                            class="dark:bg-green-6400 rounded-lg bg-green-500 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-green-600 focus:outline-none focus:ring-4 focus:ring-green-300 disabled:bg-green-300 disabled:opacity-75 dark:hover:bg-green-500 dark:focus:ring-green-800"
                                            @click="validateLoan" :disabled="loan.approved_at || loan.reject_at || !can.approve || !loan.validated_at || loan.pld_result !== 'sin_coincidencias' || missing.length > 0
                                                ">
                                            Aceptar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="planOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Contrato del Plan Funerario</DialogTitle>
                    <DialogDescription>Folio {{ loan?.policy_folio }} · consultado en MOREH</DialogDescription>
                </DialogHeader>
                <p v-if="planLoading" class="text-sm text-gray-600">Consultando contrato…</p>
                <p v-else-if="planError" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ planError }}</p>
                <dl v-else-if="plan" class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-gray-500">Titular</dt><dd class="font-medium">{{ plan.titular }}</dd>
                    <dt class="text-gray-500">Contrato</dt><dd>{{ plan.folio }}</dd>
                    <dt class="text-gray-500">Servicio</dt><dd>{{ plan.tipoServicio || '—' }}</dd>
                    <dt class="text-gray-500">Fecha de solicitud</dt><dd>{{ day(plan.fechaSolicitud) }}</dd>
                    <dt class="text-gray-500">Fecha de emisión</dt><dd>{{ day(plan.fechaEmision) }}</dd>
                    <dt class="text-gray-500">Precio del plan</dt><dd>{{ money(plan.costo) }}</dd>
                    <dt class="text-gray-500">Pagos</dt><dd>{{ plan.pagosRealizados ?? '—' }} de {{ plan.totalPagos ?? '—' }} ({{ money(plan.importePago) }} c/u)</dd>
                    <dt class="text-gray-500">Pagado</dt><dd>{{ money(plan.pagado) }}</dd>
                    <dt class="text-gray-500">Saldo pendiente</dt><dd>{{ money(plan.saldoPendiente) }}</dd>
                    <dt class="text-gray-500">Estatus</dt>
                    <dd>
                        <span v-if="plan.cancelado" class="font-semibold text-red-600">Cancelado</span>
                        <span v-else-if="plan.liquidado" class="font-semibold text-green-600">Liquidado</span>
                        <span v-else class="font-semibold text-yellow-600">Con saldo pendiente</span>
                    </dd>
                </dl>
                <p v-if="plan && !plan.liquidado" class="text-xs text-gray-500">
                    El contrato de crédito exige que el Plan Funerario esté totalmente liquidado.
                </p>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>
