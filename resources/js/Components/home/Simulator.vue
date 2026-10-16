<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const MIN_AMOUNT = 2000;
const MAX_AMOUNT = 10000;
const STEP = 1000;

const loading = ref(false);
const payment = ref(null);
const annualRate = ref(null);
const form = ref({
    amountCredit: MIN_AMOUNT,
    quantityPayments: 6,
    type: 'NORMAL',
});

const formatMoney = (value) =>
    Number(value).toLocaleString('es-MX', { maximumFractionDigits: 0 });

const amountLabel = computed(() => formatMoney(form.value.amountCredit));
const sliderProgress = computed(
    () =>
        ((form.value.amountCredit - MIN_AMOUNT) / (MAX_AMOUNT - MIN_AMOUNT)) *
        100,
);

// Si cambian los parámetros, el resultado anterior ya no aplica
watch(form, () => (payment.value = null), { deep: true });

function submitForm() {
    loading.value = true;
    axios
        .post(`/api/simulator`, { ...form.value })
        .then((response) => {
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
    if (form.value.amountCredit > MIN_AMOUNT) {
        form.value.amountCredit = parseInt(form.value.amountCredit) - STEP;
    }
}
function addAmountCredit() {
    if (form.value.amountCredit < MAX_AMOUNT) {
        form.value.amountCredit = parseInt(form.value.amountCredit) + STEP;
    }
}
</script>
<template>
    <form
        id="formCreditSimulator"
        class="mx-auto mt-10 w-full max-w-3xl rounded-2xl bg-white p-6 text-left shadow-2xl md:p-10"
        @submit.prevent="submitForm"
    >
        <!-- Monto -->
        <p class="text-center text-xs font-bold uppercase tracking-wide text-gray-500">
            ¿Cuánto necesitas?
        </p>
        <div class="mt-4 flex items-center justify-center gap-4 md:gap-8">
            <button
                type="button"
                aria-label="Disminuir monto"
                class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-vermilion-500 text-vermilion-500 transition hover:bg-vermilion-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-vermilion-500"
                :disabled="form.amountCredit <= MIN_AMOUNT"
                @click="subtractAmountCredit"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
                </svg>
            </button>
            <span class="min-w-[180px] text-center text-4xl font-extrabold text-vermilion-500 md:text-5xl">
                ${{ amountLabel }}
            </span>
            <button
                type="button"
                aria-label="Aumentar monto"
                class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-vermilion-500 text-vermilion-500 transition hover:bg-vermilion-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-vermilion-500"
                :disabled="form.amountCredit >= MAX_AMOUNT"
                @click="addAmountCredit"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                </svg>
            </button>
        </div>

        <div class="mt-6">
            <input
                id="amountCredit"
                v-model.number="form.amountCredit"
                type="range"
                :min="MIN_AMOUNT"
                :max="MAX_AMOUNT"
                :step="STEP"
                class="h-2 w-full cursor-pointer appearance-none rounded-full accent-vermilion-500"
                :style="{
                    background: `linear-gradient(to right, #ff5a0a ${sliderProgress}%, #e5e7eb ${sliderProgress}%)`,
                }"
            />
            <div class="mt-2 flex justify-between text-xs font-medium text-gray-500">
                <span>${{ formatMoney(MIN_AMOUNT) }}</span>
                <span>${{ formatMoney(MAX_AMOUNT) }}</span>
            </div>
        </div>

        <!-- Tipo y plazo -->
        <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label
                    for="loan-type"
                    class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500"
                >
                    Tipo de préstamo
                </label>
                <select
                    id="loan-type"
                    v-model="form.type"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-700 focus:border-vermilion-500 focus:ring-vermilion-500"
                >
                    <option value="NORMAL">Préstamo personal</option>
                    <option value="FUNERARIO">Préstamo con garantía (plan funerario)</option>
                </select>
            </div>
            <div>
                <label
                    for="loan-term"
                    class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500"
                >
                    ¿A qué plazo quieres pagarlo?
                </label>
                <select
                    id="loan-term"
                    v-model.number="form.quantityPayments"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-700 focus:border-vermilion-500 focus:ring-vermilion-500"
                >
                    <option v-for="months in [6, 7, 8, 9, 10, 11, 12]" :key="months" :value="months">
                        {{ months }} meses
                    </option>
                </select>
            </div>
        </div>

        <!-- Resultado -->
        <div class="mt-8 rounded-xl bg-vermilion-50 p-6 text-center">
            <template v-if="payment != null">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                    Tu pago mensual estimado
                </p>
                <p class="mt-2 text-4xl font-extrabold text-vermilion-500 md:text-5xl">
                    ${{ payment }}
                    <span class="text-lg font-bold text-gray-600">/ mes</span>
                </p>
                <p class="mt-3 text-xs text-gray-600">
                    Tasa ordinaria fija {{ annualRate }}% anual ({{ form.type === 'FUNERARIO' ? '6.19' : '6.63' }}% mensual sobre saldo insoluto). No incluye IVA sobre intereses.
                </p>
            </template>
            <p v-else class="text-sm text-gray-600">
                Elige el monto, tipo y plazo, y presiona <strong>Simular</strong> para ver tu pago mensual.
            </p>
        </div>

        <button
            type="submit"
            class="mt-6 w-full rounded-full bg-vermilion-500 px-8 py-4 text-base font-bold text-white shadow-lg transition hover:bg-vermilion-600 disabled:cursor-wait disabled:opacity-60"
            :disabled="loading"
        >
            {{ loading ? 'Calculando…' : 'Simular' }}
        </button>
    </form>
</template>
