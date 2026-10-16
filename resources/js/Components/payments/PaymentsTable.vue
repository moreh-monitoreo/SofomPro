<script setup>
import { currency, round } from '@/lib/formaters';
import { computed, defineProps, ref, watch } from 'vue';

const props = defineProps({
    paymentTable: Object,
});

const paymentTable = ref(renamePayments(props.paymentTable) || {});

// Condiciones del plan (sirven tanto para el plan generado como para el guardado)
const conditions = computed(() => {
    const t = paymentTable.value || {};
    const months = t.total_months;
    const annual = t.annual_rate ?? (t.month_rate ? t.month_rate * 12 * 100 : null);
    return {
        months,
        fees: t.opening_fees !== undefined && t.opening_fees !== null ? Number(t.opening_fees) : null,
        annual: annual !== null && annual !== undefined ? Number(annual) : null,
    };
});

function renamePayments(newValue) {
    if (newValue.details) {
        newValue.payments = newValue.details;
        delete newValue.details;
    }
    return newValue || null;
}
watch(
    () => props.paymentTable,
    (newValue) => {
        paymentTable.value = newValue;
    },
);
</script>

<template>
    <div class="relative overflow-x-auto">
        <div class="flex">
            <div class="w-1/3 px-3">
                <div
                    class="mb-6 flex w-full items-center rounded-lg border bg-white p-6 text-blue-400 xl:mb-0"
                >
                    <svg
                        class="mr-4 hidden h-16 w-16 fill-current lg:block"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M17.35,2.219h-5.934c-0.115,0-0.225,0.045-0.307,0.128l-8.762,8.762c-0.171,0.168-0.171,0.443,0,0.611l5.933,5.934c0.167,0.171,0.443,0.169,0.612,0l8.762-8.763c0.083-0.083,0.128-0.192,0.128-0.307V2.651C17.781,2.414,17.587,2.219,17.35,2.219M16.916,8.405l-8.332,8.332l-5.321-5.321l8.333-8.332h5.32V8.405z M13.891,4.367c-0.957,0-1.729,0.772-1.729,1.729c0,0.957,0.771,1.729,1.729,1.729s1.729-0.772,1.729-1.729C15.619,5.14,14.848,4.367,13.891,4.367 M14.502,6.708c-0.326,0.326-0.896,0.326-1.223,0c-0.338-0.342-0.338-0.882,0-1.224c0.342-0.337,0.881-0.337,1.223,0C14.84,5.826,14.84,6.366,14.502,6.708"
                        ></path>
                    </svg>

                    <div class="text-gray-700">
                        <p class="text-2xl font-semibold">
                            {{ currency(paymentTable.month_rate * 100) }} %
                        </p>
                        <p>Interés mensual</p>
                    </div>
                </div>
            </div>
            <div class="w-1/3 px-3">
                <div
                    class="mb-6 flex w-full items-center rounded-lg border bg-white p-6 text-blue-400 xl:mb-0"
                >
                    <svg
                        class="mr-4 hidden h-16 w-16 fill-current lg:block"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M17.35,2.219h-5.934c-0.115,0-0.225,0.045-0.307,0.128l-8.762,8.762c-0.171,0.168-0.171,0.443,0,0.611l5.933,5.934c0.167,0.171,0.443,0.169,0.612,0l8.762-8.763c0.083-0.083,0.128-0.192,0.128-0.307V2.651C17.781,2.414,17.587,2.219,17.35,2.219M16.916,8.405l-8.332,8.332l-5.321-5.321l8.333-8.332h5.32V8.405z M13.891,4.367c-0.957,0-1.729,0.772-1.729,1.729c0,0.957,0.771,1.729,1.729,1.729s1.729-0.772,1.729-1.729C15.619,5.14,14.848,4.367,13.891,4.367 M14.502,6.708c-0.326,0.326-0.896,0.326-1.223,0c-0.338-0.342-0.338-0.882,0-1.224c0.342-0.337,0.881-0.337,1.223,0C14.84,5.826,14.84,6.366,14.502,6.708"
                        ></path>
                    </svg>

                    <div class="text-gray-700">
                        <p class="text-2xl font-semibold">
                            {{
                                round(
                                    (paymentTable.CAT_simple_interest ||
                                        paymentTable.cat_amount) * 100,
                                )
                            }}
                            %
                        </p>
                        <p>CAT {{ paymentTable.type }}</p>
                    </div>
                </div>
            </div>
            <div class="w-1/3 px-3">
                <div
                    class="mb-6 flex w-full items-center rounded-lg border bg-white p-6 text-blue-400 xl:mb-0"
                >
                    <svg
                        class="mr-4 hidden h-16 w-16 fill-current lg:block"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M17.35,2.219h-5.934c-0.115,0-0.225,0.045-0.307,0.128l-8.762,8.762c-0.171,0.168-0.171,0.443,0,0.611l5.933,5.934c0.167,0.171,0.443,0.169,0.612,0l8.762-8.763c0.083-0.083,0.128-0.192,0.128-0.307V2.651C17.781,2.414,17.587,2.219,17.35,2.219M16.916,8.405l-8.332,8.332l-5.321-5.321l8.333-8.332h5.32V8.405z M13.891,4.367c-0.957,0-1.729,0.772-1.729,1.729c0,0.957,0.771,1.729,1.729,1.729s1.729-0.772,1.729-1.729C15.619,5.14,14.848,4.367,13.891,4.367 M14.502,6.708c-0.326,0.326-0.896,0.326-1.223,0c-0.338-0.342-0.338-0.882,0-1.224c0.342-0.337,0.881-0.337,1.223,0C14.84,5.826,14.84,6.366,14.502,6.708"
                        ></path>
                    </svg>

                    <div class="text-gray-700">
                        <p class="text-2xl font-semibold">
                            $
                            {{
                                currency(
                                    paymentTable.to_pay_simple_interest ||
                                        paymentTable.payment_amount,
                                )
                            }}
                        </p>
                        <p>Total a pagar</p>
                    </div>
                </div>
            </div>
        </div>
        <div
            v-if="conditions.months"
            class="mx-3 mb-4 flex flex-wrap gap-x-8 gap-y-1 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700"
        >
            <span>Plazo: <strong>{{ conditions.months }} meses</strong></span>
            <span v-if="conditions.fees !== null">Comisión por apertura: <strong>{{ conditions.fees }} %</strong></span>
            <span v-if="conditions.annual !== null">Tasa anual: <strong>{{ Number(conditions.annual).toFixed(2) }} %</strong></span>
        </div>
        <table
            class="w-full text-left text-sm text-gray-500 rtl:text-right dark:text-gray-400"
        >
            <thead
                class="bg-gray-50 uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400"
            >
                <tr>
                    <th scope="col" class="px-6 py-4">Mes</th>
                    <th scope="col" class="px-6 py-4">Interés</th>
                    <th scope="col" class="px-6 py-4">Abono principal</th>
                    <th scope="col" class="px-6 py-4">Balance</th>
                    <th scope="col" class="px-6 py-4">Pago mensual</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(payment, index) in paymentTable.payments"
                    :key="`payment-${payment.id}`"
                    class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600"
                >
                    <th
                        scope="row"
                        class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-white"
                    >
                        {{ index + 1 }}
                    </th>
                    <td class="px-6 py-4">
                        {{ currency(payment.interest_payment) }}
                    </td>
                    <td class="px-6 py-4">
                        {{ currency(payment.principal_payment) }}
                    </td>
                    <td class="px-6 py-4">
                        {{ currency(payment.balance) }}
                    </td>
                    <td class="px-6 py-4">
                        {{ currency(payment.monthly_payment) }}
                    </td>
                </tr>
                <tr
                    class="bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-600"
                    v-if="payments?.length <= 0"
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
</template>
