<script setup>
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { ref, onMounted } from 'vue';
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement } from 'chart.js';
import { Pie, Bar } from 'vue-chartjs';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement);

const props = defineProps({
    statistics: Object
});

const pieChartData = {
    labels: ['Préstamos Pagados', 'Préstamos Activos'],
    datasets: [
        {
            backgroundColor: ['#10B981', '#3B82F6'],
            data: [props.statistics.paidLoans, props.statistics.activeLoans]
        }
    ]
};

const barChartData = {
    labels: ['Total', 'Pagados', 'Activos'],
    datasets: [
        {
            label: 'Cantidad de Préstamos',
            backgroundColor: ['#6B7280', '#10B981', '#3B82F6'],
            data: [
                props.statistics.loansCount.total,
                props.statistics.loansCount.paid,
                props.statistics.loansCount.active
            ]
        }
    ]
};

const progressChartData = {
    labels: props.statistics.paymentProgress.map(loan => loan.customer),
    datasets: [
        {
            label: 'Progreso de Pago (%)',
            backgroundColor: '#3B82F6',
            data: props.statistics.paymentProgress.map(loan => loan.progress)
        }
    ]
};

const progressChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    const loan = props.statistics.paymentProgress[context.dataIndex];
                    return [
                        `Progreso: ${loan.progress}%`,
                        `Pagado: $${currency(loan.totalPaid)}`,
                        `Total a Pagar: $${currency(loan.totalToPay)}`
                    ];
                }
            }
        }
    },
    scales: {
        x: {
            beginAtZero: true,
            max: 100,
            title: {
                display: true,
                text: 'Porcentaje de Pago'
            }
        }
    }
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false
};
</script>

<template>
    <Head title="Estadísticas" />

    <DashboardLayout title="Estadísticas">
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Estadísticas de Préstamos</CardTitle>
                        <CardDescription>Resumen de préstamos y pagos</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-8">
                            <Card>
                                <CardContent class="p-6">
                                    <div class="text-2xl font-bold text-gray-900">
                                        $ {{ currency(statistics.totalLoans) }}
                                    </div>
                                    <p class="text-sm text-gray-500">Total en Préstamos</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent class="p-6">
                                    <div class="text-2xl font-bold text-green-600">
                                        $ {{ currency(statistics.paidLoans) }}
                                    </div>
                                    <p class="text-sm text-gray-500">Préstamos Pagados</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent class="p-6">
                                    <div class="text-2xl font-bold text-blue-600">
                                        $ {{ currency(statistics.activeLoans) }}
                                    </div>
                                    <p class="text-sm text-gray-500">Préstamos Activos</p>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Distribución de Préstamos</CardTitle>
                                    <CardDescription>Monto total por estado</CardDescription>
                                </CardHeader>
                                <CardContent class="h-[300px]">
                                    <Pie :data="pieChartData" :options="chartOptions" />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Cantidad de Préstamos</CardTitle>
                                    <CardDescription>Número de préstamos por estado</CardDescription>
                                </CardHeader>
                                <CardContent class="h-[300px]">
                                    <Bar :data="barChartData" :options="chartOptions" />
                                </CardContent>
                            </Card>
                        </div>

                        <div class="mt-8">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Progreso de Pago de Préstamos Activos</CardTitle>
                                    <CardDescription>Porcentaje de pago por cliente</CardDescription>
                                </CardHeader>
                                <CardContent class="h-[400px]">
                                    <Bar :data="progressChartData" :options="progressChartOptions" />
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>