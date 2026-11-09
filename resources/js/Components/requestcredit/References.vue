<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-medium text-gray-900">Referencias y cuenta para el depósito</h2>
            <p class="mt-1 text-sm text-gray-600">
                Danos dos personas que te conozcan (no pueden tener tu mismo teléfono) y la cuenta CLABE donde quieres recibir el dinero.
                La CLABE tiene 18 dígitos y viene en tu estado de cuenta o en la app de tu banco.
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
            <FormInput
                v-model="form.clabe"
                label="Cuenta CLABE (18 dígitos)"
                required
                placeholder="000000000000000000"
                :error="errors.clabe"
                @focusout="validateClabe"
            />

            <div v-for="(reference, index) in form.references" :key="index" class="rounded-lg border border-gray-200 p-4">
                <p class="mb-3 text-sm font-medium text-gray-900">Referencia {{ index + 1 }}</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <FormInput v-model="reference.name" label="Nombre completo" required :error="errors[`references.${index}.name`]" />
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Relación <span class="text-red-500">*</span></label>
                        <select v-model="reference.relationship" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Selecciona</option>
                            <option v-for="option in relationships" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <span v-if="errors[`references.${index}.relationship`]" class="mt-1 text-sm text-red-600">{{ errors[`references.${index}.relationship`] }}</span>
                    </div>
                    <FormInput
                        v-model="reference.phone"
                        label="Teléfono (10 dígitos)"
                        required
                        :error="errors[`references.${index}.phone`]"
                    />
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button
                    type="button"
                    class="rounded-md bg-gray-500 px-4 py-2 text-white transition-colors duration-200 hover:bg-gray-600"
                    :disabled="store.isSubmitting"
                    @click="store.previousStep()"
                >
                    Anterior
                </button>
                <button
                    type="submit"
                    class="rounded-md bg-vermilion-500 px-4 py-2 text-white transition-colors duration-200 hover:bg-vermilion-600 disabled:opacity-60"
                    :disabled="store.isSubmitting"
                >
                    Siguiente
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import FormInput from '../FormInput.vue';
import { useRequestCreditStore } from '@/stores/requestCredit';
import { ref, watch } from 'vue';

const store = useRequestCreditStore();
const relationships = ['Padre o madre', 'Hermano(a)', 'Hijo(a)', 'Cónyuge o pareja', 'Otro familiar', 'Amigo(a)', 'Compañero(a) de trabajo', 'Vecino(a)'];

const form = ref(JSON.parse(JSON.stringify(store.referencesInfo)));
const errors = ref({});

watch(form, (value) => store.updateReferencesInfo(value), { deep: true });

// Mismo dígito de control que valida el servidor (pesos 3, 7, 1)
const clabeIsValid = (value) => {
    const clabe = String(value || '').replace(/\s+/g, '');
    if (!/^\d{18}$/.test(clabe)) return false;
    const weights = [3, 7, 1];
    const sum = clabe.slice(0, 17).split('').reduce((acc, digit, i) => acc + ((Number(digit) * weights[i % 3]) % 10), 0);
    return (10 - (sum % 10)) % 10 === Number(clabe[17]);
};

const validateClabe = () => {
    errors.value.clabe = clabeIsValid(form.value.clabe) ? '' : 'Revisa la CLABE: deben ser 18 dígitos y estar bien escrita.';
};

const validate = () => {
    errors.value = {};
    validateClabe();
    const customerPhone = store.personalInfo.phone;
    form.value.references.forEach((reference, index) => {
        if (!reference.name.trim()) errors.value[`references.${index}.name`] = 'Escribe el nombre.';
        if (!reference.relationship) errors.value[`references.${index}.relationship`] = 'Selecciona la relación.';
        if (!/^\d{10}$/.test(reference.phone)) errors.value[`references.${index}.phone`] = 'Debe tener 10 dígitos.';
        else if (reference.phone === customerPhone) errors.value[`references.${index}.phone`] = 'No puede ser tu propio teléfono.';
    });
    const [a, b] = form.value.references;
    if (a.phone && a.phone === b.phone) errors.value['references.1.phone'] = 'Debe ser distinto al de la otra referencia.';

    return !Object.values(errors.value).some(Boolean);
};

const handleSubmit = async () => {
    if (!validate()) {
        store.setErrors({ submit: 'Revisa los campos marcados en rojo antes de continuar.' });
        return;
    }
    await store.sendReferences();
};
</script>
