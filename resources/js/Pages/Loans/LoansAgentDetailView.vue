<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { useAgentLoanStore } from '@/stores/loans.store';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DocumentUpload from '@/Components/ui/document-upload.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

const toast = useToast();

const loanStore = useAgentLoanStore();
const props = defineProps({
    loan: Object,
    account: { type: Object, default: null },
});
const step = ref(0);
const loan = ref(props.loan);
const documents = ref([]);

const account = ref(props.account);
const showPassword = ref(false);
const accountLoading = ref(false);

// Genera una contraseña nueva para la cuenta del cliente (por si no existe o se perdió)
async function resetAccountPassword() {
    const result = await Swal.fire({
        title: '¿Generar nueva contraseña?',
        text: 'La contraseña actual del cliente dejará de funcionar.',
        icon: 'warning',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, generar',
    });
    if (!result.isConfirmed) return;
    accountLoading.value = true;
    try {
        const response = await axios.post(route('loans.account.password', loan.value.id));
        account.value = response.data.account;
        showPassword.value = true;
        toast.success(response.data.message);
    } catch (error) {
        toast.error(error.response?.data?.message || 'No se pudo generar la contraseña');
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

const handleDocumentsUpdate = (newDocuments) => {
    documents.value = newDocuments;
};

async function rejectLoan() {
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: 'La solicitud será rechazada y se avisará al solicitante',
        icon: 'warning',
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
        text: 'La solicitud será aceptada y se avisará al solicitante',
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
            });
            toast.success(response.data.message);
            router.visit(route('loans.index'));
        } catch (error) {
            toast.error(error);
        }
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
                                    Prestamo
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
                                    <div v-if="loan?.approved_at" class="rounded-lg border border-green-200 bg-green-50 p-3">
                                        <p class="mb-2 text-sm font-semibold text-gray-900">Cuenta del cliente</p>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-900">Correo (usuario)</label>
                                                <input type="text" readonly :value="account?.email || loan?.customer?.email"
                                                    class="block w-64 rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-900">Contraseña</label>
                                                <div class="flex gap-2">
                                                    <input :type="showPassword ? 'text' : 'password'" readonly
                                                        :value="account?.password || ''" :placeholder="account?.password ? '' : 'No disponible'"
                                                        autocomplete="off"
                                                        class="block w-48 rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900" />
                                                    <button type="button" :disabled="!account?.password" @click="showPassword = !showPassword"
                                                        class="rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50">
                                                        {{ showPassword ? 'Ocultar' : 'Mostrar' }}
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" :disabled="accountLoading" @click="resetAccountPassword"
                                                class="rounded-lg bg-gray-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60">
                                                {{ account?.password ? 'Generar nueva' : 'Generar contraseña' }}
                                            </button>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-600">La cuenta no se envía al cliente; entrégale estos datos por el medio que definas.</p>
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
                                    <div v-if="loan.documentations.length == 0">
                                        No hay documentos guardados
                                    </div>
                                    <div v-else>
                                        <a :href="route('loans.documents.show', [loan.id, documentation.id])" target="_blank" v-for="documentation in loan.documentations" :key="documentation.id" class="cursor-pointer underline text-blue-600">
                                            <li class="list-disc">{{ documentation.name }} ({{ documentation.type }})</li>
                                        </a>
                                    </div>
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
                                        Prestamo
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
                                    <div class="flex justify-between">
                                        <button type="button"
                                            class="rounded-lg bg-yellow-500 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-yellow-600 focus:outline-none focus:ring-4 focus:ring-yellow-300 disabled:bg-orange-300 disabled:opacity-75 dark:bg-yellow-300 dark:hover:bg-yellow-400 dark:focus:ring-yellow-800"
                                            @click="rejectLoan" :disabled="loan.approved_at || loan.reject_at
                                                ">
                                            Rechazar
                                        </button>
                                        <button type="button"
                                            class="dark:bg-green-6400 rounded-lg bg-green-500 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-green-600 focus:outline-none focus:ring-4 focus:ring-green-300 disabled:bg-green-300 disabled:opacity-75 dark:hover:bg-green-500 dark:focus:ring-green-800"
                                            @click="validateLoan" :disabled="loan.approved_at || loan.reject_at
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
