<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head } from '@inertiajs/vue3';

defineProps({
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    graceDays: { type: Number, default: 0 },
});

const severity = (days) => (days > 30 ? 'bg-red-600 text-white' : days > 7 ? 'bg-orange-500 text-white' : 'bg-yellow-200 text-yellow-900');
</script>

<template>
    <Head title="Cartera vencida" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <Card class="mt-5">
                <CardHeader class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle class="text-2xl">Cartera vencida</CardTitle>
                            <CardDescription>
                                Clientes con cuotas pasadas de su fecha límite sin pagar<span v-if="graceDays > 0"> (con {{ graceDays }} días de gracia)</span>.
                            </CardDescription>
                        </div>
                        <a
                            :href="route('portfolio.overdue.export')"
                            class="rounded-lg border border-vermilion-500 px-4 py-2 text-sm font-medium text-vermilion-600 hover:bg-vermilion-50"
                        >
                            Descargar CSV
                        </a>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 p-4 pt-0">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Clientes en mora</dt>
                            <dd class="text-2xl font-bold text-red-700">{{ totals.clients }}</dd>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Cuotas vencidas</dt>
                            <dd class="text-2xl font-bold text-red-700">{{ totals.installments }}</dd>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4">
                            <dt class="text-sm text-gray-600">Monto vencido</dt>
                            <dd class="text-2xl font-bold text-red-700">$ {{ currency(totals.amount) }}</dd>
                        </div>
                    </dl>

                    <div class="relative overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 uppercase text-gray-700">
                                <tr>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Contacto</th>
                                    <th class="px-4 py-3">Cuotas vencidas</th>
                                    <th class="px-4 py-3">Monto vencido</th>
                                    <th class="px-4 py-3">Atraso</th>
                                    <th class="px-4 py-3">Desde</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-green-700">No hay clientes en mora. ¡Cartera al corriente!</td>
                                </tr>
                                <tr v-for="row in rows" :key="row.loan_id" class="border-t bg-white hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a :href="route('customers.show', row.customer_id)" class="hover:underline">{{ row.customer }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a v-if="row.phone" :href="`tel:${row.phone}`" class="block text-blue-600 hover:underline">{{ row.phone }}</a>
                                        <a v-if="row.email" :href="`mailto:${row.email}`" class="block text-xs text-blue-600 hover:underline">{{ row.email }}</a>
                                    </td>
                                    <td class="px-4 py-3">{{ row.overdue_installments }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">$ {{ currency(row.overdue_amount) }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="['rounded px-2.5 py-0.5 text-xs font-semibold', severity(row.days_overdue)]">{{ row.days_overdue }} días</span>
                                    </td>
                                    <td class="px-4 py-3">{{ row.oldest_due_date }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a :href="route('loans.payments.index', row.loan_id)" class="font-medium text-vermilion-600 hover:underline">Registrar pago</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
