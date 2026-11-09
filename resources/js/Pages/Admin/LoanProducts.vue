<script setup>
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
    products: { type: Array, required: true },
    documentTypes: { type: Array, default: () => [] },
});

const toast = useToast();

const forms = props.products.map((product) =>
    useForm({
        type: product.type,
        name: product.name,
        annual_rate: product.annual_rate,
        opening_fee: product.opening_fee,
        iva: product.iva,
        min_amount: product.min_amount,
        max_amount: product.max_amount,
        amount_step: product.amount_step,
        min_term: product.min_term,
        max_term: product.max_term,
        active: product.active,
        moratory_rate: product.moratory_rate,
        grace_days: product.grace_days,
        early_payoff_penalty: product.early_payoff_penalty,
        min_forced_months: product.min_forced_months,
        required_documents: [...product.required_documents],
    }),
);

const fields = [
    { key: 'annual_rate', label: 'Tasa ordinaria anual (%)', step: '0.01' },
    { key: 'opening_fee', label: 'Comisión por apertura (%)', step: '0.01' },
    { key: 'iva', label: 'IVA (%)', step: '0.01' },
    { key: 'amount_step', label: 'Incremento del monto ($)', step: '1' },
    { key: 'min_amount', label: 'Monto mínimo ($)', step: '1' },
    { key: 'max_amount', label: 'Monto máximo ($)', step: '1' },
    { key: 'min_term', label: 'Plazo mínimo (meses)', step: '1' },
    { key: 'max_term', label: 'Plazo máximo (meses)', step: '1' },
    { key: 'moratory_rate', label: 'Tasa moratoria anual (%)', step: '0.01' },
    { key: 'grace_days', label: 'Días de gracia antes de mora', step: '1' },
    { key: 'early_payoff_penalty', label: 'Penalización por liquidar antes (%)', step: '0.01' },
    { key: 'min_forced_months', label: 'Meses forzosos (0 = sin penalización)', step: '1' },
];

const save = (form) => {
    form.put(route('admin.loan-products.update', form.type), {
        preserveScroll: true,
        onSuccess: () => toast.success('Producto actualizado. Ya se refleja en el simulador y en los préstamos nuevos.'),
        onError: () => toast.error('Revisa los campos marcados en rojo.'),
    });
};
</script>

<template>
    <Head title="Productos y tasas" />

    <DashboardLayout>
        <div class="flex flex-1 flex-col gap-4 p-4 pt-0">
            <div class="mt-5">
                <h1 class="text-2xl font-bold text-gray-900">Productos y tasas</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Estos valores alimentan el simulador del sitio y los préstamos nuevos. Los créditos ya aprobados conservan
                    la tasa de su plan de pagos.
                </p>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card v-for="form in forms" :key="form.type">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <span>{{ form.name }}</span>
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 text-xs font-semibold',
                                    form.active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600',
                                ]"
                            >
                                {{ form.active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </CardTitle>
                        <CardDescription>Tipo: {{ form.type }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="save(form)">
                            <div>
                                <label :for="`${form.type}-name`" class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                                <input
                                    :id="`${form.type}-name`"
                                    v-model="form.name"
                                    type="text"
                                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div v-for="field in fields" :key="field.key">
                                    <label :for="`${form.type}-${field.key}`" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ field.label }}
                                    </label>
                                    <input
                                        :id="`${form.type}-${field.key}`"
                                        v-model="form[field.key]"
                                        type="number"
                                        :step="field.step"
                                        class="block w-full rounded-lg border bg-gray-50 p-2.5 text-sm"
                                        :class="form.errors[field.key] ? 'border-red-400' : 'border-gray-300'"
                                    />
                                    <p v-if="form.errors[field.key]" class="mt-1 text-sm text-red-600">{{ form.errors[field.key] }}</p>
                                </div>
                            </div>

                            <fieldset>
                                <legend class="mb-1 block text-sm font-medium text-gray-700">Documentos obligatorios para aprobar</legend>
                                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                                    <label v-for="doc in documentTypes" :key="doc" class="flex items-center gap-2 text-sm text-gray-700">
                                        <input v-model="form.required_documents" type="checkbox" :value="doc" class="rounded border-gray-300" />
                                        {{ doc }}
                                    </label>
                                </div>
                                <p v-if="form.errors.required_documents" class="mt-1 text-sm text-red-600">{{ form.errors.required_documents }}</p>
                            </fieldset>

                            <label class="flex items-center gap-3 text-sm text-gray-700">
                                <input v-model="form.active" type="checkbox" class="rounded border-gray-300" />
                                Producto activo
                            </label>

                            <div class="flex justify-end">
                                <Button type="submit" :disabled="form.processing || !form.isDirty">
                                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
