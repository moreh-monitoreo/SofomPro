<script setup>
import axios from 'axios';
import { ref } from 'vue';

const loading = ref(false);
const payment = ref(null);
const annualRate = ref(null);
const form = ref({
    amountCredit: 2000,
    quantityPayments: 6,
    type: 'NORMAL',
});

function submitForm() {
    loading.value = true;
    axios
        .post(`/api/simulator`, { ...form.value })
        .then((response) => {
            console.log(response.data);
            payment.value = response.data.payment;
            annualRate.value = response.data.annual_rate;
        })
        .catch(() => {
            payment.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}
function subtractAmountCredit() {
    if (form.value.amountCredit > 2000) {
        form.value.amountCredit = parseInt(form.value.amountCredit) - 1000;
    }
}
function addAmountCredit() {
    if (form.value.amountCredit < 10000) {
        form.value.amountCredit = parseInt(form.value.amountCredit) + 1000;
    }
}
</script>
<template>
    <section class="border-b py-8">
        <div class="container m-8 mx-auto max-w-5xl">
            <form
                action=""
                id="formCreditSimulator"
                @submit.prevent="submitForm"
            >
                <div class="flex w-full flex-shrink flex-grow flex-col p-6">
                    <div
                        class="flex-1 overflow-hidden rounded-b-none rounded-t shadow"
                    >
                        <div
                            class="flex flex-col md:flex-row w-full flex-wrap items-center justify-center gap-4"
                        >
                            <label
                                class="w-full mb-2 block text-xs font-bold uppercase tracking-wide text-white"
                                for="grid-state"
                            >
                                ¿Cuánto necesitas?
                            </label>
                            <button
                                class="focus:shadow-outline z-10 mx-auto my-6 transform rounded-full bg-white px-4 py-2 md:px-8 md:py-4 font-bold text-vermilion-500 shadow-lg transition duration-300 ease-in-out hover:scale-105 focus:outline-none lg:mx-0"
                                type="button"
                                @click="subtractAmountCredit"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-dash" viewBox="0 0 16 16">
                                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
                                </svg>
                            </button>
                            <label class="text-4xl font-bold text-vermilion-500"
                                >$ {{ form.amountCredit }}</label
                            >
                            <button
                                class="focus:shadow-outline z-10 mx-auto my-6 transform rounded-full bg-white px-4 py-2 md:px-8 md:py-4 font-bold text-vermilion-500 shadow-lg transition duration-300 ease-in-out hover:scale-105 focus:outline-none lg:mx-0"
                                type="button"
                                @click="addAmountCredit"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                </svg>
                            </button>
                        </div>
                        <div class="mx-auto w-1/2 justify-center">
                            <input
                                class="w-full"
                                type="range"
                                min="2000"
                                max="10000"
                                step="1000"
                                id="amountCredit"
                                v-model="form.amountCredit"
                            />
                        </div>
                        <div
                            class="mx-auto mb-6 mt-4 w-full px-3 md:mb-0 md:w-1/3"
                        >
                            <label
                                class="mb-2 block text-xs font-bold uppercase tracking-wide text-white"
                                for="loan-type"
                            >
                                Tipo de préstamo
                            </label>
                            <select
                                id="loan-type"
                                class="mb-4 block w-full appearance-none rounded border border-gray-200 bg-gray-200 px-4 py-3 pr-8 leading-tight text-gray-700 focus:border-gray-500 focus:bg-white focus:outline-none"
                                v-model="form.type"
                                @change="payment = null"
                            >
                                <option value="NORMAL">Préstamo personal</option>
                                <option value="FUNERARIO">Préstamo con garantía (plan funerario)</option>
                            </select>
                            <label
                                class="mb-2 block text-xs font-bold uppercase tracking-wide text-white"
                                for="grid-state"
                            >
                                ¿A qué plazo quieres pagarlo?
                            </label>
                            <div class="relative">
                                <select
                                    class="block w-full appearance-none rounded border border-gray-200 bg-gray-200 px-4 py-3 pr-8 leading-tight text-gray-700 focus:border-gray-500 focus:bg-white focus:outline-none"
                                    id="grid-state"
                                    v-model="form.quantityPayments"
                                >
                                    <option value="6">6 Meses</option>
                                    <option value="7">7 Meses</option>
                                    <option value="8">8 Meses</option>
                                    <option value="9">9 Meses</option>
                                    <option value="10">10 Meses</option>
                                    <option value="11">11 Meses</option>
                                    <option value="12">12 Meses</option>
                                </select>
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700"
                                >
                                    <svg
                                        class="h-4 w-4 fill-current"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="mt-auto flex-none overflow-hidden rounded-b rounded-t-none p-6 shadow"
                    >
                        <div class="flex flex-wrap items-center justify-center">
                            <a
                                href=""
                                v-if="payment != null"
                                class="w-full text-center text-4xl font-bold text-vermilion-500"
                                >$ {{ payment }} al mes.</a
                            >
                            <p v-if="payment != null" class="w-full text-center text-xs text-gray-600">
                                Tasa ordinaria fija {{ annualRate }}% anual ({{ form.type === 'FUNERARIO' ? '6.19' : '6.63' }}% mensual sobre saldo insoluto). No incluye IVA sobre intereses.
                            </p>
                            <button
                                type="submit"
                                class="bg-white gradient focus:shadow-outline mx-auto my-6 transform rounded-full px-8 py-4 font-bold shadow-lg transition duration-300 ease-in-out hover:scale-105 hover:underline focus:outline-none lg:mx-0 text-vermilion-500"
                                :disabled="loading"
                            >
                                Simular
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</template>
