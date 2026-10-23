<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { currency } from '@/lib/formaters';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    loans: { type: [Object, Array], required: true },
    counts: { type: Object, required: true },
    stages: { type: Object, required: true },
    filters: { type: Object, required: true },
    boardLimit: { type: Number, default: 150 },
});

const search = ref(props.filters.q ?? '');
const type = ref(props.filters.type ?? '');
const overdue = ref(props.filters.overdue);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const isBoard = computed(() => props.filters.view === 'board');
const rows = computed(() => (Array.isArray(props.loans) ? props.loans : props.loans.data));
const links = computed(() => (Array.isArray(props.loans) ? [] : props.loans.links));
const total = computed(() => Object.values(props.counts).reduce((sum, n) => sum + n, 0));

const badge = {
    received: 'bg-gray-100 text-gray-800',
    in_validation: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    disbursed: 'bg-teal-100 text-teal-800',
    paid: 'bg-emerald-100 text-emerald-900',
    rejected: 'bg-red-100 text-red-800',
};

const columnTop = {
    received: 'border-gray-400',
    in_validation: 'border-blue-500',
    approved: 'border-green-500',
    disbursed: 'border-teal-500',
    paid: 'border-emerald-700',
    rejected: 'border-red-500',
};

const board = computed(() => Object.keys(props.stages).map((stage) => ({ stage, label: props.stages[stage], loans: rows.value.filter((l) => l.stage === stage) })));

function go(overrides = {}) {
    const params = {
        stage: props.filters.stage,
        q: search.value,
        type: type.value,
        overdue: overdue.value ? 1 : undefined,
        from: from.value,
        to: to.value,
        view: props.filters.view,
        ...overrides,
    };
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null || params[key] === undefined) && delete params[key]);
    router.get(route('pipeline.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let timer;
function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => go(), 350);
}

const clear = () => {
    search.value = '';
    type.value = '';
    overdue.value = false;
    from.value = '';
    to.value = '';
    router.get(route('pipeline.index'), { view: props.filters.view }, { preserveState: true, replace: true });
};

const hasFilters = computed(() => search.value || type.value || overdue.value || from.value || to.value);
</script>

<template>
    <Head title="Pipeline de solicitudes" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <Card class="mt-5">
                <CardHeader class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle class="text-2xl">Pipeline de solicitudes</CardTitle>
                            <CardDescription>Seguimiento de cada solicitud desde que se recibe hasta que se liquida.</CardDescription>
                        </div>
                        <div class="inline-flex rounded-lg border border-gray-300 p-1 text-sm">
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1.5', !isBoard ? 'bg-vermilion-500 text-white' : 'text-gray-600']"
                                @click="go({ view: 'table' })"
                            >
                                Tabla
                            </button>
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1.5', isBoard ? 'bg-vermilion-500 text-white' : 'text-gray-600']"
                                @click="go({ view: 'board', stage: undefined })"
                            >
                                Tablero
                            </button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 p-4 pt-0">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre, CURP, RFC, correo, teléfono o folio"
                            class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm md:col-span-2"
                            @input="onSearch"
                        />
                        <select v-model="type" class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" @change="go()">
                            <option value="">Todos los productos</option>
                            <option value="NORMAL">Personal</option>
                            <option value="FUNERARIO">Con garantía</option>
                        </select>
                        <input v-model="from" type="date" aria-label="Desde" class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" @change="go()" />
                        <input v-model="to" type="date" aria-label="Hasta" class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" @change="go()" />
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="overdue" type="checkbox" class="rounded border-gray-300" @change="go()" />
                            Solo en mora
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="!isBoard"
                            type="button"
                            :class="['rounded-full border px-3 py-1 text-sm', !filters.stage ? 'border-vermilion-500 bg-vermilion-50 text-vermilion-600' : 'border-gray-300 text-gray-600']"
                            @click="go({ stage: undefined })"
                        >
                            Todas <span class="font-semibold">{{ total }}</span>
                        </button>
                        <button
                            v-for="(label, stage) in stages"
                            :key="stage"
                            type="button"
                            :disabled="isBoard"
                            :class="[
                                'rounded-full border px-3 py-1 text-sm',
                                filters.stage === stage && !isBoard ? 'border-vermilion-500 bg-vermilion-50 text-vermilion-600' : 'border-gray-300 text-gray-600',
                                isBoard ? 'cursor-default' : '',
                            ]"
                            @click="go({ stage })"
                        >
                            {{ label }} <span class="font-semibold">{{ counts[stage] }}</span>
                        </button>
                        <button v-if="hasFilters" type="button" class="ml-2 text-sm text-vermilion-600 underline" @click="clear">Limpiar filtros</button>
                    </div>

                    <!-- Tabla -->
                    <div v-if="!isBoard" class="relative overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 uppercase text-gray-700">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Monto</th>
                                    <th class="px-4 py-3">Plazo</th>
                                    <th class="px-4 py-3">Producto</th>
                                    <th class="px-4 py-3">Etapa</th>
                                    <th class="px-4 py-3">Recibida</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="rows.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">No hay solicitudes con esos filtros.</td>
                                </tr>
                                <tr v-for="loan in rows" :key="loan.id" class="border-t bg-white hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ loan.folio || loan.id }}</td>
                                    <td class="px-4 py-3">
                                        <a :href="route('customers.show', loan.customer.id)" class="font-medium text-gray-900 hover:underline">{{ loan.customer.full_name }}</a>
                                        <div class="text-xs text-gray-500">{{ loan.customer.phone }}</div>
                                    </td>
                                    <td class="px-4 py-3">$ {{ currency(loan.amount) }}</td>
                                    <td class="px-4 py-3">{{ loan.term ? `${loan.term} meses` : '-' }}</td>
                                    <td class="px-4 py-3">{{ loan.type === 'FUNERARIO' ? 'Con garantía' : 'Personal' }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="['rounded px-2.5 py-0.5 text-xs font-medium', badge[loan.stage]]">{{ loan.stage_label }}</span>
                                        <span
                                            v-if="loan.is_overdue"
                                            class="ml-2 rounded bg-red-600 px-2.5 py-0.5 text-xs font-semibold text-white"
                                            :title="`Debe $${currency(loan.overdue_amount)}`"
                                        >
                                            Mora {{ loan.days_overdue }} d
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ loan.created_at }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a :href="route('loans.show', loan.id)" class="font-medium text-vermilion-600 hover:underline">Revisar</a>
                                        <a v-if="loan.stage !== 'received' && loan.stage !== 'in_validation' && loan.stage !== 'rejected'" :href="route('loans.payments.index', loan.id)" class="ml-3 font-medium text-blue-600 hover:underline">Pagos</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <nav v-if="!isBoard && links.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="Paginación">
                        <template v-for="(link, i) in links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                :class="['rounded border px-3 py-1 text-sm', link.active ? 'border-vermilion-500 bg-vermilion-500 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50']"
                                v-html="link.label"
                            />
                            <span v-else class="rounded border border-gray-200 px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                        </template>
                    </nav>

                    <!-- Tablero -->
                    <div v-else class="overflow-x-auto pb-2">
                        <div class="flex min-w-max gap-4">
                            <section v-for="column in board" :key="column.stage" :class="['w-72 shrink-0 rounded-lg border-t-4 bg-gray-50 p-3', columnTop[column.stage]]">
                                <h3 class="mb-3 flex items-center justify-between text-sm font-semibold text-gray-800">
                                    {{ column.label }}
                                    <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-600">{{ counts[column.stage] }}</span>
                                </h3>
                                <div class="space-y-3">
                                    <p v-if="column.loans.length === 0" class="py-6 text-center text-xs text-gray-400">Sin solicitudes</p>
                                    <a
                                        v-for="loan in column.loans"
                                        :key="loan.id"
                                        :href="route('loans.show', loan.id)"
                                        class="block rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md"
                                    >
                                        <p class="font-medium text-gray-900">{{ loan.customer.full_name }}</p>
                                        <p class="mt-1 text-sm text-gray-600">$ {{ currency(loan.amount) }} · {{ loan.term ? `${loan.term} meses` : '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ loan.type === 'FUNERARIO' ? 'Con garantía' : 'Personal' }} · {{ loan.created_at }}</p>
                                        <p v-if="loan.is_overdue" class="mt-2 inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                                            Mora {{ loan.days_overdue }} d · ${{ currency(loan.overdue_amount) }}
                                        </p>
                                    </a>
                                </div>
                            </section>
                        </div>
                        <p v-if="rows.length >= boardLimit" class="mt-3 text-xs text-gray-500">
                            Se muestran las {{ boardLimit }} solicitudes más recientes; usa los filtros para acotar.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
