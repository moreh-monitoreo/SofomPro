<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Head, Link, useForm } from '@inertiajs/vue3';
import { currency } from '@/lib/formaters';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { ref } from 'vue';

const props = defineProps({
    loans: Array,
});

const loans = ref(props.loans);

</script>

<template>
    <Head title="Profile" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Historial de Préstamos</CardTitle>
                        <CardDescription>Listado de todos los préstamos que han sido pagados</CardDescription>
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