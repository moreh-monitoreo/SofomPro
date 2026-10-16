<script setup lang="ts">

import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { cn } from '@/lib/utils';
import { Loan } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CircleCheckBig } from 'lucide-vue-next';
import { defineProps } from 'vue';

const props = defineProps({
    loans: [],
    customer: Object,
});

const loans = (props.loans as unknown as Loan[]) || [];

console.log(loans);

function goToCredit() {
    window.location.replace(route('site.requestcredit'));
}

function goToPayments(loan: Loan) {
    router.visit(route('loans.show.payments', { customer: loan.customer_id, loan: loan.id }));
}
</script>

<template>

    <Head title="Dashboard" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min">
                <Card class="mt-5">
                    <CardHeader class="flex flex-col p-4">
                        <CardTitle class="text-2xl">Préstamos Activos</CardTitle>
                        <CardDescription>Detalle de préstamos</CardDescription>
                    </CardHeader>
                    <CardContent class="w-full p-4">
                        <div v-if="loans.length <= 0">
                            <Card :class="cn('w-[380px]', $attrs.class ?? '')">
                                <CardHeader>
                                    <CardTitle>Aún no tienes préstamos</CardTitle>
                                </CardHeader>
                                <CardFooter>
                                    <Button class="w-full" @click="goToCredit">
                                        Solicitar préstamo
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>
                        <div class="w-full flex gap-4" v-else>
                            <Card v-for="loan in loans" :key="`loan-${loan.id}`">
                                <CardHeader>
                                    <CardTitle>
                                        <p>Prestamo: $ {{ currency(loan.amount) }}</p>
                                        <p class="mt-2">Pagado: $ {{ currency(loan.total_payed) }}</p>
                                    </CardTitle>
                                    <CardDescription>
                                        <p>Fecha incio: {{ loan.start_at }}</p>
                                        <Badge class="mt-2">Activo</Badge>
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>

                                    <Accordion type="single" collapsible>
                                        <AccordionItem value="item-1">
                                            <AccordionTrigger>
                                                Información General
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div class="flex w-full justify-between">
                                                    <ul>
                                                        <li>
                                                            <strong>Plazo:</strong>
                                                            {{ loan.term }} meses
                                                        </li>
                                                        <li>
                                                            <strong>Propósito:</strong>
                                                            {{ loan.purpose }}
                                                        </li>
                                                        <li>
                                                            <strong>Descripción del
                                                                Propósito:</strong>
                                                            {{
                                                                loan.purpose_description
                                                            }}
                                                        </li>
                                                        <li>
                                                            <strong>Aprobado el:</strong>
                                                            {{ loan.approved_at }}
                                                        </li>
                                                        <li>
                                                            <strong>Inicia el:</strong>
                                                            {{ loan.start_at }}
                                                        </li>
                                                        <li>
                                                            <strong>Pagado el:</strong>
                                                            {{
                                                                loan.paid_at
                                                                    ? loan.paid_at
                                                                    : 'No pagado'
                                                            }}
                                                        </li>
                                                    </ul>
                                                </div>

                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>
                                </CardContent>
                                <CardFooter>
                                    <Button @click="goToPayments(loan)">
                                        Tabla de pagos
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>
                    </CardContent>
                    <CardFooter></CardFooter>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
