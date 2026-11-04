<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    actions: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({ action: props.filters.action, loan: props.filters.loan });

const apply = () => {
    router.get(route('admin.audit.index'), { action: form.action || undefined, loan: form.loan || undefined }, { preserveState: true, replace: true });
};

const when = (iso) => new Date(iso).toLocaleString('es-MX', { timeZone: 'America/Mexico_City' });

const detail = (log) => {
    const parts = [];
    if (log.before) parts.push(`Antes: ${JSON.stringify(log.before)}`);
    if (log.after) parts.push(`Después: ${JSON.stringify(log.after)}`);
    if (log.note) parts.push(log.note);
    return parts.join(' · ');
};
</script>

<template>
    <Head title="Bitácora de auditoría" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="mt-5">
                <h1 class="text-2xl font-bold text-gray-900">Bitácora de auditoría</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Registro de solo lectura: quién aprobó, cambió montos, registró o revirtió pagos y manejó documentos.
                </p>
            </div>

            <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                <div>
                    <label for="audit-action" class="mb-1 block text-sm font-medium text-gray-700">Acción</label>
                    <select id="audit-action" v-model="form.action" class="rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" @change="apply">
                        <option value="">Todas</option>
                        <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label for="audit-loan" class="mb-1 block text-sm font-medium text-gray-700">Préstamo (id)</label>
                    <input id="audit-loan" v-model="form.loan" type="number" min="1" class="w-32 rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm" />
                </div>
                <button type="submit" class="rounded-lg bg-gray-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800">Filtrar</button>
            </form>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Usuario</th>
                            <th class="px-4 py-3">Acción</th>
                            <th class="px-4 py-3">Préstamo</th>
                            <th class="px-4 py-3">Detalle</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-t border-gray-100 align-top">
                            <td class="whitespace-nowrap px-4 py-3">{{ when(log.created_at) }}</td>
                            <td class="px-4 py-3">{{ log.user?.name ?? 'Sistema' }}<span v-if="log.user" class="block text-xs text-gray-500">{{ log.user.email }}</span></td>
                            <td class="px-4 py-3 font-medium">{{ log.label }}</td>
                            <td class="px-4 py-3">
                                <Link v-if="log.loan_id" :href="route('loans.show', log.loan_id)" class="text-blue-600 hover:underline">#{{ log.loan_id }}</Link>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ detail(log) || '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ log.ip }}</td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Aún no hay movimientos registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="logs.links?.length > 3" class="flex flex-wrap gap-1">
                <template v-for="link in logs.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :class="['rounded border px-3 py-1 text-sm', link.active ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-100']"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </DashboardLayout>
</template>
