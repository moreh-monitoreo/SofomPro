<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Checkout from '@/Components/stripe/Checkout.vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { currency } from '@/lib/formaters';
import { CircleCheckBig } from 'lucide-vue-next';

const props = defineProps({
    customer: Object,
    loan: Object,
});

const total_paid = computed(() => {
    return props.loan.payment.details.filter(payment => payment.status === 'S').reduce((acc, payment) => acc + parseFloat(payment.monthly_payment), 0);
});

const next_payment = computed(() => {
    return props.loan.payment.details.find(payment => payment.status === 'N');
});
</script>
<template>
    <Head title="Pagos - Préstamo" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div
                class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min"
            >
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl"
                            >Tabla de pagos</CardTitle
                        >
                        <CardDescription>Información de préstamo</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="flex justify-between gap-4">
                            <div>
                                <p>Cliente: {{ customer.name }} {{ customer.second_name }} {{ customer.last_name }} {{ customer.maiden_name }}</p>
                                <p>Fecha de inicio: {{ loan.start_at }}</p>
                                <p>Fecha de pago: {{ loan.end_at }}</p>
                            </div>
                            <div class="w-1/2 flex flex-wrap">
                                <p class="w-1/2">Plazo:</p>
                                <p class="w-1/2 text-right">{{ loan.term }}</p>
                                <p class="w-1/2">Préstamo:</p>
                                <p class="w-1/2 text-right">$ {{ currency(loan.amount) }}</p>
                                <p class="w-1/2">Total pagado:</p>
                                <p class="w-1/2 text-right font-bold">$ {{ currency(total_paid) }}</p>
                                <p class="w-1/2">Total pendiente:</p>
                                <p class="w-1/2 text-right font-bold">$ {{ currency(loan.amount - total_paid) }}</p>
                            </div>
                        </div>
                        <Table class="mt-5">
                            <TableCaption
                                >Lista de pagos del
                                préstamo</TableCaption
                            >
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        >Número Pago</TableHead
                                    >
                                    <TableHead class="">
                                        Mes
                                    </TableHead>
                                    <TableHead
                                        >Estado</TableHead
                                    >
                                    <TableHead
                                        >Fecha Pago</TableHead
                                    >
                                    <TableHead
                                        class="text-right"
                                    >
                                        Pagar
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="payment in loan
                                        .payment.details"
                                    :key="`payment-${payment.id}`"
                                    :class="payment == next_payment ? 'bg-red-100 hover:bg-red-200' : ''"
                                >
                                    <TableCell>{{
                                        payment.number
                                    }}</TableCell>
                                    <TableCell
                                        class="font-medium"
                                    >
                                        {{ payment.month }}/{{
                                            payment.year
                                        }}
                                    </TableCell>
                                    <TableCell>{{
                                        payment.status == 'N'
                                            ? 'No pagado'
                                            : 'Pagado'
                                    }}</TableCell>
                                    <TableCell>{{
                                        payment.payed_at
                                    }}</TableCell>
                                    <TableCell
                                        class="text-right"
                                    >
                                        <div
                                            class="flex justify-end gap-2"
                                            v-if="
                                                payment.status !=
                                                'N'
                                            "
                                        >
                                            <CircleCheckBig
                                                class="text-gray-500"
                                                :size="20"
                                            />
                                            ${{
                                                currency(
                                                    payment.monthly_payment,
                                                )
                                            }}
                                        </div>
                                        <Checkout
                                            v-else
                                            :payload="{
                                                payment_id:
                                                    payment.id,
                                            }"
                                            >${{
                                                currency(
                                                    payment.monthly_payment,
                                                )
                                            }}</Checkout
                                        >
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>