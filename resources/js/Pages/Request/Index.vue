<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { currency } from '@/lib/formaters';
import { ref, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
    loans: Object,
});

const loans = ref(props.loans);
const search = ref('');

const searchLoans = debounce(() => {
    axios.post(route('requests.search'), { search: search.value })
        .then(response => {
            loans.value = response.data;
        });
}, 300);

watch(search, searchLoans);
</script>

<template>
    <Head title="Solicitudes" />

    <DashboardLayout title="Solicitudes no revisadas">
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Solicitudes no revisadas</CardTitle>
                        <CardDescription>Listado de todas las solicitudes de préstamos no revisadas</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div class="flex gap-2 mb-4">
                            <Input type="text" v-model="search" placeholder="Buscar por nombre de cliente, RFC, CURP, folio de poliza, etc." />
                            <Button>Buscar</Button>
                        </div>
                        <Table>
                            <TableCaption>Historial de Préstamos</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[200px]">
                                        Cliente
                                    </TableHead>
                                    <TableHead>Puntuación</TableHead>
                                    <TableHead>Monto</TableHead>
                                    <TableHead class="text-right">
                                        Meses
                                    </TableHead>
                                    <TableHead>Proposito</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in loans.data" :key="loan.id">
                                    <TableCell class="font-medium">
                                        <a :href="route('customers.show', loan.customer.id)">
                                            {{ loan.customer.full_name }}
                                        </a>
                                    </TableCell>
                                    <TableCell>{{ loan.score }}</TableCell>
                                    <TableCell>$ {{ currency(loan.amount) }}</TableCell>
                                    <TableCell class="text-right">
                                    {{ loan.term }}
                                    </TableCell>
                                    <TableCell>{{ loan.purpose }}</TableCell>
                                    <TableCell>{{ loan.status }}</TableCell>
                                    <TableCell>
                                        <a :href="route('loans.show', loan.id)">
                                            <Button variant="link" size="sm">Ver solicitud</Button>
                                        </a>
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