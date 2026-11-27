<script setup>
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    loanId: { type: Number, required: true },
    collection: { type: Object, required: true },
});

const toast = useToast();
const actions = ref([...props.collection.actions]);
const blank = () => ({ channel: 'llamada', outcome: 'contactado', notes: '', promise_amount: '', promise_date: '' });
const form = reactive(blank());
const errors = ref({});
const saving = ref(false);
const isPromise = computed(() => form.outcome === 'promesa');

const money = (v) => Number(v || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const dateTime = (v) => new Date(v).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
const day = (v) => new Date(`${v}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'medium' });
const promiseStyle = { pendiente: 'bg-blue-100 text-blue-800', cumplida: 'bg-green-100 text-green-800', incumplida: 'bg-red-100 text-red-800' };

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { channel: form.channel, outcome: form.outcome, notes: form.notes || null };
        if (isPromise.value) Object.assign(payload, { promise_amount: form.promise_amount, promise_date: form.promise_date });
        const { data } = await axios.post(route('loans.collection.store', props.loanId), payload);
        // Una promesa nueva deja sin efecto la anterior pendiente
        if (isPromise.value) actions.value = actions.value.map((a) => (a.promise_status === 'pendiente' ? { ...a, promise_status: 'incumplida' } : a));
        actions.value.unshift(data.data);
        Object.assign(form, blank());
        toast.success(data.message);
    } catch (error) {
        const list = error.response?.data?.errors;
        if (list) errors.value = Object.fromEntries(Object.entries(list).map(([k, v]) => [k, v[0]]));
        else toast.error(error.response?.data?.message || 'No se pudo registrar la gestión.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow sm:p-6 md:p-8">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-900">Gestión de cobranza</h3>
            <p class="text-sm text-gray-600">Gestor asignado: <span class="font-medium text-gray-900">{{ collection.collector?.name ?? 'sin asignar' }}</span></p>
        </div>

        <form v-if="collection.canRecord" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4" @submit.prevent="save">
            <div>
                <label for="col-channel" class="mb-1 block text-sm font-medium text-gray-900">Medio</label>
                <select id="col-channel" v-model="form.channel" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm">
                    <option v-for="(label, value) in collection.channels" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <div>
                <label for="col-outcome" class="mb-1 block text-sm font-medium text-gray-900">Resultado</label>
                <select id="col-outcome" v-model="form.outcome" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm">
                    <option v-for="(label, value) in collection.outcomes" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <template v-if="isPromise">
                <div>
                    <label for="col-amount" class="mb-1 block text-sm font-medium text-gray-900">Importe prometido</label>
                    <input id="col-amount" v-model="form.promise_amount" type="number" min="1" step="0.01" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm" />
                    <p v-if="errors.promise_amount" class="mt-1 text-sm text-red-600">{{ errors.promise_amount }}</p>
                </div>
                <div>
                    <label for="col-date" class="mb-1 block text-sm font-medium text-gray-900">Fecha prometida</label>
                    <input id="col-date" v-model="form.promise_date" type="date" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm" />
                    <p v-if="errors.promise_date" class="mt-1 text-sm text-red-600">{{ errors.promise_date }}</p>
                </div>
            </template>
            <div class="md:col-span-4">
                <label for="col-notes" class="mb-1 block text-sm font-medium text-gray-900">Notas</label>
                <textarea id="col-notes" v-model="form.notes" rows="2" maxlength="1000" class="block w-full rounded-lg border border-gray-300 p-2.5 text-sm" placeholder="Qué se habló, con quién, siguiente paso"></textarea>
            </div>
            <div class="flex items-center justify-between gap-4 md:col-span-4">
                <p class="text-xs text-gray-500">Una promesa se da por cumplida al entrar el pago; si vence sin pagarse, se avisa al gestor por correo.</p>
                <button type="submit" :disabled="saving" class="rounded-lg bg-gray-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60">Registrar gestión</button>
            </div>
        </form>
        <p v-else class="mt-2 text-sm text-gray-600">Solo el gestor asignado, un asesor, un gerente o un administrador pueden registrar gestiones en este préstamo.</p>

        <p v-if="!actions.length" class="mt-4 text-sm text-gray-600">Aún no hay gestiones registradas.</p>
        <ol v-else class="mt-4 space-y-3">
            <li v-for="a in actions" :key="a.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="font-medium text-gray-900">{{ collection.outcomes[a.outcome] ?? a.outcome }}</span>
                    <span class="text-gray-600">{{ collection.channels[a.channel] ?? a.channel }}</span>
                    <span class="text-gray-500">{{ dateTime(a.created_at) }} · {{ a.user ?? '—' }}</span>
                    <span v-if="a.promise_status" :class="['rounded px-2 py-0.5 text-xs font-semibold', promiseStyle[a.promise_status]]">
                        Promesa {{ (collection.promiseStatuses[a.promise_status] ?? a.promise_status).toLowerCase() }}: {{ money(a.promise_amount) }} al {{ day(a.promise_date) }}
                    </span>
                </div>
                <p v-if="a.notes" class="mt-1 whitespace-pre-line text-gray-700">{{ a.notes }}</p>
            </li>
        </ol>
    </section>
</template>
