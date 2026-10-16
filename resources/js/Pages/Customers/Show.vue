<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { Progress } from "@/Components/ui/progress";
import { computed } from 'vue';

const props = defineProps({
    customer: Object,
    loans: Array,
});

// Calculate credit score percentage (assuming score is between 0-100)
const creditScorePercentage = computed(() => {
    return (props.customer?.score || 0) * 100;
});

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
};

// Format date
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-MX');
};
</script>

<template>
    <Head title="Cliente" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <!-- Customer Information Card -->
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Información del Cliente</CardTitle>
                        <CardDescription>Datos generales del cliente</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <h3 class="font-semibold">Nombre Completo</h3>
                                <p>{{ customer.full_name }}</p>
                            </div>
                            <div class="space-y-2">
                                <h3 class="font-semibold">RFC</h3>
                                <p>{{ customer.rfc }}</p>
                            </div>
                            <div class="space-y-2">
                                <h3 class="font-semibold">CURP</h3>
                                <p>{{ customer.curp }}</p>
                            </div>
                            <div class="space-y-2">
                                <h3 class="font-semibold">Fecha de Nacimiento</h3>
                                <p>{{ formatDate(customer.birthday) }}</p>
                            </div>
                            <div class="space-y-2">
                                <h3 class="font-semibold">Estado Civil</h3>
                                <p>{{ customer.civil_status }}</p>
                            </div>
                            <div class="space-y-2">
                                <h3 class="font-semibold">Nivel de Estudios</h3>
                                <p>{{ customer.level_study }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Credit Score Card -->
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Calificación Crediticia</CardTitle>
                        <CardDescription>Evaluación del comportamiento crediticio</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Score Crediticio</span>
                                <span class="text-sm font-medium">{{ creditScorePercentage }}%</span>
                            </div>
                            <Progress :value="creditScorePercentage" class="h-2" />
                            <div class="text-sm text-muted-foreground">
                                {{ creditScorePercentage >= 70 ? 'Excelente pagador' : 
                                   creditScorePercentage >= 50 ? 'Buen pagador' : 
                                   creditScorePercentage >= 30 ? 'Pagador regular' : 'Pagador deficiente' }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Loans Table Card -->
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Historial de Préstamos</CardTitle>
                        <CardDescription>Listado de todos los préstamos realizados</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Folio</TableHead>
                                    <TableHead>Monto</TableHead>
                                    <TableHead>Plazo</TableHead>
                                    <TableHead>Propósito</TableHead>
                                    <TableHead>Fecha de Aprobación</TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in loans" :key="loan.id">
                                    <TableCell>{{ loan.folio || 'N/A' }}</TableCell>
                                    <TableCell>{{ formatCurrency(loan.amount) }}</TableCell>
                                    <TableCell>{{ loan.term }} meses</TableCell>
                                    <TableCell>{{ loan.purpose }}</TableCell>
                                    <TableCell>{{ formatDate(loan.approved_at) }}</TableCell>
                                    <TableCell>
                                        <span :class="{
                                            'px-2 py-1 rounded-full text-xs': true,
                                            'bg-green-100 text-green-800': loan.status === 'Pagado',
                                            'bg-yellow-100 text-yellow-800': loan.status === 'En Proceso',
                                            'bg-red-100 text-red-800': loan.status === 'Rechazado'
                                        }">
                                            {{ loan.status }}
                                        </span>
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