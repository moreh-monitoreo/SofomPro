<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head, Link } from '@inertiajs/vue3';
import { defineProps, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { Card, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';

const toast = useToast();
const props = defineProps({
    loans: Object,
    message: String,
});

const loans = ref(props.loans);
if (props.message) {
    toast.success(props.message);
}
</script>

<template>
    <Head title="Profile" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Solicitudes Recibidas</CardTitle>
                        <CardDescription>Listado de todas las solicitudes de préstamos pendientes</CardDescription>
                    </CardHeader>
                    <div class="mb-6 items-center justify-between lg:flex">
                        <Button title="Solicitudes registradas"></Button>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table
                            class="w-full text-left text-sm text-gray-500 rtl:text-right dark:text-gray-400"
                        >
                            <thead
                                class="bg-gray-50 uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400"
                            >
                                <tr>
                                    <th scope="col" class="px-6 py-4">Nombre</th>
                                    <th scope="col" class="px-6 py-4">
                                        Puntuación
                                    </th>
                                    <th scope="col" class="px-6 py-4">Monto</th>
                                    <th scope="col" class="px-6 py-4">Meses</th>
                                    <th scope="col" class="px-6 py-4">Proposito</th>
                                    <th scope="col" class="px-6 py-4">Estado</th>
                                    <th scope="col" class="px-6 py-4">Pagos</th>
                                    <th scope="col" class="px-6 py-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="loan in loans.data"
                                    :key="`user-${loan.id}`"
                                    class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600"
                                >
                                    <th
                                        scope="row"
                                        class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-white"
                                    >
                                        <a :href="route('customers.show', loan.customer.id)">
                                            {{ loan.customer.full_name }}
                                        </a>
                                    </th>
                                    <td class="px-6 py-4">{{ loan.score }}</td>
                                    <td class="px-6 py-4">
                                        {{ currency(loan.amount) }}
                                    </td>
                                    <td class="px-6 py-4">{{ loan.term }}</td>
                                    <td class="px-6 py-4">{{ loan.purpose }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="me-2 rounded bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900 dark:text-green-300"
                                            v-if="loan.paid_at"
                                            >Pagado</span
                                        >
                                        <span
                                            class="me-2 rounded bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300"
                                            v-if="
                                                loan.reject_at != null &&
                                                loan.paid_at == null &&
                                                loan.approved_at == null
                                            "
                                            >Rechazado</span
                                        >
                                        <span
                                            class="me-2 rounded bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300"
                                            v-if="
                                                !loan.paid_at &&
                                                !loan.approved_at &&
                                                !loan.reject_at
                                            "
                                            >Pendiente</span
                                        >
                                        <span
                                            class="me-2 rounded bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300"
                                            v-if="!loan.paid_at && loan.approved_at"
                                            >Aceptado</span
                                        >
                                    </td>
                                    <td class="px-6 py-4">
                                        <Link
                                            class="font-medium text-blue-600 hover:underline dark:text-blue-500"
                                            :href="
                                                route(
                                                    'loans.payments.index',
                                                    loan.id,
                                                )
                                            "
                                            v-if="loan.approved_at && loan.payment_count == 0"
                                            >Generar plan</Link
                                        >
                                        <Link
                                            v-if="loan.payment_count > 0"
                                            class="font-medium text-blue-600 hover:underline dark:text-blue-500"
                                            :href="
                                                route(
                                                    'loans.payments.index',
                                                    loan.id,
                                                )
                                            "
                                            >Ver plan</Link
                                        >
                                    </td>
                                    <td class="px-6 py-4 text-right flex gap-2">
                                        <Link
                                            class="font-medium text-yellow-600 hover:underline dark:text-yellow-500"
                                            :href="route('loans.show', loan.id)"
                                            >Revisar</Link
                                        >
                                    </td>
                                </tr>
                                <tr
                                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-600"
                                    v-if="loans.data?.length <= 0"
                                >
                                    <th
                                        scope="row"
                                        class="whitespace-nowrap px-6 py-4 text-center font-medium text-gray-900 dark:text-white"
                                        colspan="6"
                                    >
                                        No hay registros
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
