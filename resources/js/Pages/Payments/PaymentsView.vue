<script setup>
import FormCreatePayment from '@/Components/payments/FormCreatePayment.vue';
import PaymentsTable from '@/Components/payments/PaymentsTable.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loan: Object,
});
const toast = useToast();
const show = ref(false);
const tab = ref(0);
const loan = ref(props.loan || {});
const paymentTable = ref(props.loan.payment || {});

const printQuotation = async () => {
    const pdf = await agentPaymentsStore.generatePdfPayment();
};

const createPayment = async () => {
    await agentPaymentsStore.createPayment();
    toast.success('Tabla de pagos creada correctamente');
};

async function sendemail() {
    const result = await Swal.fire({
        title: '¿Deseas enviar la tabla de pagos por correo?',
        text: 'Se enviará el archivo pdf a la cuenta de correo registrada del cliente',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#F39C12',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si! Enviar',
    });
    if (result.isConfirmed) {
        toast.success('Tabla de pagos enviada correctamente');
    }
}
function handlePaymentsCreated(data) {
    paymentTable.value = data;
    console.log(paymentTable.value);
}
</script>

<template>
    <Head title="Profile" />

    <DashboardLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Pagos
            </h2>
        </template>
        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="mb-6 items-center justify-between lg:flex">
                    <div class="flex gap-4" v-if="tab == 1">
                        <button
                            type="button"
                            class="rounded-lg bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                            @click="createPayment"
                        >
                            Generar Préstamo
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                            @click="printQuotation"
                        >
                            Imprimir
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                            @click="sendemail"
                        >
                            Enviar
                        </button>
                    </div>
                </div>

                <div
                    class="relative flex gap-4 overflow-x-auto shadow-md sm:rounded-lg"
                >
                    <div
                        class="flex-1 rounded-lg border border-gray-200 bg-white p-4 shadow sm:p-6 md:p-8 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h5
                            class="text-xl font-medium text-gray-900 dark:text-white"
                        >
                            {{ title }}
                        </h5>
                        <FormCreatePayment :loan="loan" @paymentsCreated="handlePaymentsCreated"></FormCreatePayment>
                        <PaymentsTable :paymentTable="paymentTable"></PaymentsTable>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
