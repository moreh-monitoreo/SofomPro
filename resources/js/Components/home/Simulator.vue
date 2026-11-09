<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const FALLBACK = {
    NORMAL: { name: 'Préstamo personal', min_amount: 2000, max_amount: 10000, amount_step: 1000, min_term: 6, max_term: 12 },
    FUNERARIO: { name: 'Préstamo con garantía (plan funerario)', min_amount: 2000, max_amount: 10000, amount_step: 1000, min_term: 6, max_term: 12 },
};

const products = ref(FALLBACK);
const loading = ref(false);
const result = ref(null);
const error = ref('');
const form = ref({ amountCredit: 2000, quantityPayments: 6, type: 'NORMAL' });

const available = computed(() => Object.keys(products.value).length > 0);
const product = computed(() => products.value[form.value.type] ?? Object.values(products.value)[0] ?? FALLBACK.NORMAL);
const terms = computed(() =>
    Array.from({ length: product.value.max_term - product.value.min_term + 1 }, (_, i) => product.value.min_term + i),
);

const money = (value) =>
    Number(value).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 });
const whole = (value) => Number(value).toLocaleString('es-MX', { maximumFractionDigits: 0 });
const percent = (value) => `${Number(value).toLocaleString('es-MX', { minimumFractionDigits: 1, maximumFractionDigits: 2 })}%`;

const sliderProgress = computed(() => {
    const { min_amount: min, max_amount: max } = product.value;
    return max > min ? ((form.value.amountCredit - min) / (max - min)) * 100 : 0;
});

const calculatedOn = computed(() =>
    result.value ? new Date(`${result.value.calculated_at}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'long' }) : '',
);

// Mantiene monto y plazo dentro de los límites del producto elegido
const clamp = () => {
    const p = product.value;
    form.value.amountCredit = Math.min(Math.max(Number(form.value.amountCredit), p.min_amount), p.max_amount);
    form.value.quantityPayments = Math.min(Math.max(Number(form.value.quantityPayments), p.min_term), p.max_term);
};

watch(() => form.value.type, clamp);
watch(form, () => (result.value = null), { deep: true });

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/simulator/config');
        products.value = data.products;
        if (available.value && !products.value[form.value.type]) {
            form.value.type = Object.keys(products.value)[0];
        }
        clamp();
    } catch {
        // Se conservan los límites por defecto
    }
});

// La solicitud se abre con el monto, el plazo y el producto simulados
const requestUrl = computed(() =>
    route('site.requestcredit', { monto: form.value.amountCredit, plazo: form.value.quantityPayments, tipo: form.value.type }),
);

function adjust(direction) {
    const p = product.value;
    form.value.amountCredit = Math.min(Math.max(Number(form.value.amountCredit) + direction * p.amount_step, p.min_amount), p.max_amount);
}

async function submitForm() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.post('/api/simulator', { ...form.value });
        result.value = data;
    } catch (e) {
        result.value = null;
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat()[0] : 'No pudimos calcular tu préstamo. Inténtalo de nuevo.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div
        v-if="!available"
        class="mx-auto mt-10 w-full max-w-4xl rounded-2xl bg-white p-10 text-center text-gray-700 shadow-2xl"
    >
        El simulador no está disponible por el momento. Contáctanos y con gusto te ayudamos.
    </div>
    <form
        v-else
        id="formCreditSimulator"
        class="mx-auto mt-10 w-full max-w-4xl rounded-2xl bg-white p-6 text-left shadow-2xl md:p-10"
        @submit.prevent="submitForm"
    >
        <p class="text-center text-xs font-bold uppercase tracking-wide text-gray-500">¿Cuánto necesitas?</p>
        <div class="mt-4 flex items-center justify-center gap-4 md:gap-8">
            <button
                type="button"
                aria-label="Disminuir monto"
                class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-vermilion-500 text-vermilion-500 transition hover:bg-vermilion-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-vermilion-500"
                :disabled="form.amountCredit <= product.min_amount"
                @click="adjust(-1)"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8" />
                </svg>
            </button>
            <span class="min-w-[180px] text-center text-4xl font-extrabold text-vermilion-500 md:text-5xl">
                ${{ whole(form.amountCredit) }}
            </span>
            <button
                type="button"
                aria-label="Aumentar monto"
                class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-vermilion-500 text-vermilion-500 transition hover:bg-vermilion-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-vermilion-500"
                :disabled="form.amountCredit >= product.max_amount"
                @click="adjust(1)"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4" />
                </svg>
            </button>
        </div>

        <div class="mt-6">
            <input
                id="amountCredit"
                v-model.number="form.amountCredit"
                type="range"
                :min="product.min_amount"
                :max="product.max_amount"
                :step="product.amount_step"
                aria-label="Monto del préstamo"
                class="h-2 w-full cursor-pointer appearance-none rounded-full accent-vermilion-500"
                :style="{ background: `linear-gradient(to right, #ff5a0a ${sliderProgress}%, #e5e7eb ${sliderProgress}%)` }"
            />
            <div class="mt-2 flex justify-between text-xs font-medium text-gray-500">
                <span>${{ whole(product.min_amount) }}</span>
                <span>${{ whole(product.max_amount) }}</span>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label for="loan-type" class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">
                    Tipo de préstamo
                </label>
                <select
                    id="loan-type"
                    v-model="form.type"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-700 focus:border-vermilion-500 focus:ring-vermilion-500"
                >
                    <option v-for="(item, type) in products" :key="type" :value="type">{{ item.name }}</option>
                </select>
            </div>
            <div>
                <label for="loan-term" class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">
                    ¿A qué plazo quieres pagarlo?
                </label>
                <select
                    id="loan-term"
                    v-model.number="form.quantityPayments"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-700 focus:border-vermilion-500 focus:ring-vermilion-500"
                >
                    <option v-for="months in terms" :key="months" :value="months">{{ months }} meses</option>
                </select>
            </div>
        </div>

        <p v-if="error" role="alert" class="mt-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </p>

        <button
            type="submit"
            class="mt-8 w-full rounded-full bg-vermilion-500 px-8 py-4 text-base font-bold text-white shadow-lg transition hover:bg-vermilion-600 disabled:cursor-wait disabled:opacity-60"
            :disabled="loading"
        >
            {{ loading ? 'Calculando…' : 'Simular' }}
        </button>

        <section v-if="result" class="mt-10 border-t border-gray-200 pt-8" aria-live="polite">
            <h3 class="text-center text-xl font-bold text-gray-900">Resultado de tu simulación</h3>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-vermilion-50 p-5 text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">CAT promedio sin IVA</p>
                    <p class="mt-1 text-4xl font-extrabold text-vermilion-500">{{ percent(result.cat_sin_iva) }}</p>
                </div>
                <div class="rounded-xl bg-vermilion-50 p-5 text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">CAT promedio con IVA</p>
                    <p class="mt-1 text-4xl font-extrabold text-vermilion-500">{{ percent(result.cat_con_iva) }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-4">
                <div>
                    <dt class="text-gray-500">Primer pago mensual</dt>
                    <dd class="text-lg font-bold text-gray-900">{{ money(result.first_payment) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Total a pagar</dt>
                    <dd class="text-lg font-bold text-gray-900">{{ money(result.total_to_pay) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Tasa ordinaria anual fija</dt>
                    <dd class="text-lg font-bold text-gray-900">{{ percent(result.annual_rate) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Tasa ordinaria mensual</dt>
                    <dd class="text-lg font-bold text-gray-900">{{ percent(result.monthly_rate) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Comisión por apertura ({{ percent(result.opening_fee_percent) }})</dt>
                    <dd class="font-semibold text-gray-900">{{ money(result.opening_fee_amount) }} + IVA {{ money(result.opening_fee_iva) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">IVA sobre intereses ({{ percent(result.iva_rate) }})</dt>
                    <dd class="font-semibold text-gray-900">{{ money(result.totals.iva) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Intereses totales</dt>
                    <dd class="font-semibold text-gray-900">{{ money(result.totals.interest) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Monto del préstamo</dt>
                    <dd class="font-semibold text-gray-900">{{ money(result.amount) }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex justify-center">
                <a
                    :href="requestUrl"
                    class="rounded-md bg-vermilion-500 px-6 py-3 text-base font-semibold text-white shadow transition-colors duration-200 hover:bg-vermilion-600"
                >
                    Solicitar con estas condiciones
                </a>
            </div>

            <h4 class="mt-8 text-base font-bold text-gray-900">Tabla de amortización</h4>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full min-w-[760px] text-right text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3 text-center">Pago</th>
                            <th class="px-3 py-3">Saldo inicial</th>
                            <th class="px-3 py-3">Capital</th>
                            <th class="px-3 py-3">Interés</th>
                            <th class="px-3 py-3">Comisión</th>
                            <th class="px-3 py-3">IVA</th>
                            <th class="px-3 py-3">Pago</th>
                            <th class="px-3 py-3">Saldo final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr v-if="result.opening_fee_amount > 0" class="bg-vermilion-50/50">
                            <td class="px-3 py-2 text-center font-semibold">Apertura</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">{{ money(result.opening_fee_amount) }}</td>
                            <td class="px-3 py-2">{{ money(result.opening_fee_iva) }}</td>
                            <td class="px-3 py-2 font-semibold text-gray-900">{{ money(result.opening_fee_amount + result.opening_fee_iva) }}</td>
                            <td class="px-3 py-2">{{ money(result.amount) }}</td>
                        </tr>
                        <tr v-for="row in result.schedule" :key="row.number">
                            <td class="px-3 py-2 text-center font-semibold">{{ row.number }}</td>
                            <td class="px-3 py-2">{{ money(row.opening_balance) }}</td>
                            <td class="px-3 py-2">{{ money(row.principal) }}</td>
                            <td class="px-3 py-2">{{ money(row.interest) }}</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">{{ money(row.iva) }}</td>
                            <td class="px-3 py-2 font-semibold text-gray-900">{{ money(row.payment) }}</td>
                            <td class="px-3 py-2">{{ money(row.balance) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold text-gray-900">
                        <tr>
                            <td class="px-3 py-3 text-center">Total</td>
                            <td></td>
                            <td class="px-3 py-3">{{ money(result.totals.principal) }}</td>
                            <td class="px-3 py-3">{{ money(result.totals.interest) }}</td>
                            <td class="px-3 py-3">{{ money(result.opening_fee_amount) }}</td>
                            <td class="px-3 py-3">{{ money(result.totals.iva + result.opening_fee_iva) }}</td>
                            <td class="px-3 py-3">{{ money(result.total_to_pay) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-4 text-xs leading-relaxed text-gray-500">
                CAT (Costo Anual Total) promedio, expresado en términos porcentuales anuales, para fines informativos y de comparación.
                Fecha de cálculo: {{ calculatedOn }}. Tasa de interés ordinaria fija sobre saldo insoluto; los pagos son mensuales vencidos.
                El CAT incluye la comisión por apertura; el CAT con IVA incluye además el IVA de intereses y comisiones. Simulación
                informativa, sujeta a la aprobación del crédito.
            </p>
        </section>
    </form>
</template>
